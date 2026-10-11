<?php

namespace App\Services;

use App\Models\Books;
use App\Models\Loans;
use App\Models\Users;
use App\Helpers\Database\MongoHelper;
use MongoDB\BSON\UTCDateTime;

class BookService
{
    public const DEFAULT_AUTHOR = 'Unknown Author';
    public const DEFAULT_LOAN_DAYS = 14;

    private Books $book;
    private Loans $loans;

    public function __construct()
    {
        $this->book = new Books();
        $this->loans = new Loans();
    }

    /**
     * Copies a book is lent with. 0 = open access; anything more means it can only be read while
     * borrowed, and is never downloadable.
     *
     * @param array<string, mixed> $book
     */
    public static function loanCopies(array $book): int
    {
        return max(0, (int) ($book['loan_copies'] ?? 0));
    }

    public function hasActiveLoan(string $bookId, string $userId): bool
    {
        return $this->loans->activeFor($bookId, $userId) !== null;
    }

    /**
     * Borrow a copy of a lent book. Returns the due date (ISO 8601), or null when every copy is out.
     *
     * @param array<string, mixed> $book
     */
    public function borrow(string $bookId, array $book, string $userId): ?string
    {
        $days = (int) ($book['loan_days'] ?? 0) ?: self::DEFAULT_LOAN_DAYS;
        $loan = $this->loans->borrow($bookId, $userId, self::loanCopies($book), $days);
        return $loan === null ? null : self::isoDate($loan['due_at']);
    }

    public function returnBook(string $bookId, string $userId): void
    {
        $this->loans->returnBook($bookId, $userId);
    }

    /**
     * What the book page shows about lending: copies, how many are free, and the caller's due date.
     *
     * @param array<string, mixed> $book
     * @return array{copies: int, available: int, loan_days: int, due_at: ?string}
     */
    public function loanStatus(string $bookId, array $book, ?string $userId): array
    {
        $copies = self::loanCopies($book);
        $mine = $userId === null ? null : $this->loans->activeFor($bookId, $userId);
        return [
            'copies' => $copies,
            'available' => max(0, $copies - $this->loans->activeCount($bookId)),
            'loan_days' => (int) ($book['loan_days'] ?? 0) ?: self::DEFAULT_LOAN_DAYS,
            'due_at' => $mine === null ? null : self::isoDate($mine['due_at']),
        ];
    }

    /**
     * Loan records for the admin, with book title and borrower filled in.
     * status: active (still out), returned (given back early) or expired (ran out its period).
     *
     * @return list<array<string, mixed>>
     */
    public function loanHistory(bool $activeOnly): array
    {
        // ponytail: newest 1000 only, add paging when a library outgrows it
        $loans = $this->loans->history($activeOnly, 1000);
        $ids = fn (string $field) => array_values(array_unique(array_map(
            fn ($loan) => MongoHelper::createObjectId((string) $loan[$field]),
            $loans
        )));
        $byId = function (array $docs): array {
            $map = [];
            foreach ($docs as $doc) {
                $map[(string) $doc['_id']] = $doc;
            }
            return $map;
        };
        $books = $byId($this->book->findAll(['_id' => ['$in' => $ids('book_id')]], [
            'projection' => ['title' => 1, 'author' => 1, 'loan_copies' => 1], 'limit' => 0,
        ]));
        $users = $byId((new Users())->findAll(['_id' => ['$in' => $ids('user_id')]], [
            'projection' => ['username' => 1, 'email' => 1], 'limit' => 0,
        ]));

        $now = time();
        return array_map(function ($loan) use ($books, $users, $now) {
            $book = $books[$loan['book_id']] ?? null;
            $user = $users[$loan['user_id']] ?? null;
            $due = $loan['due_at'] instanceof UTCDateTime ? $loan['due_at']->toDateTime()->getTimestamp() : 0;
            $returned = self::isoDate($loan['returned_at'] ?? null);
            return [
                'book_id' => $loan['book_id'],
                'book_title' => $book['title'] ?? null,
                'book_author' => $book['author'] ?? null,
                'user_id' => $loan['user_id'],
                'username' => $user['username'] ?? null,
                'email' => $user['email'] ?? null,
                'borrowed_at' => self::isoDate($loan['borrowed_at']),
                'due_at' => self::isoDate($loan['due_at']),
                'returned_at' => $returned,
                'status' => $returned !== null ? 'returned' : ($due > $now ? 'active' : 'expired'),
            ];
        }, $loans);
    }

    private static function isoDate(mixed $date): ?string
    {
        return $date instanceof UTCDateTime ? $date->toDateTime()->format(DATE_ATOM) : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getAllBooks(): array
    {
        return $this->book->getAllBooks();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getFeaturedBooks(): array
    {
        return $this->book->getFeaturedBooks();
    }

    /**
     * @return array<string, mixed>
     */
    public function deleteBook(string $id): array
    {
        return $this->book->deleteBook($id);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getPublicBooks(): array
    {
        return $this->book->getPublicBooks();
    }

    /**
     * @return list<string>
     */
    public function getCategories(): array
    {
        return $this->book->getDistinctCategories();
    }

    public const CATALOG_PAGE_SIZE = 24;

    /**
     * Catalog search with facets (see Books::catalog). Untrusted query-string input is normalised
     * here: unknown filters/sorts are ignored, values are trimmed strings, capped in count and length.
     *
     * @param array<string, mixed> $params Raw $_GET: q, category[], format[], author[], year_from, year_to, sort, page
     * @return array{
     *     books: list<array<string, mixed>>, total: int, page: int, pageSize: int,
     *     facets: array<string, list<array{value: string, count: int}>>, years: array{min: int, max: int}|null
     * }
     */
    public function catalog(array $params, bool $publicOnly): array
    {
        $strings = function (mixed $value): array {
            $values = is_array($value) ? $value : [$value];
            $values = array_map(fn ($v) => is_scalar($v) ? mb_substr(trim((string) $v), 0, 200) : '', $values);
            return array_slice(array_values(array_unique(array_filter($values, fn ($v) => $v !== ''))), 0, 20);
        };
        $filters = [
            'category' => $strings($params['category'] ?? []),
            'format' => array_map('strtolower', $strings($params['format'] ?? [])),
            'author' => $strings($params['author'] ?? []),
        ];
        $year = fn (string $key) => is_string($params[$key] ?? null) && preg_match('/^\d{1,4}$/', trim($params[$key]))
            ? (int) $params[$key]
            : null;
        $years = [$year('year_from'), $year('year_to')];
        if ($years[0] !== null && $years[1] !== null && $years[0] > $years[1]) {
            $years = [$years[1], $years[0]];
        }
        $sort = is_string($params['sort'] ?? null) && isset(Books::CATALOG_SORTS[$params['sort']]) ? $params['sort'] : 'title';
        $page = max(1, min(1000, (int) ($params['page'] ?? 1)));
        $text = is_string($params['q'] ?? null) ? mb_substr(trim($params['q']), 0, 200) : '';

        $skip = ($page - 1) * self::CATALOG_PAGE_SIZE;
        $result = $this->book->catalog($text, $filters, $years, $publicOnly, $sort, $skip, self::CATALOG_PAGE_SIZE);
        return $result + ['page' => $page, 'pageSize' => self::CATALOG_PAGE_SIZE];
    }

    /**
     * @param array<int, string> $categories
     * @return array<string, mixed>|false
     */
    public function updateBook(
        string $id,
        string $title,
        string $author,
        string $year,
        string $description,
        array $categories,
        string $status,
        mixed $featured,
        string $isbn,
        bool $downloadable = true,
        ?int $loanCopies = null,
        ?int $loanDays = null
    ): array|false {
        // Add validation here

        // Properly handle featured parameter conversion to boolean
        $featuredBool = false;
        if ($featured === true || $featured === 'true' || $featured === 1 || $featured === '1') {
            $featuredBool = true;
        }

        $book = [
            'title' => $title,
            'author' => $author,
            'year' => (int)$year,
            'description' => $description,
            'categories' => $categories,
            'isbn' => $isbn,
            'downloadable' => $downloadable,
            'loan_copies' => $loanCopies,
            'loan_days' => $loanDays,
            'featured' => $featuredBool,
            'status' => $status,
            'updated_at' => new UTCDateTime()
        ];

        return $this->book->updateBook($id, $book);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getBookDetails(string $id): ?array
    {
        return $this->book->getBookDetails($id);
    }

    /**
     * @param array<int, string> $categories
     * @return array<string, mixed>
     */
    public function addBook(
        string $title,
        string $author,
        string $year,
        string $description,
        array $categories,
        string $isbn,
        ?string $filePath = null,
        ?string $thumbnailPath = null,
        bool $downloadable = true,
        string $fileType = 'pdf',
        string $fileExtension = 'pdf'
    ): array {
        // Add validation here

        $book = [
            'title' => $title,
            'author' => trim($author) !== '' ? trim($author) : self::DEFAULT_AUTHOR,
            'year' => (int)$year,
            'description' => $description,
            'categories' => $categories,
            'isbn' => $isbn,
            'file_path' => $filePath,          // Renamed from pdf_path for clarity
            'file_type' => $fileType,          // New field to store the file format type
            'file_extension' => $fileExtension, // New field to store the file extension
            'thumbnail' => $thumbnailPath,
            'created_at' => new UTCDateTime(),
            'updated_at' => new UTCDateTime(),
            'status' => 'draft',
            'views' => 0,
            'downloads' => 0,
            'featured' => false,
            'downloadable' => $downloadable,
            'reviews' => []
        ];

        return $this->book->addBook($book);
    }

    /**
     * Search for books based on multiple criteria
     *
     * @param array<string, mixed>|string $params
     * @param bool $publicOnly Restrict results to status: 'public' (callers pass true for
     *                         any non-admin/unauthenticated caller so drafts stay hidden)
     * @return list<array<string, mixed>>
     */
    public function searchBooks(array|string $params, bool $publicOnly = false): array
    {
        // If only a string is passed, treat it as a title search (backwards compatibility)
        if (is_string($params)) {
            $params = ['title' => $params];
        }

        // preg_quote() escapes regex metacharacters in the user-supplied term before it
        // reaches MongoDB's $regex (PCRE) evaluation — an unescaped term lets a caller submit
        // a pathological pattern (e.g. nested quantifiers) that costs catastrophic-backtracking
        // CPU time per document scanned (CWE-1333); this is a substring search, not a regex API.
        $query = [];
        if (!empty($params['title'])) {
            $query['title'] = ['$regex' => preg_quote((string) $params['title']), '$options' => 'i'];
        }
        if (!empty($params['author'])) {
            $query['author'] = ['$regex' => preg_quote((string) $params['author']), '$options' => 'i'];
        }
        if (!empty($params['category'])) {
            $query['categories'] = ['$in' => [$params['category']]];
        }

        if (empty($query)) {
            return [];
        }

        if ($publicOnly) {
            $query['status'] = 'public';
        }

        try {
            return $this->book->searchBooks($query);
        } catch (\Exception $e) {
            error_log("Search error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * @param array<string, mixed> $review
     * @return array<string, mixed>|false
     */
    public function saveReview(string $bookId, array $review, mixed $rating = null): array|false
    {
        // Make sure rating is included
        if (isset($rating) && !isset($review['rating'])) {
            $review['rating'] = intval($rating);
        }

        // Make sure review has a timestamp if not already set
        if (!isset($review['created_at'])) {
            $review['created_at'] = date('Y-m-d H:i:s');
        }

        // Add the review to the book, or replace this user's existing one
        $result = $this->book->saveReview($bookId, $review);

        if ($result) {
            // Update the book's average rating
            $this->updateBookRating($bookId);
        }

        return $result;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getBookByTitle(string $title): ?array
    {
        return $this->book->getBookByTitle($title);
    }

    /**
     * Update book's average rating based on all reviews
     *
     * @return array<string, mixed>|false
     */
    private function updateBookRating(string $bookId): array|false
    {
        // getBookReviews() turns the driver's BSONArray/BSONDocument values into plain arrays array_column() accepts.
        $ratings = array_column($this->getBookReviews($bookId), 'rating');
        if ($ratings === []) {
            return false;
        }

        $count = count($ratings);
        $averageRating = round(array_sum($ratings) / $count, 1);

        // Update the book with the new average rating
        return $this->book->updateBookRating($bookId, $averageRating, $count);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getBookReviews(string $bookId): array
    {
        $book = $this->book->getBookDetails($bookId);
        if (!$book || empty($book['reviews'])) {
            return [];
        }

        $reviews = $book['reviews'];
        $reviews = is_object($reviews) && method_exists($reviews, 'getArrayCopy')
            ? $reviews->getArrayCopy()
            : (array) $reviews;

        return array_values(array_map(
            fn ($review) => is_object($review) && method_exists($review, 'getArrayCopy')
                ? $review->getArrayCopy()
                : (array) $review,
            $reviews
        ));
    }
}

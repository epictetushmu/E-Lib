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
    private NotificationService $notifications;

    public function __construct()
    {
        $this->book = new Books();
        $this->loans = new Loans();
        $this->notifications = new NotificationService();
    }

    /**
     * Copies a book is lent with. For a PDF: 0 = open access; more means it can only be read while
     * borrowed, and is never downloadable. For a physical book: copies on the shelf (0 = reference only).
     *
     * @param array<string, mixed> $book
     */
    public static function loanCopies(array $book): int
    {
        return max(0, (int) ($book['loan_copies'] ?? 0));
    }

    /**
     * @param array<string, mixed> $book
     */
    public static function isPhysical(array $book): bool
    {
        return ($book['file_type'] ?? null) === 'physical';
    }

    /**
     * @param array<string, mixed> $book
     */
    private static function loanDays(array $book): int
    {
        return (int) ($book['loan_days'] ?? 0) ?: self::DEFAULT_LOAN_DAYS;
    }

    public function hasActiveLoan(string $bookId, string $userId): bool
    {
        return $this->loans->activeFor($bookId, $userId) !== null;
    }

    /**
     * Reader takes a copy: a PDF loan starts now, a physical copy is held for pickup.
     * Returns the slot (due_at / free_at as ISO 8601), or null when every copy is out.
     *
     * @param array<string, mixed> $book
     * @return array{due_at: ?string, pickup_by: ?string}|null
     */
    public function borrow(string $bookId, array $book, string $userId): ?array
    {
        $copies = self::loanCopies($book);
        $slot = self::isPhysical($book)
            ? $this->loans->reserve($bookId, $userId, $copies)
            : $this->loans->borrow($bookId, $userId, $copies, self::loanDays($book));
        if ($slot === null) {
            return null;
        }
        $due = self::isoDate($slot['due_at'] ?? null);
        return ['due_at' => $due, 'pickup_by' => $due === null ? self::isoDate($slot['free_at']) : null];
    }

    /**
     * Librarian hands a physical copy to a reader (their reservation, or a walk-in).
     *
     * @param array<string, mixed> $book
     * @return string|null Due date, or null when they already have it out or no copy is free
     */
    public function checkOut(string $bookId, array $book, string $userId): ?string
    {
        $loan = $this->loans->checkOut($bookId, $userId, self::loanCopies($book), self::loanDays($book));
        if ($loan === null) {
            return null;
        }
        $due = (string) self::isoDate($loan['due_at']);
        $this->notifications->notify(
            $userId,
            $bookId,
            'Checked out: ' . $book['title'],
            sprintf('You borrowed "%s". Please return it to the library by %s.', $book['title'], self::humanDate($loan['due_at']))
        );
        return $due;
    }

    /**
     * End a hold: return a PDF, cancel a reservation, or (librarian) check a physical copy back in.
     *
     * @param array<string, mixed>|null $book Null when the book has been deleted
     * @return bool False when the user held nothing
     */
    public function returnBook(string $bookId, ?array $book, string $userId): bool
    {
        $loan = $this->loans->returnBook($bookId, $userId);
        if ($loan === null) {
            return false;
        }
        if (($loan['kind'] ?? null) === 'physical' && $loan['returned_at'] !== null) {
            $title = $book['title'] ?? 'your book';
            $this->notifications->notify(
                $userId,
                $bookId,
                'Returned: ' . $title,
                sprintf('"%s" was checked back in on %s. Thank you!', $title, self::humanDate($loan['returned_at']))
            );
        }
        return true;
    }

    /**
     * True when the user's hold on the book is a reservation not yet picked up.
     */
    public function hasReservation(string $bookId, string $userId): bool
    {
        $slot = $this->loans->activeFor($bookId, $userId);
        return $slot !== null && $slot['due_at'] === null;
    }

    /**
     * What the book page shows about lending: copies, how many are free, and the caller's hold.
     *
     * @param array<string, mixed> $book
     * @return array{copies: int, available: int, loan_days: int, due_at: ?string, pickup_by: ?string, pickup_days: int}
     */
    public function loanStatus(string $bookId, array $book, ?string $userId): array
    {
        $copies = self::loanCopies($book);
        $mine = $userId === null ? null : $this->loans->activeFor($bookId, $userId);
        $due = self::isoDate($mine['due_at'] ?? null);
        return [
            'copies' => $copies,
            'available' => max(0, $copies - $this->loans->activeCount($bookId)),
            'loan_days' => self::loanDays($book),
            'due_at' => $due,
            'pickup_by' => $mine !== null && $due === null ? self::isoDate($mine['free_at']) : null,
            'pickup_days' => Loans::PICKUP_DAYS,
        ];
    }

    /**
     * Loan records with book title and borrower filled in — all of them (admin) or one reader's.
     *
     * Status: digital → active | returned | expired; physical → reserved | not_collected | cancelled |
     * out | overdue | returned.
     *
     * @return list<array<string, mixed>>
     */
    public function loanHistory(?string $userId = null): array
    {
        // ponytail: newest 1000 only, add paging when a library outgrows it
        $loans = $this->loans->history($userId === null ? [] : ['user_id' => $userId], 1000);
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
            'projection' => ['title' => 1, 'author' => 1, 'shelf_location' => 1], 'limit' => 0,
        ]));
        $users = $userId !== null ? [] : $byId((new Users())->findAll(['_id' => ['$in' => $ids('user_id')]], [
            'projection' => ['username' => 1, 'email' => 1], 'limit' => 0,
        ]));

        return array_map(function ($loan) use ($books, $users) {
            $book = $books[$loan['book_id']] ?? null;
            $user = $users[$loan['user_id']] ?? null;
            return [
                'id' => (string) $loan['_id'],
                'kind' => $loan['kind'] ?? 'digital',
                'book_id' => $loan['book_id'],
                'book_title' => $book['title'] ?? null,
                'book_author' => $book['author'] ?? null,
                'shelf_location' => $book['shelf_location'] ?? null,
                'user_id' => $loan['user_id'],
                'username' => $user['username'] ?? null,
                'email' => $user['email'] ?? null,
                'reserved_at' => self::isoDate($loan['reserved_at'] ?? null),
                'pickup_by' => self::isoDate($loan['pickup_by'] ?? null),
                'borrowed_at' => self::isoDate($loan['borrowed_at'] ?? null),
                'due_at' => self::isoDate($loan['due_at'] ?? null),
                'returned_at' => self::isoDate($loan['returned_at'] ?? null),
                'cancelled_at' => self::isoDate($loan['cancelled_at'] ?? null),
                'status' => self::loanState($loan),
            ];
        }, $loans);
    }

    /**
     * Email + in-app notice for each physical loan that has gone overdue, once per loan.
     * Run by scripts/notify-overdue.php.
     */
    public function notifyOverdue(): int
    {
        $sent = 0;
        foreach ($this->loans->overdueUnnotified() as $loan) {
            $book = $this->book->getBookDetails((string) $loan['book_id']);
            $title = $book['title'] ?? 'a library book';
            $this->notifications->notify(
                (string) $loan['user_id'],
                (string) $loan['book_id'],
                'Overdue: ' . $title,
                sprintf('"%s" was due back on %s. Please return it to the library.', $title, self::humanDate($loan['due_at']))
            );
            $this->loans->markOverdueNotified((string) $loan['_id']);
            $sent++;
        }
        return $sent;
    }

    /**
     * @param array<string, mixed> $loan A LoanHistory document
     */
    public static function loanState(array $loan, ?int $now = null): string
    {
        $now ??= time();
        $ts = fn (string $field) => ($loan[$field] ?? null) instanceof UTCDateTime
            ? $loan[$field]->toDateTime()->getTimestamp()
            : null;
        if ($ts('returned_at') !== null) {
            return 'returned';
        }
        if ($ts('cancelled_at') !== null) {
            return 'cancelled';
        }
        $due = $ts('due_at');
        if (($loan['kind'] ?? 'digital') === 'digital') {
            return $due !== null && $due > $now ? 'active' : 'expired';
        }
        if ($due === null) {
            return ($ts('pickup_by') ?? 0) > $now ? 'reserved' : 'not_collected';
        }
        return $due > $now ? 'out' : 'overdue';
    }

    private static function humanDate(mixed $date): string
    {
        return $date instanceof UTCDateTime ? $date->toDateTime()->format('j M Y') : '';
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
        ?int $loanDays = null,
        ?string $shelfLocation = null
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
            'shelf_location' => $shelfLocation,
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
     * Catalogue a book that only exists on the library's shelves: no file, lent by copies.
     * file_type/file_extension "physical" is what the catalog's Format filter shows.
     *
     * @param array<int, string> $categories
     * @return array<string, mixed>
     */
    public function addPhysicalBook(
        string $title,
        string $author,
        string $year,
        string $description,
        array $categories,
        string $isbn,
        string $status,
        int $copies,
        int $loanDays,
        string $shelfLocation
    ): array {
        return $this->book->addBook([
            'title' => $title,
            'author' => trim($author) !== '' ? trim($author) : self::DEFAULT_AUTHOR,
            'year' => (int) $year,
            'description' => $description,
            'categories' => $categories,
            'isbn' => $isbn,
            'file_path' => null,
            'file_type' => 'physical',
            'file_extension' => 'physical',
            'thumbnail' => null,
            'created_at' => new UTCDateTime(),
            'updated_at' => new UTCDateTime(),
            'status' => $status === 'public' ? 'public' : 'draft',
            'views' => 0,
            'downloads' => 0,
            'featured' => false,
            'downloadable' => false,
            'loan_copies' => $copies,
            'loan_days' => $loanDays,
            'shelf_location' => $shelfLocation,
            'reviews' => [],
        ]);
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

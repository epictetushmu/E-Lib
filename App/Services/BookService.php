<?php

namespace App\Services;

use App\Models\Books;
use MongoDB\BSON\UTCDateTime;

class BookService
{
    private const DEFAULT_AUTHOR = 'Unknown Author';

    private Books $book;

    public function __construct()
    {
        $this->book = new Books();
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
        bool $downloadable = true
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
    public function addReview(string $bookId, array $review, mixed $rating = null): array|false
    {
        // Make sure rating is included
        if (isset($rating) && !isset($review['rating'])) {
            $review['rating'] = intval($rating);
        }

        // Make sure review has a timestamp if not already set
        if (!isset($review['created_at'])) {
            $review['created_at'] = date('Y-m-d H:i:s');
        }

        // Add the review to the book
        $result = $this->book->addReview($bookId, $review);

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

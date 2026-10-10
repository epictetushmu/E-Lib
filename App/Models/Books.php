<?php

namespace App\Models;

use App\Helpers\Database\MongoHelper;
use InvalidArgumentException;

/**
 * Books model handles all book-related data operations
 */
class Books extends BaseModel
{
    protected string $collection = 'Books';

    /**
     * Required fields for a valid book
     */
    private const REQUIRED_FIELDS = ['title'];

    /**
     * Get all books in the database
     *
     * @return list<array<string, mixed>> Array of all books
     */
    public function getAllBooks(): array
    {
        return $this->findAll();
    }

    /**
     * Get only public books
     *
     * @return list<array<string, mixed>> Array of public books
     */
    public function getPublicBooks(): array
    {
        return $this->findAll(['status' => 'public']);
    }

    /**
     * Get a specific book by ID
     *
     * @param string $id The book ID
     * @return array<string, mixed>|null The book data or null if not found
     * @throws InvalidArgumentException If the ID format is invalid
     */
    public function getBookDetails(string $id)
    {
        return $this->findById($id);
    }

    /**
     * Get featured books
     *
     * @param int $limit Maximum number of books to return
     * @return list<array<string, mixed>> Featured books
     */
    public function getFeaturedBooks(int $limit = 20): array
    {
        $pipeline = [
            ['$match' => ['featured' => true, 'status' => 'public']],
            ['$sample' => ['size' => $limit]]
        ];
        return $this->db->aggregate($this->collection, $pipeline);
    }

    /**
     * Add a new book
     *
     * @param array<string, mixed> $book Book data
     * @return array<string, mixed> Insert operation result
     * @throws InvalidArgumentException If validation fails
     */
    public function addBook(array $book): array
    {
        return $this->create($book);
    }

    /**
     * Search books by criteria
     *
     * @param array<string, mixed> $searchQuery Search criteria
     * @return list<array<string, mixed>> Matching books
     */
    public function searchBooks(array $searchQuery): array
    {
        return $this->findAll($searchQuery);
    }

    /**
     * Get the distinct category values used across all books, sorted alphabetically
     *
     * @return list<string>
     */
    public function getDistinctCategories(): array
    {
        // distinct() surfaces a BSON Undefined value for documents where the
        // categories field is entirely absent — filter down to real strings.
        $categories = array_values(array_filter($this->db->distinct($this->collection, 'categories'), 'is_string'));
        sort($categories);
        return $categories;
    }

    /**
     * Save a user's review of a book: edits their existing review, or adds one if they have none.
     *
     * @param string $bookId Book ID
     * @param array<string, mixed> $review Review data, including user_id
     * @return array<string, mixed>|false Update result or false on error
     * @throws InvalidArgumentException If the ID format is invalid
     */
    public function saveReview(string $bookId, array $review)
    {
        try {
            $id = $this->ensureId($bookId);
            $userId = $review['user_id'];

            // Each write is a no-op when the other applies, and the $ne guard makes the push
            // atomic per document, so concurrent submits still can't create a second review.
            $edit = $this->db->update(
                $this->collection,
                ['_id' => $id, 'reviews.user_id' => $userId],
                ['$set' => [
                    'reviews.$.username' => $review['username'] ?? null,
                    'reviews.$.rating' => $review['rating'] ?? null,
                    'reviews.$.comment' => $review['comment'] ?? null,
                    'reviews.$.updated_at' => date('Y-m-d H:i:s'),
                ]]
            );
            if (isset($edit['error'])) {
                return $edit;
            }

            return $this->db->update(
                $this->collection,
                ['_id' => $id, 'reviews.user_id' => ['$ne' => $userId]],
                ['$push' => ['reviews' => $review]]
            );
        } catch (\Exception $e) {
            error_log("Error saving review: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Find a book by title (case-insensitive)
     *
     * @param string $title Book title
     * @return array<string, mixed>|null Book data or null if not found
     */
    public function getBookByTitle(string $title)
    {
        $regex = MongoHelper::createRegex($title, 'i'); // 'i' for case-insensitive
        return $this->db->findOne($this->collection, ['title' => $regex]);
    }

    /**
     * Update a book's rating and review count
     *
     * @param string $bookId Book ID
     * @param float $rating New average rating
     * @param int $reviewCount New review count
     * @return array<string, mixed>|false Update result or false on error
     * @throws InvalidArgumentException If the ID format is invalid
     */
    public function updateBookRating(string $bookId, float $rating, int $reviewCount)
    {
        try {
            return $this->db->update(
                $this->collection,
                ['_id' => $this->ensureId($bookId)],
                [
                    '$set' => [
                        'average_rating' => $rating,
                        'review_count' => $reviewCount
                    ]
                ]
            );
        } catch (\Exception $e) {
            error_log("Error updating book rating: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete a book
     *
     * @param string $id Book ID
     * @return array<string, mixed> Delete operation result
     * @throws InvalidArgumentException If the ID format is invalid
     */
    public function deleteBook(string $id): array
    {
        return $this->deleteById($id);
    }

    /**
     * Update a book
     *
     * @param string $id Book ID
     * @param array<string, mixed> $book Updated book data
     * @return array<string, mixed>|false Update result or false if no fields to update
     * @throws InvalidArgumentException If the ID format is invalid
     */
    public function updateBook(string $id, array $book)
    {
        $filteredBook = array_filter($book, function ($value) {
            return $value !== null && $value !== '';
        });

        if (empty($filteredBook)) {
            return false; // No fields to update
        }

        return $this->updateById($id, $filteredBook);
    }

    /**
     * Validate book data
     *
     * @param array<string, mixed> $data Book data to validate
     * @return array<string, string> Validation errors
     */
    public function validate(array $data): array
    {
        $errors = [];

        // Check required fields
        foreach (self::REQUIRED_FIELDS as $field) {
            if (empty($data[$field])) {
                $errors[$field] = ucfirst($field) . ' is required';
            }
        }

        // Validate year if present
        if (!empty($data['year'])) {
            if (!is_numeric($data['year']) || $data['year'] < 1000 || $data['year'] > date('Y') + 5) {
                $errors['year'] = 'Year must be a valid year between 1000 and ' . (date('Y') + 5);
            }
        }

        // Validate ISBN if present
        if (!empty($data['isbn'])) {
            // Basic ISBN validation (simple check for now)
            if (!preg_match('/^\d{10,13}$/', preg_replace('/[^0-9]/', '', $data['isbn']))) {
                $errors['isbn'] = 'ISBN must be a valid 10 or 13 digit number';
            }
        }

        return $errors;
    }
}

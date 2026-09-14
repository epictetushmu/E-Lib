<?php

namespace App\Models;

use App\Repository\DatabaseRepository;
use App\Helpers\Database\MongoHelper;
use InvalidArgumentException;

/**
 * Books model handles all book-related data operations
 */
class Books extends BaseModel
{
    protected $collection = 'Books';

    /**
     * Required fields for a valid book
     */
    private const REQUIRED_FIELDS = ['title', 'author'];

    /**
     * Optional fields for a book
     */
    private const OPTIONAL_FIELDS = ['year', 'description', 'categories', 'isbn', 'status', 'featured', 'downloadable', 'pdf_path', 'file_path', 'thumbnail', 'thumbnail_path'];

    /**
     * Get all books in the database
     *
     * @return array Array of all books
     */
    public function getAllBooks(): array
    {
        return $this->findAll();
    }

    /**
     * Get only public books
     *
     * @return array Array of public books
     */
    public function getPublicBooks(): array
    {
        return $this->findAll(['status' => 'public']);
    }

    /**
     * Get a specific book by ID
     *
     * @param string $id The book ID
     * @return array|null The book data or null if not found
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
     * @return array Featured books
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
     * @param array $book Book data
     * @return array Insert operation result
     * @throws InvalidArgumentException If validation fails
     */
    public function addBook(array $book): array
    {
        return $this->create($book);
    }

    /**
     * Search books by criteria
     *
     * @param array $searchQuery Search criteria
     * @return array Matching books
     */
    public function searchBooks(array $searchQuery): array
    {
        return $this->findAll($searchQuery);
    }

    /**
     * Add a review to a book
     *
     * @param string $bookId Book ID
     * @param array $review Review data
     * @return array|false Update result or false on error
     * @throws InvalidArgumentException If the ID format is invalid
     */
    public function addReview(string $bookId, array $review)
    {
        try {
            // Return the result of the update operation
            return $this->db->update(
                $this->collection,
                ['_id' => $this->ensureId($bookId)],
                ['$push' => ['reviews' => $review]]
            );
        } catch (\Exception $e) {
            error_log("Error adding review: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Find a book by title (case-insensitive)
     *
     * @param string $title Book title
     * @return array|null Book data or null if not found
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
     * @return array|false Update result or false on error
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
     * @return array Delete operation result
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
     * @param array $book Updated book data
     * @return array|false Update result or false if no fields to update
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
     * @param array $data Book data to validate
     * @return array Validation errors
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

<?php

namespace App\Models;

use App\Helpers\Database\MongoHelper;
use InvalidArgumentException;
use MongoDB\BSON\UTCDateTime;

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
        try {
            return $this->db->aggregate($this->collection, $pipeline);
        } catch (\Exception $e) {
            return []; // Already logged; the home page just shows no featured books.
        }
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

    /** Sort keys for catalog(); the first is the default. */
    public const CATALOG_SORTS = [
        'title' => ['sort_title' => 1],
        'newest' => ['created_at' => -1],
        'year_desc' => ['year_num' => -1, 'sort_title' => 1],
        'year_asc' => ['year_num' => 1, 'sort_title' => 1],
        'views' => ['views' => -1, 'sort_title' => 1],
    ];

    /**
     * Catalog search: one page of books matching $text (title or author) and the selected filters,
     * plus facet counts for subjects, formats and authors, and the span of publication years. Each facet
     * is counted with every filter applied except its own, so its options show what picking them would give.
     *
     * ponytail: computed fields ($convert, $toLower) mean a collection scan per query — fine for tens of
     * thousands of books; beyond that, move to Atlas Search facets on a search index.
     *
     * @param array{category?: list<string>, format?: list<string>, author?: list<string>} $filters
     * @param array{0: ?int, 1: ?int} $years Published from/to (inclusive); null = open-ended
     * @return array{
     *     books: list<array<string, mixed>>, total: int,
     *     facets: array<string, list<array{value: string, count: int}>>, years: array{min: int, max: int}|null
     * }
     */
    public function catalog(string $text, array $filters, array $years, bool $publicOnly, string $sort, int $skip, int $limit): array
    {
        $base = $publicOnly ? ['status' => 'public'] : [];
        if ($text !== '') {
            $regex = MongoHelper::createRegex(preg_quote($text), 'i');
            $base['$or'] = [['title' => $regex], ['author' => $regex]];
        }

        // One condition per filter, on the normalised fields added below.
        $fields = ['category' => 'categories', 'format' => 'format', 'author' => 'author'];
        $conditions = [];
        foreach ($filters as $name => $values) {
            if ($values !== []) {
                $conditions[$name] = [$fields[$name] => ['$in' => $values]];
            }
        }
        $range = array_filter(['$gte' => $years[0], '$lte' => $years[1]], fn ($y) => $y !== null);
        if ($range !== []) {
            $conditions['year'] = ['year_num' => $range];
        }
        $matchExcept = function (?string $facet) use ($conditions): array {
            unset($conditions[$facet ?? '']);
            return ['$match' => (object) array_merge([], ...array_values($conditions))];
        };
        $countBy = fn (string $facet, string $field, int $max) => [
            $matchExcept($facet),
            ...($field === 'categories' ? [['$unwind' => '$categories']] : []),
            ['$match' => [$field => ['$nin' => [null, '']]]],
            ['$group' => ['_id' => '$' . $field, 'count' => ['$sum' => 1]]],
            ['$sort' => ['count' => -1, '_id' => 1]],
            ['$limit' => $max],
            ['$project' => ['_id' => 0, 'value' => '$_id', 'count' => 1]],
        ];

        $year = ['$convert' => ['input' => '$year', 'to' => 'int', 'onError' => null, 'onNull' => null]];
        $result = $this->db->aggregate($this->collection, [
            ['$match' => (object) $base],
            ['$project' => ['reviews' => 0, 'file_path' => 0, 'pdf_path' => 0]],
            ['$addFields' => [
                'year_num' => $year,
                // Books uploaded before file_extension existed were all PDFs.
                // Nested, not 3-argument $ifNull: that needs MongoDB 5, CI runs 4.4.
                'format' => ['$toLower' => ['$ifNull' => ['$file_extension', ['$ifNull' => ['$file_type', 'pdf']]]]],
                'sort_title' => ['$toLower' => '$title'],
            ]],
            ['$facet' => [
                'books' => [
                    $matchExcept(null),
                    ['$sort' => self::CATALOG_SORTS[$sort] ?? self::CATALOG_SORTS['title']],
                    ['$skip' => $skip],
                    ['$limit' => $limit],
                    ['$project' => ['sort_title' => 0, 'year_num' => 0]],
                ],
                'total' => [$matchExcept(null), ['$count' => 'n']],
                'category' => $countBy('category', 'categories', 30),
                'format' => $countBy('format', 'format', 10),
                'author' => $countBy('author', 'author', 12),
                // The year span the other filters leave, shown as the range fields' placeholders.
                'year' => [
                    $matchExcept('year'),
                    ['$match' => ['year_num' => ['$gt' => 0]]],
                    ['$group' => ['_id' => null, 'min' => ['$min' => '$year_num'], 'max' => ['$max' => '$year_num']]],
                ],
            ]],
        ])[0] ?? [];

        return [
            'books' => $result['books'] ?? [],
            'total' => (int) ($result['total'][0]['n'] ?? 0),
            'facets' => [
                'category' => $result['category'] ?? [],
                'format' => $result['format'] ?? [],
                'author' => $result['author'] ?? [],
            ],
            'years' => isset($result['year'][0])
                ? ['min' => (int) $result['year'][0]['min'], 'max' => (int) $result['year'][0]['max']]
                : null,
        ];
    }

    /**
     * Public books the user reviewed, each with only that user's review in `reviews`.
     *
     * @return list<array<string, mixed>>
     */
    public function reviewedBy(string $userId): array
    {
        return $this->findAll(
            ['status' => 'public', 'reviews.user_id' => $userId],
            [
                'projection' => ['title' => 1, 'author' => 1, 'reviews' => ['$elemMatch' => ['user_id' => $userId]]],
                'limit' => 0,
            ]
        );
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
     * Remove a user's review of a book.
     *
     * @return bool Whether there was one to remove
     * @throws InvalidArgumentException If the ID format is invalid
     */
    public function removeReview(string $bookId, string $userId): bool
    {
        $result = $this->db->update(
            $this->collection,
            ['_id' => $this->ensureId($bookId)],
            ['$pull' => ['reviews' => ['user_id' => $userId]]]
        );
        return ($result['modifiedCount'] ?? 0) > 0;
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
        $result = $this->deleteById($id);
        // Its search chunks and loans go with it — here, the one place every delete goes through.
        (new BookChunks($this->db))->deleteForBook($id);
        (new Loans($this->db))->deleteForBook($id);
        (new ReviewReplies($this->db))->deleteForBook($id);
        return $result;
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

        $result = $this->updateById($id, $filteredBook);
        // Chunks carry a copy of the status so content search can hide drafts before ranking.
        if (isset($filteredBook['status'])) {
            (new BookChunks($this->db))->setStatusForBook($id, (string) $filteredBook['status']);
        }
        return $result;
    }

    /**
     * Atomically claim the next book that needs (re)indexing for content search, leasing it so
     * other workers skip it. Covers: never indexed, a lapsed lease (crashed worker), a failed book
     * whose retry time has come, and books indexed by a different embedding model.
     *
     * @return array<string, mixed>|null The claimed book, or null when there's nothing to do
     */
    public function claimNextForIndexing(string $model, int $leaseSeconds, int $maxAttempts): ?array
    {
        $now = new UTCDateTime();
        // A lapsed lease means the worker died mid-book. If that keeps happening (e.g. a PDF that blows
        // the memory limit), stop handing the book out instead of crashing a worker every lease period.
        $this->db->update(
            $this->collection,
            ['index_status' => 'indexing', 'index_lease_until' => ['$lt' => $now], 'index_attempts' => ['$gte' => $maxAttempts]],
            ['$set' => ['index_status' => 'failed', 'index_error' => 'The indexing worker crashed on this book']]
        );
        return $this->db->findOneAndUpdate(
            $this->collection,
            [
                // Only PDFs: text extraction is pdftotext. Other formats stay unindexed.
                'file_path' => MongoHelper::createRegex('\\.pdf$', 'i'),
                '$or' => [
                    ['index_status' => ['$exists' => false]],
                    ['index_status' => 'pending'],
                    ['index_status' => 'indexing', 'index_lease_until' => ['$lt' => $now], 'index_attempts' => ['$lt' => $maxAttempts]],
                    ['index_status' => 'failed', 'index_attempts' => ['$lt' => $maxAttempts], 'index_retry_at' => ['$lt' => $now]],
                    ['index_status' => ['$in' => ['done', 'no_text']], 'index_model' => ['$ne' => $model]],
                ],
            ],
            [
                '$set' => [
                    'index_status' => 'indexing',
                    'index_lease_until' => new UTCDateTime((time() + $leaseSeconds) * 1000),
                ],
                // Counted at claim time, so a worker that dies mid-book still uses up an attempt.
                '$inc' => ['index_attempts' => 1],
            ],
            ['sort' => ['created_at' => 1]]
        );
    }

    /**
     * Write content-search indexing state (index_* fields) without touching anything else.
     *
     * @param array<string, mixed> $set
     * @param array<string, int> $inc
     * @return array<string, mixed>
     */
    public function updateIndexState(string $id, array $set, array $inc = []): array
    {
        $update = ['$set' => $set];
        if ($inc !== []) {
            $update['$inc'] = $inc;
        }
        return $this->db->update($this->collection, ['_id' => $this->ensureId($id)], $update);
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

<?php

namespace App\Models;

use MongoDB\BSON\UTCDateTime;
use MongoDB\Driver\Exception\ServerException;

/**
 * Time-limited loans of books that are lent with a fixed number of copies.
 *
 * Each copy is a slot document with _id "<bookId>:<n>", so the copy limit is enforced by the unique _id
 * index alone: taking a copy is one upsert that only matches a slot whose loan has expired, and fails with
 * a duplicate-key error while the slot is held. Expired loans are simply overwritten, no cleanup job.
 */
class Loans extends BaseModel
{
    protected string $collection = 'Loans';

    private const DUPLICATE_KEY = 11000;

    /**
     * The user's current loan of the book, if any.
     *
     * @return array<string, mixed>|null
     */
    public function activeFor(string $bookId, string $userId): ?array
    {
        return $this->db->findOne($this->collection, [
            'book_id' => $bookId,
            'user_id' => $userId,
            'due_at' => ['$gt' => new UTCDateTime()],
        ]);
    }

    public function activeCount(string $bookId): int
    {
        return count($this->findAll(
            ['book_id' => $bookId, 'due_at' => ['$gt' => new UTCDateTime()]],
            ['projection' => ['_id' => 1], 'limit' => 0]
        ));
    }

    /**
     * Take a free copy for $days days. Returns the loan, or null when every copy is out.
     * Already holding one returns that loan instead of taking a second copy.
     *
     * @return array<string, mixed>|null
     */
    public function borrow(string $bookId, string $userId, int $copies, int $days): ?array
    {
        $existing = $this->activeFor($bookId, $userId);
        if ($existing !== null) {
            return $existing;
        }

        $now = new UTCDateTime();
        $loan = [
            'book_id' => $bookId,
            'user_id' => $userId,
            'borrowed_at' => $now,
            'due_at' => new UTCDateTime((time() + $days * 86400) * 1000),
        ];
        // ponytail: linear scan over slots, fine for the handful of copies a library lends
        for ($slot = 0; $slot < $copies; $slot++) {
            try {
                $taken = $this->db->findOneAndUpdate(
                    $this->collection,
                    ['_id' => "$bookId:$slot", 'due_at' => ['$lte' => $now]],
                    ['$set' => $loan],
                    ['upsert' => true]
                );
                if ($taken !== null) {
                    return $taken;
                }
            } catch (ServerException $e) {
                if ($e->getCode() !== self::DUPLICATE_KEY) {
                    throw $e;
                }
            }
        }
        return null;
    }

    public function returnBook(string $bookId, string $userId): void
    {
        $this->db->delete($this->collection, ['book_id' => $bookId, 'user_id' => $userId]);
    }

    public function deleteForBook(string $bookId): void
    {
        $this->db->delete($this->collection, ['book_id' => $bookId]);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, string>
     */
    public function validate(array $data): array
    {
        return [];
    }
}

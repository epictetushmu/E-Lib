<?php

namespace App\Models;

use MongoDB\BSON\UTCDateTime;

/**
 * In-app messages to one reader (loan checked out, returned, overdue). Scoped by user_id like Annotations.
 */
class Notifications extends BaseModel
{
    protected string $collection = 'Notifications';

    public function add(string $userId, ?string $bookId, string $message): void
    {
        $this->db->insert($this->collection, [
            'user_id' => $userId,
            'book_id' => $bookId,
            'message' => $message,
            'created_at' => new UTCDateTime(),
            'read_at' => null,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forUser(string $userId, int $limit = 100): array
    {
        return $this->findAll(['user_id' => $userId], ['sort' => ['created_at' => -1], 'limit' => $limit]);
    }

    public function markAllRead(string $userId): void
    {
        $this->db->update(
            $this->collection,
            ['user_id' => $userId, 'read_at' => null],
            ['$set' => ['read_at' => new UTCDateTime()]]
        );
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

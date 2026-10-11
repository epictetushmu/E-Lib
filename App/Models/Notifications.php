<?php

namespace App\Models;

use MongoDB\BSON\UTCDateTime;

/**
 * In-app messages to one reader (loan checked out, returned, overdue). Scoped by user_id like Annotations.
 */
class Notifications extends BaseModel
{
    protected string $collection = 'Notifications';

    /** Email delivery: pending until the scheduler sends it (sent), gives up (failed), or mail is off (skipped). */
    public const MAX_EMAIL_ATTEMPTS = 3;

    public function add(string $userId, ?string $bookId, string $subject, string $message): void
    {
        $this->db->insert($this->collection, [
            'user_id' => $userId,
            'book_id' => $bookId,
            'subject' => $subject,
            'message' => $message,
            'created_at' => new UTCDateTime(),
            'read_at' => null,
            'email_status' => 'pending',
            'email_attempts' => 0,
        ]);
    }

    /**
     * @return list<array<string, mixed>> Oldest first
     */
    public function pendingEmails(int $limit): array
    {
        return $this->findAll(['email_status' => 'pending'], ['sort' => ['created_at' => 1], 'limit' => $limit]);
    }

    /**
     * @param 'sent'|'failed'|'skipped'|'pending' $status
     */
    public function setEmailStatus(mixed $id, string $status, bool $attempted): void
    {
        $this->db->update(
            $this->collection,
            ['_id' => $id],
            ['$set' => ['email_status' => $status]] + ($attempted ? ['$inc' => ['email_attempts' => 1]] : [])
        );
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

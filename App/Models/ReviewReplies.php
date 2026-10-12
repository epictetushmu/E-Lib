<?php

namespace App\Models;

use MongoDB\BSON\UTCDateTime;

/**
 * Replies under a book review, nested: parent_id is the reply being answered (null = answering the
 * review itself). A review is identified by its book and its author (one review per user per book, see
 * Books::saveReview), so every reply carries book_id + review_user_id. Edits and deletes are scoped by
 * user_id — only the reply's author can make them. A deleted reply that others have answered stays as
 * a `deleted` placeholder (text and author cleared) so the answers keep their place in the tree.
 */
class ReviewReplies extends BaseModel
{
    protected string $collection = 'ReviewReplies';

    public const MAX_LENGTH = 2000;

    /**
     * @return array<string, mixed>
     */
    public function add(string $bookId, string $reviewUserId, ?string $parentId, string $userId, string $username, string $text): array
    {
        return $this->db->insert($this->collection, [
            'book_id' => $bookId,
            'review_user_id' => $reviewUserId,
            'parent_id' => $parentId,
            'user_id' => $userId,
            'username' => $username,
            'text' => $text,
            'created_at' => new UTCDateTime(),
            'updated_at' => null,
            'deleted' => false,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $id): ?array
    {
        return preg_match('/^[0-9a-f]{24}$/', $id) ? $this->db->findOne($this->collection, ['_id' => $this->ensureId($id)]) : null;
    }

    public function hasAnswers(string $id): bool
    {
        return $this->db->findOne($this->collection, ['parent_id' => $id]) !== null;
    }

    /** Keep the reply's place in the tree but drop what it said and who said it. */
    public function markDeleted(string $id): void
    {
        $this->db->update(
            $this->collection,
            ['_id' => $this->ensureId($id)],
            ['$set' => ['deleted' => true, 'text' => '', 'username' => '', 'user_id' => null]]
        );
    }

    public function remove(string $id): void
    {
        $this->db->delete($this->collection, ['_id' => $this->ensureId($id)]);
    }

    /**
     * @return list<array<string, mixed>> Oldest first
     */
    public function forBook(string $bookId): array
    {
        return $this->findAll(['book_id' => $bookId], ['sort' => ['created_at' => 1], 'limit' => 0]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findOwned(string $id, string $userId): ?array
    {
        if (!preg_match('/^[0-9a-f]{24}$/', $id)) {
            return null;
        }
        return $this->db->findOne($this->collection, ['_id' => $this->ensureId($id), 'user_id' => $userId, 'deleted' => ['$ne' => true]]);
    }

    public function updateOwned(string $id, string $userId, string $text): void
    {
        $this->db->update(
            $this->collection,
            ['_id' => $this->ensureId($id), 'user_id' => $userId],
            ['$set' => ['text' => $text, 'updated_at' => new UTCDateTime()]]
        );
    }

    public function deleteForReview(string $bookId, string $reviewUserId): void
    {
        $this->db->delete($this->collection, ['book_id' => $bookId, 'review_user_id' => $reviewUserId]);
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

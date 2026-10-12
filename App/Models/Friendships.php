<?php

namespace App\Models;

use MongoDB\BSON\UTCDateTime;

/**
 * Friend requests and friendships between two readers: one document per pair, _id "<smallerId>:<largerId>",
 * so the unique _id alone stops duplicate or crossed requests (like Loans' slots and Waitlist).
 * status is 'pending' (requester_id asked, the other user hasn't answered) or 'accepted'.
 * Declining, cancelling and unfriending all delete the document.
 */
class Friendships extends BaseModel
{
    protected string $collection = 'Friendships';

    public static function pairId(string $a, string $b): string
    {
        return strcmp($a, $b) < 0 ? "$a:$b" : "$b:$a";
    }

    /**
     * Ask $toId to be friends. If $toId already asked $fromId, this accepts their request instead.
     *
     * @return 'requested'|'accepted'|'exists' What happened
     */
    public function request(string $fromId, string $toId): string
    {
        $id = self::pairId($fromId, $toId);
        // A duplicate _id is reported by insert() instead of thrown: the pair already has a document.
        $result = $this->db->insert($this->collection, [
            '_id' => $id,
            'users' => [$fromId, $toId],
            'requester_id' => $fromId,
            'status' => 'pending',
            'created_at' => new UTCDateTime(),
            'accepted_at' => null,
        ]);
        if (!isset($result['error'])) {
            return 'requested';
        }
        return $this->accept($fromId, $toId) ? 'accepted' : 'exists';
    }

    /**
     * Accept $otherId's pending request to $userId. Only the user who was asked can accept.
     */
    public function accept(string $userId, string $otherId): bool
    {
        $result = $this->db->update(
            $this->collection,
            ['_id' => self::pairId($userId, $otherId), 'status' => 'pending', 'requester_id' => $otherId],
            ['$set' => ['status' => 'accepted', 'accepted_at' => new UTCDateTime()]]
        );
        return ($result['modifiedCount'] ?? 0) > 0;
    }

    /**
     * Decline, cancel or unfriend — whichever state the pair is in.
     */
    public function remove(string $userId, string $otherId): bool
    {
        return ($this->db->delete($this->collection, ['_id' => self::pairId($userId, $otherId)])['deletedCount'] ?? 0) > 0;
    }

    public function areFriends(string $a, string $b): bool
    {
        return $this->db->findOne($this->collection, ['_id' => self::pairId($a, $b), 'status' => 'accepted']) !== null;
    }

    /**
     * @return list<array<string, mixed>> Every request and friendship the user is part of
     */
    public function forUser(string $userId): array
    {
        return $this->findAll(['users' => $userId], ['sort' => ['created_at' => -1], 'limit' => 0]);
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

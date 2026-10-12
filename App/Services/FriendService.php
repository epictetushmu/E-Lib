<?php

namespace App\Services;

use App\Helpers\BookDisplayHelper;
use App\Helpers\Database\MongoHelper;
use App\Models\Books;
use App\Models\Friendships;
use App\Models\Users;
use MongoDB\BSON\UTCDateTime;

/**
 * Friend requests between readers. Once both have accepted, each can see the other's saved books and reviews.
 */
class FriendService
{
    private Friendships $friendships;
    private Users $users;
    private Books $books;
    private NotificationService $notifications;

    public function __construct()
    {
        $this->friendships = new Friendships();
        $this->users = new Users();
        $this->books = new Books();
        $this->notifications = new NotificationService();
    }

    /**
     * @return 'not_found'|'self'|'requested'|'accepted'|'exists'
     * @throws \InvalidArgumentException On a malformed email
     */
    public function request(string $userId, string $email): string
    {
        $other = $this->users->getUserByEmail(trim($email));
        if ($other === null) {
            return 'not_found';
        }
        $otherId = (string) $other['_id'];
        if ($otherId === $userId) {
            return 'self';
        }
        $outcome = $this->friendships->request($userId, $otherId);
        $me = $this->username($userId);
        if ($outcome === 'requested') {
            $this->notifications->notify($otherId, null, 'New friend request', "$me wants to be your friend on E-Lib.");
        } elseif ($outcome === 'accepted') {
            $this->notifications->notify($otherId, null, 'Friend request accepted', "$me accepted your friend request.");
        }
        return $outcome;
    }

    public function accept(string $userId, string $otherId): bool
    {
        if (!$this->friendships->accept($userId, $otherId)) {
            return false;
        }
        $me = $this->username($userId);
        $this->notifications->notify($otherId, null, 'Friend request accepted', "$me accepted your friend request.");
        return true;
    }

    public function remove(string $userId, string $otherId): bool
    {
        return $this->friendships->remove($userId, $otherId);
    }

    /**
     * @return list<array{user_id: string, username: string, status: 'friends'|'incoming'|'outgoing', since: string|null}>
     */
    public function list(string $userId): array
    {
        $docs = $this->friendships->forUser($userId);
        $otherIds = array_map(fn ($d) => self::other($d, $userId), $docs);
        $names = [];
        if ($otherIds !== []) {
            $users = $this->users->findAll(
                ['_id' => ['$in' => array_map([MongoHelper::class, 'createObjectId'], $otherIds)]],
                ['projection' => ['username' => 1], 'limit' => 0]
            );
            foreach ($users as $u) {
                $names[(string) $u['_id']] = (string) ($u['username'] ?? '');
            }
        }

        $list = [];
        foreach ($docs as $i => $d) {
            $otherId = $otherIds[$i];
            if (!isset($names[$otherId])) {
                continue; // account gone
            }
            $status = $d['status'] === 'accepted' ? 'friends' : ($d['requester_id'] === $userId ? 'outgoing' : 'incoming');
            $since = $d['accepted_at'] ?? $d['created_at'] ?? null;
            $list[] = [
                'user_id' => $otherId,
                'username' => $names[$otherId],
                'status' => $status,
                'since' => $since instanceof UTCDateTime ? $since->toDateTime()->format(DATE_ATOM) : null,
            ];
        }
        return $list;
    }

    /**
     * A friend's saved books and reviews; null unless the two are friends.
     *
     * @return array{username: string, saved: list<array<string, mixed>>, reviews: list<array<string, mixed>>}|null
     */
    public function library(string $userId, string $friendId): ?array
    {
        if (!$this->friendships->areFriends($userId, $friendId)) {
            return null;
        }
        $friend = $this->users->getUserById($friendId);
        if ($friend === null) {
            return null;
        }

        $saved = [];
        foreach (MongoHelper::toArray($friend['savedBooks'] ?? []) as $bookId) {
            $book = $this->books->getBookDetails((string) $bookId);
            // Drafts stay hidden: a friend sees what any reader could open.
            if ($book && ($book['status'] ?? null) === 'public') {
                BookDisplayHelper::applyThumbnailForApi($book);
                $saved[] = $book;
            }
        }

        $reviews = [];
        foreach ($this->books->reviewedBy($friendId) as $book) {
            // The $elemMatch projection leaves only this user's review (a BSON document, not cast by find()).
            $first = $book['reviews'][0] ?? null;
            $review = is_array($first) || is_object($first) ? (array) $first : [];
            $reviews[] = [
                'book_id' => (string) $book['_id'],
                'title' => $book['title'] ?? '',
                'author' => $book['author'] ?? '',
                'thumbnail' => BookDisplayHelper::thumbnailUrl($book),
                'rating' => (int) ($review['rating'] ?? 0),
                'comment' => (string) ($review['comment'] ?? ''),
                'created_at' => $review['created_at'] ?? null,
            ];
        }

        return ['username' => (string) ($friend['username'] ?? ''), 'saved' => $saved, 'reviews' => $reviews];
    }

    /**
     * @param array<string, mixed> $doc
     */
    private static function other(array $doc, string $userId): string
    {
        $users = MongoHelper::toArray($doc['users']);
        return (string) ($users[0] === $userId ? $users[1] : $users[0]);
    }

    private function username(string $userId): string
    {
        return (string) ($this->users->getUserById($userId)['username'] ?? 'Someone');
    }
}

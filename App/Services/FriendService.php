<?php

namespace App\Services;

use App\Helpers\BookDisplayHelper;
use App\Helpers\Database\MongoHelper;
use App\Models\Books;
use App\Models\Friendships;
use App\Models\Users;
use MongoDB\BSON\UTCDateTime;

/**
 * Friend requests between readers. Once both have accepted, each can see what the other chose to share
 * (saved books, reviews, current loans, downloads — one switch each, see SHARING_DEFAULTS).
 */
class FriendService
{
    /**
     * What a reader shares with friends until they change it. Saved books and reviews are on, so friendships
     * made before the switches existed see what they always saw (reviews are public on book pages anyway);
     * reading activity (loans, downloads) and notes — written as private until the switch existed — are off.
     * Notes are shown only inside the reader (AnnotationService::listForFriends), never on the friend page.
     */
    /** What BookCard.vue shows; the projection for books on a friend's page. */
    private const BOOK_CARD_FIELDS = ['title' => 1, 'author' => 1, 'year' => 1, 'categories' => 1];

    public const SHARING_DEFAULTS = ['saved' => true, 'reviews' => true, 'loans' => false, 'downloads' => false, 'notes' => false];

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
     * The user's sharing switches, with defaults for any they never set.
     *
     * @param array<string, mixed> $user
     * @return array{saved: bool, reviews: bool, loans: bool, downloads: bool, notes: bool}
     */
    public static function sharing(array $user): array
    {
        $set = $user['sharing'] ?? [];
        $set = is_array($set) || is_object($set) ? (array) $set : [];
        $sharing = self::SHARING_DEFAULTS;
        foreach ($sharing as $key => $default) {
            if (is_bool($set[$key] ?? null)) {
                $sharing[$key] = $set[$key];
            }
        }
        return $sharing;
    }

    /**
     * @return array{saved: bool, reviews: bool, loans: bool, downloads: bool, notes: bool}|null Null if the user is gone
     */
    public function getSharing(string $userId): ?array
    {
        $user = $this->users->getUserById($userId);
        return $user === null ? null : self::sharing($user);
    }

    /**
     * Change some of the user's switches; unknown keys and non-booleans are ignored.
     *
     * @param array<string, mixed> $changes
     * @return array{saved: bool, reviews: bool, loans: bool, downloads: bool, notes: bool}|null Null if the user is gone
     */
    public function setSharing(string $userId, array $changes): ?array
    {
        $set = [];
        foreach (array_keys(self::SHARING_DEFAULTS) as $key) {
            if (is_bool($changes[$key] ?? null)) {
                $set["sharing.$key"] = $changes[$key];
            }
        }
        if ($set !== []) {
            $this->users->updateById($userId, $set);
        }
        return $this->getSharing($userId);
    }

    /**
     * The user's friends who share $section with friends, as id => username.
     *
     * @param key-of<self::SHARING_DEFAULTS> $section
     * @return array<string, string>
     */
    public function friendsSharing(string $userId, string $section): array
    {
        $friendIds = [];
        foreach ($this->friendships->forUser($userId) as $doc) {
            if ($doc['status'] === 'accepted') {
                $friendIds[] = self::other($doc, $userId);
            }
        }
        if ($friendIds === []) {
            return [];
        }
        $sharing = [];
        foreach (
            $this->users->findAll(
                ['_id' => ['$in' => array_map([MongoHelper::class, 'createObjectId'], $friendIds)]],
                ['projection' => ['username' => 1, 'sharing' => 1], 'limit' => 0]
            ) as $friend
        ) {
            if (self::sharing($friend)[$section]) {
                $sharing[(string) $friend['_id']] = (string) ($friend['username'] ?? '');
            }
        }
        return $sharing;
    }

    /**
     * What a friend shares; null unless the two are friends. A section they don't share is null (not []),
     * so the page can say "not shared" rather than "nothing here".
     *
     * @return array{
     *     username: string,
     *     saved: list<array<string, mixed>>|null,
     *     reviews: list<array<string, mixed>>|null,
     *     loans: list<array<string, mixed>>|null,
     *     downloads: list<array<string, mixed>>|null
     * }|null
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
        $sharing = self::sharing($friend);

        return [
            'username' => (string) ($friend['username'] ?? ''),
            'saved' => $sharing['saved'] ? $this->publicBooks(MongoHelper::toArray($friend['savedBooks'] ?? [])) : null,
            'reviews' => $sharing['reviews'] ? $this->reviews($friendId) : null,
            'loans' => $sharing['loans'] ? $this->publicBooks(array_column(array_filter(
                (new BookService())->loanHistory($friendId),
                // Books they have right now — not reservations or past loans.
                fn ($l) => in_array($l['status'], ['active', 'out', 'overdue'], true)
            ), 'book_id')) : null,
            'downloads' => $sharing['downloads'] ? $this->publicBooks(MongoHelper::toArray($friend['downloadedBooks'] ?? [])) : null,
        ];
    }

    /**
     * The public ones among these books, in the given order, in one query, with only what a book card shows:
     * never storage paths, other readers' embedded reviews or the indexer's state (as the catalog strips).
     * Drafts stay hidden: a friend sees what any reader could open.
     *
     * @param array<int, mixed> $bookIds
     * @return list<array<string, mixed>>
     */
    private function publicBooks(array $bookIds): array
    {
        $ids = array_values(array_unique(array_filter(
            array_map('strval', $bookIds),
            fn ($id) => preg_match('/^[0-9a-f]{24}$/', $id) === 1
        )));
        if ($ids === []) {
            return [];
        }
        $byId = [];
        foreach (
            $this->books->findAll(
                ['_id' => ['$in' => array_map([MongoHelper::class, 'createObjectId'], $ids)], 'status' => 'public'],
                ['projection' => self::BOOK_CARD_FIELDS, 'limit' => 0]
            ) as $book
        ) {
            BookDisplayHelper::applyThumbnailForApi($book);
            $byId[BookDisplayHelper::bookIdString($book)] = $book;
        }
        return array_values(array_filter(array_map(fn ($id) => $byId[$id] ?? null, $ids)));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function reviews(string $userId): array
    {
        $reviews = [];
        foreach ($this->books->reviewedBy($userId) as $book) {
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
        return $reviews;
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

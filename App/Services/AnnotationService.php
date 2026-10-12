<?php

namespace App\Services;

use App\Models\Annotations;
use InvalidArgumentException;
use MongoDB\BSON\UTCDateTime;

/**
 * Per-user highlights and notes. The user id always comes from the authenticated request, never from
 * client input. Friends who share their notes are listed read-only by listForFriends().
 */
class AnnotationService
{
    private Annotations $annotations;

    public function __construct()
    {
        $this->annotations = new Annotations();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForBook(string $userId, string $bookId): array
    {
        return array_map([$this, 'toClient'], $this->annotations->findForUserAndBook($userId, $bookId));
    }

    /**
     * Notes and highlights on this book by the user's friends who share their notes, each with its author.
     * The caller must already have checked the user may read the book (quotes are the book's text).
     *
     * @return list<array<string, mixed>>
     */
    public function listForFriends(string $userId, string $bookId): array
    {
        $friends = (new FriendService())->friendsSharing($userId, 'notes');
        return array_map(
            fn ($doc) => $this->toClient($doc) + [
                'user_id' => (string) $doc['user_id'],
                'username' => $friends[(string) $doc['user_id']] ?? '',
            ],
            $this->annotations->listForUsersAndBook(array_map('strval', array_keys($friends)), $bookId)
        );
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed> the created annotation
     * @throws InvalidArgumentException with a JSON map of field errors
     */
    public function create(string $userId, string $bookId, array $input): array
    {
        $rects = $input['rects'] ?? [];
        $doc = [
            'user_id' => $userId,
            'book_id' => $bookId,
            'page' => $input['page'] ?? null,
            'rects' => $rects,
            'quote' => $this->trimmed($input['quote'] ?? ''),
            'note' => $this->trimmed($input['note'] ?? ''),
            'color' => $rects === [] ? null : ($input['color'] ?? null),
            'created_at' => new UTCDateTime(),
        ];

        $result = $this->annotations->create($doc);
        if (empty($result['insertedId'])) {
            throw new \RuntimeException('Could not save annotation');
        }
        $doc['_id'] = $result['insertedId'];

        return $this->toClient($doc);
    }

    /**
     * Only the note and colour can change; the highlighted passage is fixed.
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>|null the updated annotation, or null if it isn't this user's
     * @throws InvalidArgumentException with a JSON map of field errors
     */
    public function update(string $userId, string $id, array $input): ?array
    {
        $existing = $this->annotations->findOwned($id, $userId);
        if ($existing === null) {
            return null;
        }

        $fields = [];
        if (array_key_exists('note', $input)) {
            $fields['note'] = $this->trimmed($input['note']);
        }
        if (array_key_exists('color', $input) && !empty($existing['rects'])) {
            $fields['color'] = $input['color'];
        }

        $merged = array_merge($existing, $fields, ['rects' => $this->plainRects($existing['rects'] ?? [])]);
        $errors = $this->annotations->validate($merged);
        if (!empty($errors)) {
            throw new InvalidArgumentException((string) json_encode($errors));
        }

        if ($fields !== []) {
            $this->annotations->updateOwned($id, $userId, $fields);
        }

        return $this->toClient($merged);
    }

    /** Non-strings pass through untouched so validation rejects them instead of storing "Array". */
    private function trimmed(mixed $value): mixed
    {
        return is_string($value) ? trim($value) : $value;
    }

    public function delete(string $userId, string $id): bool
    {
        return ($this->annotations->deleteOwned($id, $userId)['deletedCount'] ?? 0) > 0;
    }

    /**
     * BSON arrays from the driver come back as objects; validation and JSON want plain arrays.
     *
     * @param iterable<iterable<int|float>> $rects
     * @return list<list<float>>
     */
    private function plainRects(iterable $rects): array
    {
        return array_map(
            fn (iterable $r) => array_map('floatval', array_values(iterator_to_array($r))),
            array_values(iterator_to_array($rects))
        );
    }

    /**
     * @param array<string, mixed> $doc
     * @return array<string, mixed>
     */
    private function toClient(array $doc): array
    {
        $createdAt = $doc['created_at'] ?? null;

        return [
            'id' => (string) $doc['_id'],
            'page' => (int) $doc['page'],
            'rects' => $this->plainRects($doc['rects'] ?? []),
            'quote' => (string) ($doc['quote'] ?? ''),
            'note' => (string) ($doc['note'] ?? ''),
            'color' => $doc['color'] ?? null,
            'created_at' => $createdAt instanceof UTCDateTime ? $createdAt->toDateTime()->format(DATE_ATOM) : null,
        ];
    }
}

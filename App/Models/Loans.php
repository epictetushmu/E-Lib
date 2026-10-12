<?php

namespace App\Models;

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Driver\Exception\ServerException;

/**
 * Loans of books lent with a fixed number of copies — PDFs read online and physical books on the shelf.
 *
 * Each copy is a slot document with _id "<bookId>:<n>" holding `free_at`, the moment the copy is free
 * again. The copy limit is enforced by the unique _id index alone: taking a copy is one upsert that only
 * matches a slot whose `free_at` has passed, and fails with a duplicate-key error while the slot is held.
 * - PDF loan:          free_at = due_at, so it ends by itself.
 * - Hold:              free_at = pickup deadline, due_at = null. A physical reservation, or a copy kept
 *                      for the head of the waitlist (PDF too); not taken up in time → the copy frees itself.
 * - Physical loan:     free_at = never, until a librarian checks it in. Past due_at it's overdue.
 * Slots are reused, so every loan is also a LoanHistory document (slot.history_id), the record
 * admins and readers see. When every copy is out, readers queue in Waitlist (_id "<bookId>:<userId>").
 */
class Loans extends BaseModel
{
    protected string $collection = 'Loans';

    public const PICKUP_DAYS = 3;
    public const DIGITAL_HOLD_DAYS = 1;
    public const MAX_RENEWALS = 2;

    private const HISTORY = 'LoanHistory';
    private const WAITLIST = 'Waitlist';
    private const DUPLICATE_KEY = 11000;
    private const NEVER = 253402300799000; // 9999-12-31, ms

    /**
     * The user's current hold on the book (PDF loan, reservation or physical loan), if any.
     *
     * @return array<string, mixed>|null
     */
    public function activeFor(string $bookId, string $userId): ?array
    {
        return $this->db->findOne($this->collection, [
            'book_id' => $bookId,
            'user_id' => $userId,
            'free_at' => ['$gt' => new UTCDateTime()],
        ]);
    }

    public function activeCount(string $bookId): int
    {
        return count($this->findAll(
            ['book_id' => $bookId, 'free_at' => ['$gt' => new UTCDateTime()]],
            ['projection' => ['_id' => 1], 'limit' => 0]
        ));
    }

    /**
     * PDF loan for $days days. Already holding one returns that loan instead of taking a second copy.
     *
     * @return array<string, mixed>|null The slot, or null when every copy is out
     */
    public function borrow(string $bookId, string $userId, int $copies, int $days): ?array
    {
        $due = self::inDays($days);
        $existing = $this->activeFor($bookId, $userId);
        if ($existing !== null && $existing['due_at'] === null) {
            // A copy held for them off the waitlist: start the loan on it.
            $this->db->update($this->collection, ['_id' => $existing['_id']], ['$set' => ['free_at' => $due, 'due_at' => $due]]);
            $this->setHistory((string) $existing['history_id'], ['borrowed_at' => new UTCDateTime(), 'due_at' => $due]);
            return $this->activeFor($bookId, $userId);
        }
        if ($existing !== null) {
            return $existing;
        }
        return $this->take($bookId, $userId, $copies, ['free_at' => $due, 'due_at' => $due], [
            'kind' => 'digital', 'borrowed_at' => new UTCDateTime(), 'due_at' => $due,
        ]);
    }

    /**
     * Hold a copy for the user: a physical reservation, or (PDF) a copy kept for the head of the waitlist.
     *
     * @param 'physical'|'digital' $kind
     * @return array<string, mixed>|null The slot, or null when every copy is out
     */
    public function reserve(string $bookId, string $userId, int $copies, string $kind = 'physical'): ?array
    {
        $existing = $this->activeFor($bookId, $userId);
        if ($existing !== null) {
            return $existing;
        }
        $pickupBy = self::inDays($kind === 'physical' ? self::PICKUP_DAYS : self::DIGITAL_HOLD_DAYS);
        return $this->take($bookId, $userId, $copies, ['free_at' => $pickupBy, 'due_at' => null], [
            'kind' => $kind, 'reserved_at' => new UTCDateTime(), 'pickup_by' => $pickupBy, 'due_at' => null,
        ]);
    }

    /**
     * Extend the user's loan to $days from now. Fails (with the reason) past MAX_RENEWALS, or when
     * overdue unless $allowOverdue (librarian).
     *
     * @return array<string, mixed>|string The updated history document, or why it can't be renewed
     */
    public function renew(string $bookId, string $userId, int $days, bool $allowOverdue): array|string
    {
        $slot = $this->activeFor($bookId, $userId);
        $history = $slot === null || $slot['due_at'] === null ? null : $this->findHistory((string) $slot['history_id']);
        if ($slot === null || $history === null) {
            return 'There is no loan to renew';
        }
        if ((int) ($history['renewals'] ?? 0) >= self::MAX_RENEWALS) {
            return 'This loan has already been renewed ' . self::MAX_RENEWALS . ' times';
        }
        $currentDue = (int) (string) $slot['due_at'];
        if (!$allowOverdue && $currentDue < time() * 1000) {
            return 'Overdue books can only be renewed at the library desk';
        }
        $due = new UTCDateTime(max($currentDue, (time() + $days * 86400) * 1000));
        $physical = ($history['kind'] ?? 'digital') === 'physical';
        $this->db->update($this->collection, ['_id' => $slot['_id']], [
            '$set' => ['due_at' => $due] + ($physical ? [] : ['free_at' => $due]),
        ]);
        $this->db->update(self::HISTORY, ['_id' => $history['_id']], [
            '$set' => ['due_at' => $due, 'overdue_notified_at' => null, 'due_soon_notified_at' => null],
            '$inc' => ['renewals' => 1],
        ]);
        return $this->findHistory((string) $history['_id']) ?? 'There is no loan to renew';
    }

    // --- Waitlist ---

    public function joinWaitlist(string $bookId, string $userId): void
    {
        // Joining twice is a duplicate _id, which insert() reports instead of throwing: already in line.
        $this->db->insert(self::WAITLIST, [
            '_id' => "$bookId:$userId", 'book_id' => $bookId, 'user_id' => $userId, 'joined_at' => new UTCDateTime(),
        ]);
    }

    public function leaveWaitlist(string $bookId, string $userId): void
    {
        $this->db->delete(self::WAITLIST, ['_id' => "$bookId:$userId"]);
    }

    /**
     * @return list<array<string, mixed>> Oldest first
     */
    public function waitlist(string $bookId): array
    {
        return $this->db->find(self::WAITLIST, ['book_id' => $bookId], ['sort' => ['joined_at' => 1], 'limit' => 0]);
    }

    /**
     * @return list<string> Books someone is waiting for
     */
    public function waitedForBookIds(): array
    {
        return array_map('strval', $this->db->distinct(self::WAITLIST, 'book_id'));
    }

    /**
     * Hand a physical copy over at the desk: turns the reader's reservation into a loan, or takes a free
     * copy for a walk-in. Null when they already have it out, or no copy is free.
     *
     * @return array<string, mixed>|null The loan's history document
     */
    public function checkOut(string $bookId, string $userId, int $copies, int $days): ?array
    {
        $due = self::inDays($days);
        $loan = ['borrowed_at' => new UTCDateTime(), 'due_at' => $due];
        $existing = $this->activeFor($bookId, $userId);
        if ($existing !== null) {
            if ($existing['due_at'] !== null) {
                return null;
            }
            $this->db->update($this->collection, ['_id' => $existing['_id']], [
                '$set' => ['free_at' => new UTCDateTime(self::NEVER), 'due_at' => $due],
            ]);
            return $this->setHistory((string) $existing['history_id'], $loan);
        }
        $slot = $this->take($bookId, $userId, $copies, ['free_at' => new UTCDateTime(self::NEVER), 'due_at' => $due], [
            'kind' => 'physical', 'pickup_by' => null,
        ] + $loan);
        return $slot === null ? null : $this->findHistory((string) $slot['history_id']);
    }

    /**
     * End the user's hold: a returned loan, or a cancelled reservation (no due date yet).
     *
     * @return array<string, mixed>|null The loan's history document, or null when they held nothing
     */
    public function returnBook(string $bookId, string $userId): ?array
    {
        $slot = $this->activeFor($bookId, $userId);
        if ($slot === null) {
            return null;
        }
        $this->db->delete($this->collection, ['_id' => $slot['_id']]);
        $field = $slot['due_at'] === null ? 'cancelled_at' : 'returned_at';
        return $this->setHistory((string) $slot['history_id'], [$field => new UTCDateTime()]);
    }

    /**
     * Loan records, newest first.
     *
     * @param array<string, mixed> $filter
     * @return list<array<string, mixed>>
     */
    public function history(array $filter, int $limit): array
    {
        return $this->db->find(self::HISTORY, $filter, ['sort' => ['_id' => -1], 'limit' => $limit]);
    }

    /**
     * Physical loans past their due date whose reader hasn't been told yet.
     *
     * @return list<array<string, mixed>>
     */
    public function overdueUnnotified(): array
    {
        return $this->history([
            'kind' => 'physical',
            'returned_at' => null,
            'due_at' => ['$ne' => null, '$lt' => new UTCDateTime()],
            'overdue_notified_at' => null,
        ], 0);
    }

    /**
     * Physical loans due within $days whose reader hasn't been reminded yet.
     *
     * @return list<array<string, mixed>>
     */
    public function dueSoonUnnotified(int $days): array
    {
        return $this->history([
            'kind' => 'physical',
            'returned_at' => null,
            'due_at' => ['$ne' => null, '$gte' => new UTCDateTime(), '$lt' => self::inDays($days)],
            'due_soon_notified_at' => null,
        ], 0);
    }

    /**
     * @param 'overdue_notified_at'|'due_soon_notified_at' $field
     */
    public function markNotified(string $historyId, string $field): void
    {
        $this->setHistory($historyId, [$field => new UTCDateTime()]);
    }

    public function deleteForBook(string $bookId): void
    {
        $this->db->delete($this->collection, ['book_id' => $bookId]);
        $this->db->delete(self::WAITLIST, ['book_id' => $bookId]);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, string>
     */
    public function validate(array $data): array
    {
        return [];
    }

    /**
     * Take the first free copy and open its history record.
     *
     * @param array<string, mixed> $slotFields
     * @param array<string, mixed> $historyFields
     * @return array<string, mixed>|null
     */
    private function take(string $bookId, string $userId, int $copies, array $slotFields, array $historyFields): ?array
    {
        $historyId = new ObjectId();
        $owner = ['book_id' => $bookId, 'user_id' => $userId];
        $now = new UTCDateTime();
        // ponytail: linear scan over slots, fine for the handful of copies a library lends
        for ($slot = 0; $slot < $copies; $slot++) {
            try {
                $taken = $this->db->findOneAndUpdate(
                    $this->collection,
                    ['_id' => "$bookId:$slot", 'free_at' => ['$lte' => $now]],
                    ['$set' => $owner + $slotFields + ['history_id' => (string) $historyId]],
                    ['upsert' => true]
                );
                if ($taken !== null) {
                    $this->db->insert(self::HISTORY, ['_id' => $historyId] + $owner + $historyFields + [
                        'returned_at' => null, 'cancelled_at' => null, 'renewals' => 0,
                        'overdue_notified_at' => null, 'due_soon_notified_at' => null,
                    ]);
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

    /**
     * @param array<string, mixed> $fields
     * @return array<string, mixed>|null
     */
    private function setHistory(string $historyId, array $fields): ?array
    {
        $this->db->update(self::HISTORY, ['_id' => new ObjectId($historyId)], ['$set' => $fields]);
        return $this->findHistory($historyId);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findHistory(string $historyId): ?array
    {
        return $this->db->findOne(self::HISTORY, ['_id' => new ObjectId($historyId)]);
    }

    private static function inDays(int $days): UTCDateTime
    {
        return new UTCDateTime((time() + $days * 86400) * 1000);
    }
}

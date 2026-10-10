<?php

namespace App\Models;

/**
 * A reader's private highlight or note on a book page.
 *
 * Every query here is scoped by user_id: annotations are never visible to, or
 * editable by, anyone but the user who made them (admins included).
 */
class Annotations extends BaseModel
{
    protected string $collection = 'Annotations';

    public const COLORS = ['saffron', 'olive', 'aegean', 'rose'];
    public const MAX_RECTS = 200;
    public const MAX_QUOTE = 2000;
    public const MAX_NOTE = 20000;

    /**
     * @return list<array<string, mixed>>
     */
    public function findForUserAndBook(string $userId, string $bookId): array
    {
        return $this->findAll(
            ['user_id' => $userId, 'book_id' => $bookId],
            ['limit' => 5000, 'sort' => ['page' => 1, 'created_at' => 1]]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findOwned(string $id, string $userId)
    {
        return $this->db->findOne($this->collection, ['_id' => $this->ensureId($id), 'user_id' => $userId]);
    }

    /**
     * @param array<string, mixed> $fields
     * @return array<string, mixed>
     */
    public function updateOwned(string $id, string $userId, array $fields): array
    {
        return $this->db->update(
            $this->collection,
            ['_id' => $this->ensureId($id), 'user_id' => $userId],
            ['$set' => $fields]
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function deleteOwned(string $id, string $userId): array
    {
        return $this->db->delete($this->collection, ['_id' => $this->ensureId($id), 'user_id' => $userId]);
    }

    /**
     * A highlight has rects (and a colour); a page note has no rects but must have note text.
     *
     * @param array<string, mixed> $data
     * @return array<string, string>
     */
    public function validate(array $data): array
    {
        $errors = [];

        foreach (['user_id', 'book_id'] as $field) {
            if (!is_string($data[$field] ?? null) || !preg_match('/^[0-9a-f]{24}$/', $data[$field])) {
                $errors[$field] = "Invalid $field";
            }
        }

        if (!is_int($data['page'] ?? null) || $data['page'] < 1) {
            $errors['page'] = 'Page must be a positive whole number';
        }

        $rects = $data['rects'] ?? [];
        if (!is_array($rects) || count($rects) > self::MAX_RECTS || !array_is_list($rects)) {
            $errors['rects'] = 'Invalid highlight area';
        } else {
            foreach ($rects as $rect) {
                if (!$this->isRect($rect)) {
                    $errors['rects'] = 'Invalid highlight area';
                    break;
                }
            }
        }

        $note = $data['note'] ?? '';
        if (!is_string($note) || mb_strlen($note) > self::MAX_NOTE) {
            $errors['note'] = 'Notes can be up to ' . self::MAX_NOTE . ' characters';
        }

        $quote = $data['quote'] ?? '';
        if (!is_string($quote) || mb_strlen($quote) > self::MAX_QUOTE) {
            $errors['quote'] = 'Highlighted text can be up to ' . self::MAX_QUOTE . ' characters';
        }

        if (empty($errors['rects']) && $rects === [] && trim(is_string($note) ? $note : '') === '') {
            $errors['note'] = 'A note needs some text';
        }

        if ($rects !== [] && !in_array($data['color'] ?? null, self::COLORS, true)) {
            $errors['color'] = 'Unknown highlight colour';
        }

        return $errors;
    }

    private function isRect(mixed $rect): bool
    {
        if (!is_array($rect) || count($rect) !== 4 || !array_is_list($rect)) {
            return false;
        }
        foreach ($rect as $value) {
            if (!is_int($value) && !is_float($value) || !is_finite((float) $value)) {
                return false;
            }
        }
        return true;
    }
}

<?php

namespace App\Helpers;

/**
 * Normalizes book presentation for UI/API (thumbnail URLs, IDs).
 */
class BookDisplayHelper
{
    /**
     * @param array<string, mixed> $book
     */
    public static function bookIdString(array $book): string
    {
        $id = $book['_id'] ?? null;
        if ($id instanceof \MongoDB\BSON\ObjectId) {
            return (string) $id;
        }
        if (is_array($id) && isset($id['$oid'])) {
            return (string) $id['$oid'];
        }
        return is_string($id) ? $id : '';
    }

    /**
     * Thumbnail URL that goes through PHP so legacy files (wrong disk path) still resolve.
     *
     * @param array<string, mixed> $book
     */
    public static function thumbnailUrl(array $book): string
    {
        $id = self::bookIdString($book);
        if ($id === '') {
            return '/assets/uploads/thumbnails/placeholder-book.jpg';
        }
        return '/api/v1/books/' . $id . '/thumbnail';
    }

    /**
     * @param array<string, mixed> $book
     */
    public static function applyThumbnailForApi(array &$book): void
    {
        $book['thumbnail'] = self::thumbnailUrl($book);
    }
}

<?php

namespace App\Models;

use MongoDB\BSON\Binary;

/**
 * One document per book page: {book_id, page, text, embedding, embedding_model, status}.
 * Kept out of Books so a book's full text and vectors never ride along with every Books query.
 * `status` is copied from the book so both searches can drop drafts *before* ranking — filtering
 * afterwards would let drafts crowd public books out of the top results.
 */
class BookChunks extends BaseModel
{
    public const COLLECTION = 'BookChunks';

    protected string $collection = self::COLLECTION;

    /** Atlas index names — created by scripts/db-setup.php. */
    public const TEXT_INDEX = 'chunks_text';
    public const VECTOR_INDEX = 'chunks_vector';

    private const SNIPPET_LENGTH = 300;

    /**
     * @param array<string, mixed> $data
     * @return array<string, string>
     */
    public function validate(array $data): array
    {
        return [];
    }

    /**
     * @param list<array<string, mixed>> $chunks
     */
    public function insertChunks(array $chunks): int
    {
        return $this->db->insertMany($this->collection, $chunks);
    }

    /**
     * Delete one model's chunks of a book after $afterPage — the batch a crashed worker wrote
     * before it could record its progress.
     *
     * @return array<string, mixed>
     */
    public function deleteUnfinished(string $bookId, string $model, int $afterPage): array
    {
        $filter = ['book_id' => $bookId, 'embedding_model' => $model, 'page' => ['$gt' => $afterPage]];
        return $this->db->delete($this->collection, $filter);
    }

    /**
     * Drop a book's chunks made by any other embedding model (left over after a model change).
     *
     * @return array<string, mixed>
     */
    public function deleteOtherModels(string $bookId, string $model): array
    {
        return $this->db->delete($this->collection, ['book_id' => $bookId, 'embedding_model' => ['$ne' => $model]]);
    }

    /**
     * @return array<string, mixed>
     */
    public function deleteForBook(string $bookId): array
    {
        return $this->db->delete($this->collection, ['book_id' => $bookId]);
    }

    /**
     * @return array<string, mixed>
     */
    public function setStatusForBook(string $bookId, string $status): array
    {
        return $this->db->update($this->collection, ['book_id' => $bookId], ['$set' => ['status' => $status]]);
    }

    /**
     * Pages containing the query's words (Atlas Search, BM25 with Greek/English stemming).
     *
     * @return list<array{book_id: string, page: int, snippet: string}>
     */
    public function keywordSearch(string $query, bool $publicOnly, int $limit): array
    {
        $search = [
            'index' => self::TEXT_INDEX,
            'compound' => [
                'must' => [[
                    'text' => [
                        'query' => $query,
                        'path' => ['text', ['value' => 'text', 'multi' => 'el'], ['value' => 'text', 'multi' => 'en']],
                    ],
                ]],
            ],
            'highlight' => ['path' => 'text', 'maxNumPassages' => 1],
        ];
        if ($publicOnly) {
            $search['compound']['filter'] = [['equals' => ['path' => 'status', 'value' => 'public']]];
        }

        $rows = $this->db->aggregate($this->collection, [
            ['$search' => $search],
            ['$limit' => $limit],
            ['$project' => [
                '_id' => 0,
                'book_id' => 1,
                'page' => 1,
                'highlights' => ['$meta' => 'searchHighlights'],
                'start' => ['$substrCP' => ['$text', 0, self::SNIPPET_LENGTH]],
            ]],
        ]);

        return array_map(fn (array $row) => [
            'book_id' => (string) $row['book_id'],
            'page' => (int) $row['page'],
            'snippet' => self::highlightPassage($row['highlights'] ?? []) ?? (string) $row['start'],
        ], $rows);
    }

    /**
     * Pages closest in meaning to the query vector (Atlas Vector Search), from the current model only —
     * vectors from different models aren't comparable. Vector search always returns *some* nearest
     * neighbours, however unrelated, so pages scoring below $minScore are dropped. Best first.
     *
     * @return list<array{book_id: string, page: int, snippet: string, similarity: float}>
     */
    public function vectorSearch(Binary $queryVector, string $model, bool $publicOnly, int $limit, float $minScore): array
    {
        $filter = ['embedding_model' => $model];
        if ($publicOnly) {
            $filter['status'] = 'public';
        }

        $rows = $this->db->aggregate($this->collection, [
            ['$vectorSearch' => [
                'index' => self::VECTOR_INDEX,
                'path' => 'embedding',
                'queryVector' => $queryVector,
                'numCandidates' => $limit * 10,
                'limit' => $limit,
                'filter' => $filter,
            ]],
            ['$project' => [
                '_id' => 0,
                'book_id' => 1,
                'page' => 1,
                'snippet' => ['$substrCP' => ['$text', 0, self::SNIPPET_LENGTH]],
                'score' => ['$meta' => 'vectorSearchScore'],
            ]],
            ['$match' => ['score' => ['$gte' => $minScore]]],
        ]);

        return array_map(fn (array $row) => [
            'book_id' => (string) $row['book_id'],
            'page' => (int) $row['page'],
            'snippet' => (string) $row['snippet'],
            'similarity' => (float) $row['score'],
        ], $rows);
    }

    /**
     * Plain-text passage around the best keyword hit, from Atlas Search's highlight output.
     *
     * @param mixed $highlights BSONArray from the driver (aggregate() only maps documents to arrays)
     */
    private static function highlightPassage(mixed $highlights): ?string
    {
        $highlights = json_decode((string) json_encode($highlights), true);
        $texts = $highlights[0]['texts'] ?? [];
        if ($texts === []) {
            return null;
        }
        return trim(implode('', array_map(fn ($t) => (string) $t['value'], $texts)));
    }
}

<?php

namespace App\Services;

use App\Helpers\FileHelper;
use App\Includes\Environment;
use App\Models\BookChunks;
use App\Models\Books;
use App\Helpers\Database\MongoHelper;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectException;
use MongoDB\BSON\Binary;
use MongoDB\BSON\UTCDateTime;
use MongoDB\BSON\VectorType;
use RuntimeException;

/**
 * "Search inside books": indexes each book's pages (text + an embedding from the self-hosted
 * embedder container) into BookChunks, and answers queries by fusing Atlas keyword search with
 * Atlas vector search. Indexing runs in scripts/index-worker.php, never in a web request.
 */
class ContentSearchService
{
    public const MAX_ATTEMPTS = 3;
    /** Renewed after every batch, so it only has to outlast one batch, not a whole book. */
    private const LEASE_SECONDS = 300;
    /** Reciprocal Rank Fusion constant — 60 is the standard value from the original RRF paper. */
    private const RRF_K = 60;
    /** Pages fetched from each search before fusing. */
    private const CANDIDATES = 50;
    private const PAGES_PER_BOOK = 3;

    private Books $books;
    private BookChunks $chunks;
    private FileHelper $files;
    private ?ClientInterface $http = null;

    public function __construct()
    {
        $this->books = new Books();
        $this->chunks = new BookChunks();
        $this->files = new FileHelper();
    }

    /**
     * The Ollama model tag to embed with — also stored on every vector. Changing it makes the worker
     * re-embed every book in the background; vectors from different models never mix.
     */
    public static function model(): string
    {
        return (string) Environment::get('EMBEDDING_MODEL', 'bge-m3:567m');
    }

    /**
     * Claim and index one book. Returns false when there was nothing to do.
     *
     * @throws ConnectException When the embedder is unreachable (the book is released, not failed)
     */
    public function indexNextBook(): bool
    {
        $model = self::model();
        $book = $this->books->claimNextForIndexing($model, self::LEASE_SECONDS, self::MAX_ATTEMPTS);
        if ($book === null) {
            return false;
        }

        $id = (string) $book['_id'];
        try {
            $this->indexBook($id, $book, $model);
        } catch (ConnectException $e) {
            // The embedder being down says nothing about this book — don't burn its attempts.
            $this->books->updateIndexState($id, ['index_status' => 'pending']);
            throw $e;
        } catch (\Throwable $e) {
            $attempts = (int) ($book['index_attempts'] ?? 0) + 1;
            error_log("Indexing book $id failed (attempt $attempts): " . $e->getMessage());
            $this->books->updateIndexState($id, [
                'index_status' => 'failed',
                'index_error' => mb_substr($e->getMessage(), 0, 500),
                // Back off 2, 4, 8... minutes between attempts.
                'index_retry_at' => new UTCDateTime((time() + 60 * 2 ** $attempts) * 1000),
            ], ['index_attempts' => 1]);
        }
        return true;
    }

    /**
     * @param array<string, mixed> $book
     */
    private function indexBook(string $id, array $book, string $model): void
    {
        // Resume where a crashed/stopped worker left off — unless the model changed, which starts over.
        $resumeAfter = ($book['index_model'] ?? null) === $model ? (int) ($book['index_progress'] ?? 0) : 0;
        if ($resumeAfter === 0) {
            $this->books->updateIndexState($id, ['index_model' => $model, 'index_progress' => 0]);
        }
        $this->chunks->deleteUnfinished($id, $model, $resumeAfter);

        $pages = $this->files->extractPageTexts($this->absolutePath((string) $book['file_path']));
        $status = (string) ($book['status'] ?? 'draft');
        $batchSize = max(1, (int) Environment::get('WORKER_BATCH_SIZE', 16));

        $batch = [];
        foreach ($pages as $i => $text) {
            $page = $i + 1;
            if ($page <= $resumeAfter || $text === '') {
                continue;
            }
            $batch[$page] = $text;
            if (count($batch) === $batchSize) {
                $this->writeBatch($id, $batch, $model, $status);
                $batch = [];
            }
        }
        if ($batch !== []) {
            $this->writeBatch($id, $batch, $model, $status);
        }

        // The book may have been deleted or re-statused while we worked.
        $current = $this->books->getBookDetails($id);
        if ($current === null) {
            $this->chunks->deleteUnfinished($id, $model, 0);
            return;
        }
        $this->chunks->setStatusForBook($id, (string) ($current['status'] ?? 'draft'));
        $this->chunks->deleteOtherModels($id, $model);

        $hasText = array_filter($pages, fn (string $text) => $text !== '') !== [];
        $this->books->updateIndexState($id, [
            // no_text = scanned PDF without a text layer; needs OCR, which isn't built yet.
            'index_status' => $hasText ? 'done' : 'no_text',
            'index_pages' => count($pages),
            'index_attempts' => 0,
            'index_error' => null,
            'index_lease_until' => null,
            'indexed_at' => new UTCDateTime(),
        ]);
    }

    /**
     * @param array<int, string> $batch page number => text
     */
    private function writeBatch(string $bookId, array $batch, string $model, string $status): void
    {
        $vectors = $this->embed(array_values($batch));
        $docs = [];
        foreach (array_keys($batch) as $i => $page) {
            $docs[] = [
                'book_id' => $bookId,
                'page' => $page,
                'text' => $batch[$page],
                'embedding' => self::toVector($vectors[$i]),
                'embedding_model' => $model,
                'status' => $status,
            ];
        }
        $this->chunks->insertChunks($docs);
        // Progress is only recorded once the batch is safely stored — the resume point is never ahead of the data.
        $this->books->updateIndexState($bookId, [
            'index_progress' => array_key_last($batch),
            'index_lease_until' => new UTCDateTime((time() + self::LEASE_SECONDS) * 1000),
        ]);
    }

    /**
     * Books whose pages match the query by words or by meaning, best first, each with its best pages.
     * If the embedder is down or slow, this degrades to keyword-only results instead of failing.
     *
     * @return list<array{book: array<string, mixed>, matches: list<array{page: int, snippet: string}>}>
     */
    public function search(string $query, bool $publicOnly, int $limit = 20): array
    {
        $keyword = $this->chunks->keywordSearch($query, $publicOnly, self::CANDIDATES);

        $semantic = [];
        try {
            $timeout = (float) Environment::get('EMBEDDING_TIMEOUT', 2);
            $vector = self::toVector($this->embed([$query], $timeout)[0]);
            // ponytail: fixed cutoff (Atlas cosine score, 0..1) calibrated on a small test set — bge-m3 put
            // relevant pages at ~0.75-0.85 and unrelated ones at ~0.60-0.70. Re-tune on the real library.
            $minScore = (float) Environment::get('SEMANTIC_MIN_SCORE', 0.70);
            $semantic = $this->chunks->vectorSearch($vector, self::model(), $publicOnly, self::CANDIDATES, $minScore);
        } catch (\Throwable $e) {
            error_log('Content search fell back to keyword-only: ' . $e->getMessage());
        }

        $ranked = self::fuse([$keyword, $semantic], $limit);
        if ($ranked === []) {
            return [];
        }

        $ids = array_map(fn (array $r) => MongoHelper::createObjectId($r['book_id']), $ranked);
        $books = [];
        foreach ($this->books->findAll(['_id' => ['$in' => $ids]]) as $book) {
            $books[(string) $book['_id']] = $book;
        }

        $results = [];
        foreach ($ranked as $r) {
            $book = $books[$r['book_id']] ?? null;
            // Chunk status can briefly lag a status change; the book itself is the source of truth.
            if ($book === null || ($publicOnly && ($book['status'] ?? null) !== 'public')) {
                continue;
            }
            $results[] = ['book' => $book, 'matches' => $r['matches']];
        }
        return $results;
    }

    /**
     * Reciprocal Rank Fusion: each page scores sum(1 / (k + rank)) over the lists it appears in, so
     * pages ranked well by both keyword and semantic search rise to the top. Raw scores from the two
     * searches aren't comparable, ranks are. Books are ordered by their best page; the first list's
     * snippet wins when a page is in both (keyword snippets are centred on the hit).
     *
     * @param list<list<array{book_id: string, page: int, snippet: string}>> $lists
     * @return list<array{book_id: string, score: float, matches: list<array{page: int, snippet: string}>}>
     */
    public static function fuse(array $lists, int $limit): array
    {
        $pages = [];
        foreach ($lists as $hits) {
            foreach (array_values($hits) as $rank => $hit) {
                $key = $hit['book_id'] . ':' . $hit['page'];
                $pages[$key] ??= $hit + ['score' => 0.0];
                $pages[$key]['score'] += 1 / (self::RRF_K + $rank + 1);
            }
        }
        usort($pages, fn (array $a, array $b) => $b['score'] <=> $a['score']);

        $books = [];
        foreach ($pages as $page) {
            $id = $page['book_id'];
            if (!isset($books[$id])) {
                if (count($books) >= $limit) {
                    continue;
                }
                $books[$id] = ['book_id' => $id, 'score' => $page['score'], 'matches' => []];
            }
            if (count($books[$id]['matches']) < self::PAGES_PER_BOOK) {
                $books[$id]['matches'][] = ['page' => $page['page'], 'snippet' => $page['snippet']];
            }
        }
        return array_values($books);
    }

    /**
     * Embeddings for $texts from the embedder container (Ollama's /api/embed, L2-normalized).
     *
     * @param list<string> $texts
     * @return list<list<float>>
     */
    public function embed(array $texts, float $timeout = 120.0): array
    {
        $response = $this->http()->request('POST', '/api/embed', [
            'json' => ['model' => self::model(), 'input' => $texts, 'truncate' => true],
            'timeout' => $timeout,
            'connect_timeout' => min($timeout, 5.0),
        ]);
        $vectors = json_decode((string) $response->getBody(), true)['embeddings'] ?? null;
        if (!is_array($vectors) || count($vectors) !== count($texts)) {
            throw new RuntimeException('Unexpected response from the embedder');
        }
        return $vectors;
    }

    /**
     * Store a vector as int8 (1 byte per dimension instead of 8 as a BSON double array — matters on
     * Atlas M0's 512MB). Each vector is scaled by its own largest component before rounding: cosine
     * similarity ignores per-vector scale, so this keeps nearly full precision, whereas a fixed
     * x*127 would round most of a normalized 1024-dim vector's small components to zero.
     *
     * @param list<float> $vector
     */
    public static function toVector(array $vector): Binary
    {
        $max = max(array_map('abs', $vector)) ?: 1.0;
        return Binary::fromVector(
            array_map(fn (float $x) => (int) round($x / $max * 127), $vector),
            VectorType::Int8
        );
    }

    private function http(): ClientInterface
    {
        return $this->http ??= new Client([
            'base_uri' => rtrim((string) Environment::get('EMBEDDING_URL', 'http://embedder:11434'), '/'),
        ]);
    }

    /**
     * Absolute path of a stored book file from its web path (/assets/uploads/documents/...), refusing
     * anything that resolves outside the uploads directory.
     */
    private function absolutePath(string $webPath): string
    {
        $uploads = realpath(dirname(__DIR__, 2) . '/public/assets/uploads');
        $path = realpath(dirname(__DIR__, 2) . '/public' . $webPath);
        if ($uploads === false || $path === false || !str_starts_with($path, $uploads . DIRECTORY_SEPARATOR)) {
            // No path in the message: it's stored on the book as index_error, and book records are
            // returned by public endpoints that deliberately hide file_path.
            error_log("Content indexing: book file missing or outside uploads: $webPath");
            throw new RuntimeException('Book file not found');
        }
        return $path;
    }
}

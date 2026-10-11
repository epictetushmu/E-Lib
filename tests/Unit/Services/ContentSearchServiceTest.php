<?php

namespace Tests\Unit\Services;

use App\Helpers\FileHelper;
use App\Models\BookChunks;
use App\Models\Books;
use App\Services\ContentSearchService;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Tests\Support\ServiceTestCase;

class ContentSearchServiceTest extends ServiceTestCase
{
    private const BOOK_ID = '507f1f77bcf86cd799439011';
    /** Any real file under public/assets/uploads — FileHelper is mocked, only the path check runs. */
    private const FILE = '/assets/uploads/thumbnails/placeholder-book.jpg';

    /** @var list<array{string, mixed}> */
    private array $embedRequests = [];

    /**
     * @param list<Response|\Throwable> $embedderResponses
     */
    private function service(Books $books, ?BookChunks $chunks = null, ?FileHelper $files = null, array $embedderResponses = []): ContentSearchService
    {
        /** @var ContentSearchService $service */
        $service = $this->makeService(ContentSearchService::class, 'books', $books);
        $this->inject($service, 'chunks', $chunks ?? $this->createMock(BookChunks::class));
        $this->inject($service, 'files', $files ?? $this->createMock(FileHelper::class));

        $stack = HandlerStack::create(new MockHandler($embedderResponses));
        $stack->push(function (callable $handler) {
            return function ($request, array $options) use ($handler) {
                $this->embedRequests[] = json_decode((string) $request->getBody(), true);
                return $handler($request, $options);
            };
        });
        $this->inject($service, 'http', new Client(['handler' => $stack]));
        return $service;
    }

    /** One fake 4-dim vector per input text. */
    private static function vectors(int $count): Response
    {
        return new Response(200, [], (string) json_encode(['embeddings' => array_fill(0, $count, [0.03, -0.05, 0.01, 0.0])]));
    }

    /**
     * @param list<string> $pages
     */
    private function files(array $pages): FileHelper
    {
        $files = $this->createMock(FileHelper::class);
        $files->method('extractPageTexts')->willReturn($pages);
        return $files;
    }

    /**
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    private static function book(array $extra = []): array
    {
        return $extra + ['_id' => self::BOOK_ID, 'file_path' => self::FILE, 'status' => 'public'];
    }

    // --- fuse() ---

    public function testFuseRanksPagesFoundByBothSearchesFirst(): void
    {
        $keyword = [
            ['book_id' => 'a', 'page' => 1, 'snippet' => 'kw a1'],
            ['book_id' => 'b', 'page' => 7, 'snippet' => 'kw b7'],
        ];
        $semantic = [
            ['book_id' => 'c', 'page' => 2, 'snippet' => 'sem c2'],
            ['book_id' => 'b', 'page' => 7, 'snippet' => 'sem b7'],
        ];

        $result = ContentSearchService::fuse([$keyword, $semantic], 10);

        $this->assertSame(['b', 'a', 'c'], array_column($result, 'book_id'));
        // The keyword snippet (centred on the hit) wins when both searches found the page.
        $this->assertSame([['page' => 7, 'snippet' => 'kw b7']], $result[0]['matches']);
    }

    public function testFuseGroupsPagesPerBookCappedAtThreeBestFirst(): void
    {
        $hits = [];
        foreach ([4, 9, 2, 5, 1] as $page) {
            $hits[] = ['book_id' => 'a', 'page' => $page, 'snippet' => "p$page"];
        }

        $result = ContentSearchService::fuse([$hits], 10);

        $this->assertCount(1, $result);
        $this->assertSame([4, 9, 2], array_column($result[0]['matches'], 'page'));
    }

    public function testFuseLimitsTheNumberOfBooks(): void
    {
        $hits = [];
        foreach (['a', 'b', 'c'] as $id) {
            $hits[] = ['book_id' => $id, 'page' => 1, 'snippet' => ''];
        }

        $this->assertSame(['a', 'b'], array_column(ContentSearchService::fuse([$hits], 2), 'book_id'));
    }

    // --- toVector() ---

    public function testToVectorScalesEachVectorToTheFullInt8Range(): void
    {
        // A naive round(x * 127) would turn all of these into 0, 0, 0 (or ±1, ±6).
        $vector = ContentSearchService::toVector([0.03, -0.05, 0.01, 0.0])->toArray();

        $this->assertSame([76, -127, 25, 0], $vector);
    }

    // --- isNavigationPage() ---

    public function testIsNavigationPageDetectsContentsAndIndexPages(): void
    {
        $contents = "Contents\n" . str_repeat("Section 1.1: A sample algorithmic problem ........................ 2\n", 9);
        $greekContents = "ΠΕΡΙΕΧΟΜΕΝΑ\nxi\n" . str_repeat("1.2 Ιεραρχία Chomsky . . . . . . . . . . . .\n3\n", 8);
        $index = "INDEX\n" . str_repeat("cholesterol 454, 457\nclients, freelance 71–72\n", 5);

        $this->assertTrue(ContentSearchService::isNavigationPage($contents));
        $this->assertTrue(ContentSearchService::isNavigationPage($greekContents));
        $this->assertTrue(ContentSearchService::isNavigationPage($index));
    }

    public function testIsNavigationPageKeepsProseAndProgramOutput(): void
    {
        $prose = str_repeat("Depth-first search explores as far as possible along each branch before backtracking.\n", 10);
        // Lines ending in numbers, but mostly bare numbers — output, not a contents page.
        $output = "Expected output of the program is\nVertex\n0\n1\n2\n3\n4\n5\n6\n7\n8\nDistance from Source\n0\n4\n12\n";
        $short = "Chapter 1 ..... 2\nChapter 2 ..... 5\n";

        $this->assertFalse(ContentSearchService::isNavigationPage($prose));
        $this->assertFalse(ContentSearchService::isNavigationPage($output));
        $this->assertFalse(ContentSearchService::isNavigationPage($short));
    }

    // --- indexNextBook() ---

    public function testIndexNextBookReturnsFalseWhenNothingNeedsIndexing(): void
    {
        $books = $this->createMock(Books::class);
        $books->method('claimNextForIndexing')->willReturn(null);

        $this->assertFalse($this->service($books)->indexNextBook());
    }

    public function testIndexNextBookStoresNonEmptyPagesInBatchesAndMarksDone(): void
    {
        putenv('WORKER_BATCH_SIZE=2');
        $books = $this->createMock(Books::class);
        $books->method('claimNextForIndexing')->willReturn(self::book());
        $books->method('getBookDetails')->willReturn(self::book(['status' => 'draft']));
        $states = [];
        $books->method('updateIndexState')->willReturnCallback(function (string $id, array $set) use (&$states) {
            $states[] = $set;
            return [];
        });

        $chunks = $this->createMock(BookChunks::class);
        $inserted = [];
        $chunks->method('insertChunks')->willReturnCallback(function (array $docs) use (&$inserted) {
            $inserted[] = $docs;
            return count($docs);
        });
        // Re-read status wins over the one the chunks were written with.
        $chunks->expects($this->once())->method('setStatusForBook')->with(self::BOOK_ID, 'draft');
        $chunks->expects($this->once())->method('deleteOtherModels');

        $service = $this->service($books, $chunks, $this->files(['one', '', 'three', 'four']), [self::vectors(2), self::vectors(1)]);
        $this->assertTrue($service->indexNextBook());
        putenv('WORKER_BATCH_SIZE');

        // Page 2 is blank (e.g. a scanned image) and skipped, but numbering stays aligned with the PDF.
        $this->assertSame([[1, 3], [4]], array_map(fn ($batch) => array_column($batch, 'page'), $inserted));
        $this->assertSame(['one', 'three'], $this->embedRequests[0]['input']);
        $this->assertSame(ContentSearchService::model(), $this->embedRequests[0]['model']);
        $this->assertSame('public', $inserted[0][0]['status']);
        $this->assertSame(self::BOOK_ID, $inserted[0][0]['book_id']);
        $this->assertSame(3, $states[1]['index_progress']);
        $last = end($states);
        $this->assertSame('done', $last['index_status']);
        $this->assertSame(4, $last['index_pages']);
        $this->assertSame(0, $last['index_attempts']);
    }

    public function testIndexNextBookResumesAfterTheLastStoredPage(): void
    {
        $model = ContentSearchService::model();
        $books = $this->createMock(Books::class);
        $books->method('claimNextForIndexing')->willReturn(self::book(['index_model' => $model, 'index_progress' => 2]));
        $books->method('getBookDetails')->willReturn(self::book());

        $chunks = $this->createMock(BookChunks::class);
        // Drops whatever a crashed worker wrote past its recorded progress.
        $chunks->expects($this->once())->method('deleteUnfinished')->with(self::BOOK_ID, $model, 2);
        $chunks->expects($this->once())
            ->method('insertChunks')
            ->with($this->callback(fn (array $docs) => array_column($docs, 'page') === [3]))
            ->willReturn(1);

        $this->service($books, $chunks, $this->files(['one', 'two', 'three']), [self::vectors(1)])->indexNextBook();
    }

    public function testIndexNextBookSkipsContentsPages(): void
    {
        $books = $this->createMock(Books::class);
        $books->method('claimNextForIndexing')->willReturn(self::book());
        $books->method('getBookDetails')->willReturn(self::book());
        $chunks = $this->createMock(BookChunks::class);
        $chunks->expects($this->once())
            ->method('insertChunks')
            ->with($this->callback(fn (array $docs) => array_column($docs, 'page') === [2]))
            ->willReturn(1);
        $contents = str_repeat("Chapter 42: Depth First Search ........ 202\n", 10);

        $this->service($books, $chunks, $this->files([$contents, 'Depth-first search explores...']), [self::vectors(1)])->indexNextBook();
    }

    public function testIndexNextBookMarksScannedPdfsAsNoText(): void
    {
        $books = $this->createMock(Books::class);
        $books->method('claimNextForIndexing')->willReturn(self::book());
        $books->method('getBookDetails')->willReturn(self::book());
        $last = null;
        $books->method('updateIndexState')->willReturnCallback(function (string $id, array $set) use (&$last) {
            $last = $set;
            return [];
        });
        $chunks = $this->createMock(BookChunks::class);
        $chunks->expects($this->never())->method('insertChunks');

        $this->service($books, $chunks, $this->files(['', '']))->indexNextBook();

        $this->assertSame('no_text', $last['index_status']);
    }

    public function testIndexNextBookRecordsFailureWithBackoff(): void
    {
        $books = $this->createMock(Books::class);
        // As returned by the claim, which already counted this (second) attempt.
        $books->method('claimNextForIndexing')->willReturn(self::book(['index_attempts' => 2]));
        $files = $this->createMock(FileHelper::class);
        $files->method('extractPageTexts')->willThrowException(new \RuntimeException('corrupt PDF'));
        $books->expects($this->atLeastOnce())
            ->method('updateIndexState')
            ->willReturnCallback(function (string $id, array $set, array $inc = []) {
                if (($set['index_status'] ?? null) === 'failed') {
                    $this->assertSame('corrupt PDF', $set['index_error']);
                    $this->assertSame([], $inc, 'The claim already counted the attempt');
                    // Second failure → 2^2 = 4 minutes.
                    $this->assertEqualsWithDelta(time() + 240, $set['index_retry_at']->toDateTime()->getTimestamp(), 5);
                }
                return [];
            });

        $this->assertTrue($this->service($books, null, $files)->indexNextBook());
    }

    /**
     * @return array<string, array{Response|\Throwable}>
     */
    public static function embedderFailures(): array
    {
        return [
            'unreachable' => [new ConnectException('Connection refused', new Request('POST', '/embed'))],
            'erroring (model loading / out of memory)' => [new Response(500, [], 'llama runner process has terminated')],
            'model not pulled yet' => [new Response(404, [], '{"error":"model not found"}')],
            'garbage response' => [new Response(200, [], '{}')],
        ];
    }

    /**
     * @dataProvider embedderFailures
     */
    public function testIndexNextBookReleasesTheBookWhenTheEmbedderFails(Response|\Throwable $failure): void
    {
        $books = $this->createMock(Books::class);
        $books->method('claimNextForIndexing')->willReturn(self::book(['index_attempts' => 1]));
        $states = [];
        $books->method('updateIndexState')->willReturnCallback(function (string $id, array $set, array $inc = []) use (&$states) {
            $states[] = [$set['index_status'] ?? null, $inc];
            return [];
        });

        $this->expectException(TransferException::class);
        try {
            $this->service($books, null, $this->files(['text']), [$failure])->indexNextBook();
        } finally {
            // Back in the queue, with the attempt the claim took given back.
            $this->assertSame(['pending', ['index_attempts' => -1]], end($states));
        }
    }

    // --- search() ---

    public function testSearchFallsBackToKeywordResultsWhenTheEmbedderFails(): void
    {
        $chunks = $this->createMock(BookChunks::class);
        $chunks->method('keywordSearch')->willReturn([['book_id' => self::BOOK_ID, 'page' => 3, 'snippet' => 'hit']]);
        $chunks->expects($this->never())->method('vectorSearch');
        $books = $this->createMock(Books::class);
        $books->method('findAll')->willReturn([self::book()]);

        $down = new ConnectException('timeout', new Request('POST', '/embed'));
        $results = $this->service($books, $chunks, null, [$down])->search('stoicism', true);

        $this->assertCount(1, $results);
        $this->assertSame([['page' => 3, 'snippet' => 'hit']], $results[0]['matches']);
    }

    public function testSearchFallsBackToKeywordResultsWhenVectorSearchFails(): void
    {
        // E.g. the vector index is still building right after db-setup.
        $chunks = $this->createMock(BookChunks::class);
        $chunks->method('keywordSearch')->willReturn([['book_id' => self::BOOK_ID, 'page' => 3, 'snippet' => 'hit']]);
        $chunks->method('vectorSearch')->willThrowException(new \RuntimeException('index not ready'));
        $books = $this->createMock(Books::class);
        $books->method('findAll')->willReturn([self::book()]);

        $results = $this->service($books, $chunks, null, [self::vectors(1)])->search('stoicism', true);

        $this->assertSame([['page' => 3, 'snippet' => 'hit']], $results[0]['matches']);
    }

    public function testSearchStillAnswersByMeaningWhenKeywordSearchFails(): void
    {
        $chunks = $this->createMock(BookChunks::class);
        $chunks->method('keywordSearch')->willThrowException(new \RuntimeException('index not ready'));
        $chunks->method('vectorSearch')->willReturn([['book_id' => self::BOOK_ID, 'page' => 2, 'snippet' => 'sem', 'similarity' => 0.8]]);
        $books = $this->createMock(Books::class);
        $books->method('findAll')->willReturn([self::book()]);

        $results = $this->service($books, $chunks, null, [self::vectors(1)])->search('stoicism', true);

        $this->assertSame([['page' => 2, 'snippet' => 'sem']], $results[0]['matches']);
    }

    public function testSearchDropsBooksThatAreNoLongerPublicForPublicCallers(): void
    {
        $chunks = $this->createMock(BookChunks::class);
        $chunks->method('keywordSearch')->willReturn([['book_id' => self::BOOK_ID, 'page' => 1, 'snippet' => '']]);
        $chunks->method('vectorSearch')->willReturn([['book_id' => self::BOOK_ID, 'page' => 1, 'snippet' => '', 'similarity' => 0.8]]);
        $books = $this->createMock(Books::class);
        // Chunk status lagged behind: the book was just moved back to draft.
        $books->method('findAll')->willReturn([self::book(['status' => 'draft'])]);

        $this->assertSame([], $this->service($books, $chunks, null, [self::vectors(1)])->search('q', true));
    }

    public function testSearchOnlyShowsBooksCloseInMeaningToTheBestMatch(): void
    {
        $close = '507f1f77bcf86cd799439022';
        $far = '507f1f77bcf86cd799439033';
        $keywordOnly = '507f1f77bcf86cd799439044';
        $chunks = $this->createMock(BookChunks::class);
        // Keyword search matched common words in every book.
        $chunks->method('keywordSearch')->willReturn([
            ['book_id' => $keywordOnly, 'page' => 9, 'snippet' => 'how to'],
            ['book_id' => $far, 'page' => 2, 'snippet' => 'kw far'],
            ['book_id' => self::BOOK_ID, 'page' => 5, 'snippet' => 'kw best'],
        ]);
        $chunks->method('vectorSearch')->willReturn([
            ['book_id' => self::BOOK_ID, 'page' => 4, 'snippet' => 'sem best', 'similarity' => 0.85],
            ['book_id' => $close, 'page' => 1, 'snippet' => 'sem close', 'similarity' => 0.83],
            ['book_id' => $far, 'page' => 3, 'snippet' => 'sem far', 'similarity' => 0.78],
        ]);
        $books = $this->createMock(Books::class);
        $books->expects($this->once())
            ->method('findAll')
            ->with($this->callback(fn (array $filter) => array_map('strval', $filter['_id']['$in']) === [self::BOOK_ID, $close]))
            ->willReturn([self::book(), self::book(['_id' => $close])]);

        $results = $this->service($books, $chunks, null, [self::vectors(1)])->search('q', true);

        $this->assertSame([self::BOOK_ID, $close], array_map(fn (array $r) => (string) $r['book']['_id'], $results));
        // The best book keeps its keyword-matched page too.
        $this->assertSame([5, 4], array_column($results[0]['matches'], 'page'));
    }

    public function testSearchReturnsNothingWhenNoPageIsCloseInMeaning(): void
    {
        $chunks = $this->createMock(BookChunks::class);
        $chunks->method('keywordSearch')->willReturn([['book_id' => self::BOOK_ID, 'page' => 1, 'snippet' => 'recipe']]);
        $chunks->method('vectorSearch')->willReturn([]);
        $books = $this->createMock(Books::class);
        $books->expects($this->never())->method('findAll');

        $this->assertSame([], $this->service($books, $chunks, null, [self::vectors(1)])->search('recipe for chocolate cake', true));
    }
}

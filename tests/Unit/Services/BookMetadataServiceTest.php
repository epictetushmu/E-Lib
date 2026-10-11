<?php

namespace Tests\Unit\Services;

use App\Services\BookMetadataService;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class BookMetadataServiceTest extends TestCase
{
    /** @var list<array<string, mixed>> */
    private array $history = [];

    /** @param list<Response|\Throwable> $responses */
    private function service(array $responses): BookMetadataService
    {
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->history));
        $service = new BookMetadataService();
        (new \ReflectionProperty($service, 'http'))->setValue($service, new Client(['handler' => $stack]));
        return $service;
    }

    private function query(int $i = 0): string
    {
        parse_str($this->history[$i]['request']->getUri()->getQuery(), $q);
        return (string) $q['q'];
    }

    public function testMapsVolumeInfo(): void
    {
        $body = json_encode(['items' => [['volumeInfo' => [
            'title' => 'Introduction to Algorithms',
            'subtitle' => 'Third Edition',
            'authors' => ['Thomas H. Cormen', 'Charles E. Leiserson'],
            'publishedDate' => '2009-07-31',
            'description' => '<p>A <b>comprehensive</b> text &amp; reference.</p>',
            'industryIdentifiers' => [['type' => 'ISBN_10', 'identifier' => '0262033844'], ['type' => 'ISBN_13', 'identifier' => '9780262033848']],
            'categories' => ['Computers'],
        ]]]]);
        $result = $this->service([new Response(200, [], (string) $body)])->lookup('978-0-262-03384-8', '', '');

        $this->assertSame('isbn:9780262033848', $this->query());
        $this->assertSame([
            'title' => 'Introduction to Algorithms: Third Edition',
            'author' => 'Thomas H. Cormen, Charles E. Leiserson',
            'year' => '2009',
            'isbn' => '9780262033848',
            'description' => 'A comprehensive text & reference.',
            'categories' => ['Computers'],
        ], $result);
    }

    public function testFallsBackToTitleAndAuthor(): void
    {
        $this->service([new Response(200, [], '{"totalItems":0}'), new Response(200, [], '{"docs":[]}')])
            ->lookup('', 'SICP "2nd"', 'Abelson');
        $this->assertSame('intitle:"SICP 2nd" inauthor:"Abelson"', $this->query());
    }

    public function testPlaceholderAuthorIsNotSearched(): void
    {
        $this->service([new Response(200, [], '{"totalItems":0}'), new Response(200, [], '{"docs":[]}')])
            ->lookup('', 'Web Design', 'Unknown Author');
        $this->assertSame('intitle:"Web Design"', $this->query());
    }

    public function testIsbnFailureFallsBackToTitle(): void
    {
        $body = (string) json_encode(['items' => [['volumeInfo' => ['title' => 'SICP', 'description' => 'Classic.']]]]);
        $result = $this->service([new Response(503), new Response(200, [], $body)])->lookup('0262510871', 'SICP', '');
        $this->assertSame('intitle:"SICP"', $this->query(1));
        $this->assertSame('Classic.', $result['description'] ?? null);
        $this->assertSame('0262510871', $result['isbn'] ?? null);
        $this->assertCount(2, $this->history, 'Google had a description, so Open Library is not asked');
    }

    public function testOpenLibraryFillsWhatGoogleLacks(): void
    {
        $google = (string) json_encode(['items' => [['volumeInfo' => ['title' => 'Introduction to Algorithms', 'publishedDate' => '2009']]]]);
        $search = (string) json_encode(['docs' => [[
            'key' => '/works/OL4781294W',
            'title' => 'Introduction to algorithms',
            'author_name' => ['Thomas H. Cormen'],
            'first_publish_year' => 1990,
            'subject' => ['Algorithms', 'Computer programming'],
        ]]]);
        $edition = '{"works": [{"key": "/works/OL1W"}]}'; // no description of its own → its work's
        $work = '{"description": {"type": "/type/text", "value": "A comprehensive introduction."}}';
        $responses = [new Response(200, [], $google), new Response(200, [], $search), new Response(200, [], $edition), new Response(200, [], $work)];
        $result = $this->service($responses)->lookup('9780262033848', '', '');

        $this->assertSame('https://openlibrary.org/isbn/9780262033848.json', (string) $this->history[2]['request']->getUri());
        $this->assertSame('https://openlibrary.org/works/OL1W.json', (string) $this->history[3]['request']->getUri());
        $this->assertSame([
            'title' => 'Introduction to Algorithms',
            'author' => 'Thomas H. Cormen',
            'year' => '2009',
            'isbn' => '9780262033848',
            'description' => 'A comprehensive introduction.',
            'categories' => ['Algorithms', 'Computer programming'],
        ], $result);
    }

    public function testOpenLibraryEditionDescriptionWins(): void
    {
        $search = '{"docs": [{"key": "/works/OL2W", "title": "Guidelines"}]}';
        $edition = '{"description": "For NCI staff.", "works": [{"key": "/works/OL3W"}]}';
        $result = $this->service([new Response(503), new Response(200, [], $search), new Response(200, [], $edition)])
            ->lookup('0160762707', '', '');
        $this->assertSame('For NCI staff.', $result['description'] ?? null);
        $this->assertCount(3, $this->history, 'no work fetch when the edition has a description');
    }

    public function testCleanSubjectsDropsCodesTagsAndFlags(): void
    {
        $this->assertSame(['Algorithms', 'Computer programming', 'Αλγόριθμοι'], BookMetadataService::cleanSubjects([
            'Algorithms', 'open_syllabus_project', 'Qa76.6 .i5858 2001', '005.1', 'pdf.yt', 'mirror', 'nyt:hardcover=2010',
            'Accessible book', 'Computer programming -- Textbooks', 'algorithms', 'Αλγόριθμοι', 42,
        ]));
    }

    public function testNoMatchOrFailureIsNull(): void
    {
        $miss = [new Response(200, [], '{"totalItems":0}'), new Response(200, [], '{"docs":[]}')];
        $this->assertNull($this->service($miss)->lookup('', 'Nothing', ''));
        $down = [new ConnectException('down', new Request('GET', 'x')), new ConnectException('down', new Request('GET', 'x'))];
        $this->assertNull($this->service($down)->lookup('123456789X', '', ''));
        $this->assertNull((new BookMetadataService())->lookup('', ' ', 'only author'));
    }
}

<?php

namespace Tests\Unit\Services;

use App\Models\Books;
use App\Services\BookService;
use MongoDB\Model\BSONArray;
use MongoDB\Model\BSONDocument;
use Tests\Support\ServiceTestCase;

class BookServiceTest extends ServiceTestCase
{
    private function service(?Books $books = null): BookService
    {
        /** @var BookService $service */
        $service = $this->makeService(
            BookService::class,
            'book',
            $books ?? $this->createMock(Books::class)
        );
        return $service;
    }

    /**
     * @dataProvider truthyFeaturedValuesProvider
     */
    public function testUpdateBookNormalizesTruthyFeaturedValues(mixed $featuredInput): void
    {
        $books = $this->createMock(Books::class);
        $books->expects($this->once())
            ->method('updateBook')
            ->with('id1', $this->callback(fn ($book) => $book['featured'] === true))
            ->willReturn(['modifiedCount' => 1]);

        $this->service($books)->updateBook(
            'id1',
            'Title',
            'Author',
            '2020',
            'Description',
            ['fiction'],
            'public',
            $featuredInput,
            '1234567890',
        );
    }

    public static function truthyFeaturedValuesProvider(): array
    {
        return [
            'boolean true' => [true],
            'string true' => ['true'],
            'int 1' => [1],
            'string 1' => ['1'],
        ];
    }

    /**
     * @dataProvider falsyFeaturedValuesProvider
     */
    public function testUpdateBookNormalizesFalsyFeaturedValues(mixed $featuredInput): void
    {
        $books = $this->createMock(Books::class);
        $books->expects($this->once())
            ->method('updateBook')
            ->with('id1', $this->callback(fn ($book) => $book['featured'] === false))
            ->willReturn(['modifiedCount' => 1]);

        $this->service($books)->updateBook(
            'id1',
            'Title',
            'Author',
            '2020',
            'Description',
            ['fiction'],
            'public',
            $featuredInput,
            '1234567890',
        );
    }

    public static function falsyFeaturedValuesProvider(): array
    {
        return [
            'boolean false' => [false],
            'string false' => ['false'],
            'int 0' => [0],
            'null' => [null],
        ];
    }

    public function testUpdateBookCastsYearToInt(): void
    {
        $books = $this->createMock(Books::class);
        $books->expects($this->once())
            ->method('updateBook')
            ->with('id1', $this->callback(fn ($book) => $book['year'] === 1999))
            ->willReturn(['modifiedCount' => 1]);

        $this->service($books)->updateBook(
            'id1',
            'Title',
            'Author',
            '1999',
            'Description',
            [],
            'public',
            false,
            '1234567890',
        );
    }

    public function testAddBookRenamesFilePathToDraftStatus(): void
    {
        $books = $this->createMock(Books::class);
        $books->expects($this->once())
            ->method('addBook')
            ->with($this->callback(
                fn ($book) => $book['file_path'] === '/uploads/a.pdf'
                    && $book['status'] === 'draft'
                    && $book['views'] === 0
                    && $book['downloads'] === 0
                    && $book['featured'] === false
                    && $book['reviews'] === []
            ))
            ->willReturn(['insertedId' => 'new-id']);

        $this->service($books)->addBook(
            'Title',
            'Author',
            '2020',
            'Description',
            ['fiction'],
            '1234567890',
            '/uploads/a.pdf'
        );
    }

    public function testAddBookFallsBackToUnknownAuthorWhenBlank(): void
    {
        $books = $this->createMock(Books::class);
        $books->expects($this->once())
            ->method('addBook')
            ->with($this->callback(fn ($book) => $book['author'] === 'Unknown Author'))
            ->willReturn(['insertedId' => 'new-id']);

        $this->service($books)->addBook(
            'Title',
            '   ',
            '2020',
            'Description',
            [],
            '1234567890',
            '/uploads/a.pdf'
        );
    }

    public function testAddBookKeepsProvidedAuthor(): void
    {
        $books = $this->createMock(Books::class);
        $books->expects($this->once())
            ->method('addBook')
            ->with($this->callback(fn ($book) => $book['author'] === 'Marcus Aurelius'))
            ->willReturn(['insertedId' => 'new-id']);

        $this->service($books)->addBook(
            'Title',
            'Marcus Aurelius',
            '2020',
            'Description',
            [],
            '1234567890',
            '/uploads/a.pdf'
        );
    }

    public function testSearchBooksTreatsPlainStringAsTitleSearch(): void
    {
        $books = $this->createMock(Books::class);
        $books->expects($this->once())
            ->method('searchBooks')
            ->with($this->callback(fn ($query) => $query['title']['$regex'] === 'Meditations'))
            ->willReturn([['title' => 'Meditations']]);

        $result = $this->service($books)->searchBooks('Meditations');

        $this->assertCount(1, $result);
    }

    public function testSearchBooksReturnsEmptyArrayWhenNoCriteriaGiven(): void
    {
        $books = $this->createMock(Books::class);
        $books->expects($this->never())->method('searchBooks');

        $this->assertSame([], $this->service($books)->searchBooks([]));
    }

    public function testSearchBooksReturnsEmptyArrayOnModelException(): void
    {
        $books = $this->createMock(Books::class);
        $books->method('searchBooks')->willThrowException(new \RuntimeException('mongo down'));

        $this->assertSame([], $this->service($books)->searchBooks(['title' => 'x']));
    }

    public function testSaveReviewFillsInMissingRatingFromArgument(): void
    {
        $books = $this->createMock(Books::class);
        $books->expects($this->once())
            ->method('saveReview')
            ->with('book1', $this->callback(fn ($review) => $review['rating'] === 4))
            ->willReturn(['modifiedCount' => 1]);
        // No reviews on the book afterward, so no rating recalculation call is required to succeed.
        $books->method('getBookDetails')->willReturn(null);

        $this->service($books)->saveReview('book1', [], 4);
    }

    public function testSaveReviewDoesNotOverrideExplicitRating(): void
    {
        $books = $this->createMock(Books::class);
        $books->expects($this->once())
            ->method('saveReview')
            ->with('book1', $this->callback(fn ($review) => $review['rating'] === 5))
            ->willReturn(['modifiedCount' => 1]);
        $books->method('getBookDetails')->willReturn(null);

        $this->service($books)->saveReview('book1', ['rating' => 5], 1);
    }

    public function testSaveReviewRecalculatesAverageRatingAfterSuccess(): void
    {
        $books = $this->createMock(Books::class);
        $books->method('saveReview')->willReturn(['modifiedCount' => 1]);
        $books->method('getBookDetails')->willReturn([
            'reviews' => [['rating' => 4], ['rating' => 2]],
        ]);
        $books->expects($this->once())
            ->method('updateBookRating')
            ->with('book1', 3.0, 2)
            ->willReturn(['modifiedCount' => 1]);

        $this->service($books)->saveReview('book1', ['rating' => 2]);
    }

    public function testSaveReviewRecalculatesRatingFromDriverBsonTypes(): void
    {
        // What the MongoDB driver actually returns for an embedded array of documents.
        $books = $this->createMock(Books::class);
        $books->method('saveReview')->willReturn(['modifiedCount' => 1]);
        $books->method('getBookDetails')->willReturn([
            'reviews' => new BSONArray([new BSONDocument(['rating' => 5]), new BSONDocument(['rating' => 4])]),
        ]);
        $books->expects($this->once())
            ->method('updateBookRating')
            ->with('book1', 4.5, 2)
            ->willReturn(['modifiedCount' => 1]);

        $this->service($books)->saveReview('book1', ['rating' => 4]);
    }

    public function testSaveReviewSkipsRatingRecalculationWhenAddFails(): void
    {
        $books = $this->createMock(Books::class);
        $books->method('saveReview')->willReturn(false);
        $books->expects($this->never())->method('updateBookRating');

        $result = $this->service($books)->saveReview('book1', ['rating' => 2]);

        $this->assertFalse($result);
    }

    public function testGetBookReviewsReturnsEmptyArrayWhenBookMissing(): void
    {
        $books = $this->createMock(Books::class);
        $books->method('getBookDetails')->willReturn(null);

        $this->assertSame([], $this->service($books)->getBookReviews('missing'));
    }

    public function testGetBookReviewsReturnsStoredReviews(): void
    {
        $reviews = [['rating' => 5, 'comment' => 'Great']];
        $books = $this->createMock(Books::class);
        $books->method('getBookDetails')->willReturn(['reviews' => $reviews]);

        $this->assertSame($reviews, $this->service($books)->getBookReviews('book1'));
    }

    public function testCatalogNormalisesUntrustedQueryParameters(): void
    {
        $books = $this->createMock(Books::class);
        $books->expects($this->once())
            ->method('catalog')
            ->with(
                'kurose',
                [
                    // Trimmed, de-duplicated, blanks and non-scalars dropped.
                    'category' => ['Networking'],
                    'format' => ['epub'],
                    'author' => [],
                ],
                // Reversed range is swapped.
                [2001, 2015],
                true,
                'title', // unknown sort falls back to the default
                0,       // page < 1 is page 1
                BookService::CATALOG_PAGE_SIZE
            )
            ->willReturn(['books' => [], 'total' => 0, 'facets' => []]);

        $result = $this->service($books)->catalog([
            'q' => '  kurose ',
            'category' => [' Networking', 'Networking', '', ['nested']],
            'format' => 'EPUB',
            'author' => [],
            'year_from' => '2015',
            'year_to' => ' 2001 ',
            'sort' => 'bogus',
            'page' => '-3',
        ], true);

        $this->assertSame(1, $result['page']);
    }

    public function testCatalogCapsFilterValuesAndPages(): void
    {
        $books = $this->createMock(Books::class);
        $books->expects($this->once())
            ->method('catalog')
            ->with(
                '',
                $this->callback(fn (array $f) => count($f['author']) === 20 && mb_strlen($f['author'][0]) === 200),
                // Not a year: ignored, so the range is open-ended.
                [null, 1999],
                false,
                'views',
                999 * BookService::CATALOG_PAGE_SIZE,
                BookService::CATALOG_PAGE_SIZE
            )
            ->willReturn(['books' => [], 'total' => 0, 'facets' => []]);

        $authors = array_map(fn ($i) => $i . str_repeat('x', 300), range(1, 50));
        $this->service($books)->catalog(['author' => $authors, 'year_from' => '19x0', 'year_to' => '1999', 'sort' => 'views', 'page' => '99999'], false);
    }
}

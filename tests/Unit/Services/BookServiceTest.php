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
}

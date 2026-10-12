<?php

namespace Tests\Unit\Services;

use App\Models\Books;
use App\Models\Loans;
use App\Models\ReviewReplies;
use App\Services\NotificationService;
use App\Services\BookService;
use MongoDB\BSON\UTCDateTime;
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

    /**
     * @dataProvider loanStateProvider
     */
    public function testLoanState(array $loan, string $expected): void
    {
        $this->assertSame($expected, BookService::loanState($loan, 1_000_000));
    }

    public static function loanStateProvider(): array
    {
        $past = new UTCDateTime(999_000 * 1000);
        $future = new UTCDateTime(1_001_000 * 1000);
        return [
            'pdf on loan' => [['kind' => 'digital', 'due_at' => $future], 'active'],
            'pdf ran out' => [['kind' => 'digital', 'due_at' => $past], 'expired'],
            'legacy record without kind' => [['due_at' => $future], 'active'],
            'returned wins' => [['kind' => 'physical', 'due_at' => $past, 'returned_at' => $past], 'returned'],
            'reservation waiting' => [['kind' => 'physical', 'due_at' => null, 'pickup_by' => $future], 'reserved'],
            'reservation lapsed' => [['kind' => 'physical', 'due_at' => null, 'pickup_by' => $past], 'not_collected'],
            'reservation cancelled' => [['kind' => 'physical', 'due_at' => null, 'cancelled_at' => $past], 'cancelled'],
            'physical out' => [['kind' => 'physical', 'due_at' => $future], 'out'],
            'physical overdue' => [['kind' => 'physical', 'due_at' => $past], 'overdue'],
        ];
    }

    public function testFreedCopyGoesToTheHeadOfTheWaitlist(): void
    {
        $loans = $this->createMock(Loans::class);
        $loans->method('waitlist')->willReturn([['user_id' => 'u1'], ['user_id' => 'u2']]);
        $loans->method('activeFor')->willReturn(null);
        $loans->method('activeCount')->willReturnOnConsecutiveCalls(0, 1);
        $loans->expects($this->once())->method('reserve')->with('b1', 'u1', 1, 'physical')
            ->willReturn(['free_at' => new UTCDateTime()]);
        $loans->expects($this->once())->method('leaveWaitlist')->with('b1', 'u1');
        $notifications = $this->createMock(NotificationService::class);
        $notifications->expects($this->once())->method('notify')->with('u1', 'b1', $this->stringContains('Ready'));

        $service = $this->service();
        $this->inject($service, 'loans', $loans);
        $this->inject($service, 'notifications', $notifications);

        $book = ['title' => 'T', 'loan_copies' => 1, 'file_type' => 'physical'];
        $this->assertSame(1, $service->promoteWaitlist('b1', $book));
    }

    public function testNobodyJumpsTheWaitlist(): void
    {
        $loans = $this->createMock(Loans::class);
        $loans->method('activeFor')->willReturn(null);
        $loans->method('waitlist')->willReturn([['user_id' => 'someone-else']]);
        $loans->expects($this->never())->method('borrow');

        $service = $this->service();
        $this->inject($service, 'loans', $loans);

        $this->assertSame('queue', $service->borrow('b1', ['loan_copies' => 3], 'u1'));
    }

    /**
     * @param list<array<string, mixed>> $reviews
     */
    private function bookWithReviews(array $reviews): Books
    {
        $books = $this->createMock(Books::class);
        $books->method('getBookDetails')->willReturn(['reviews' => $reviews]);
        return $books;
    }

    public function testRepliesAreThreadedUnderTheirReview(): void
    {
        $replies = $this->createMock(ReviewReplies::class);
        $replies->method('forBook')->willReturn([
            ['_id' => 'r1', 'review_user_id' => 'alice', 'user_id' => 'bob', 'username' => 'Bob', 'text' => 'Agreed'],
            ['_id' => 'r2', 'review_user_id' => 'alice', 'user_id' => 'alice', 'username' => 'Alice', 'text' => 'Thanks'],
        ]);
        $service = $this->service($this->bookWithReviews([
            ['user_id' => 'alice', 'rating' => 5, 'comment' => 'Great'],
            ['user_id' => 'carol', 'rating' => 3, 'comment' => 'Fine'],
        ]));
        $this->inject($service, 'replies', $replies);

        $reviews = $service->getReviewsWithReplies('b1');

        $this->assertSame(['Agreed', 'Thanks'], array_column($reviews[0]['replies'], 'text'));
        $this->assertSame([], $reviews[1]['replies']);
    }

    public function testCannotReplyToAReviewThatIsGone(): void
    {
        $replies = $this->createMock(ReviewReplies::class);
        $replies->expects($this->never())->method('add');
        $service = $this->service($this->bookWithReviews([['user_id' => 'alice', 'rating' => 5]]));
        $this->inject($service, 'replies', $replies);

        $this->assertNotNull($service->addReply('b1', 'deleted-author', null, 'bob', 'Bob', 'Hello?'));
    }

    public function testCannotAnswerAReplyFromAnotherThreadOrADeletedOne(): void
    {
        $replies = $this->createMock(ReviewReplies::class);
        $replies->method('find')->willReturnMap([
            ['other', ['book_id' => 'b1', 'review_user_id' => 'carol', 'deleted' => false]],
            ['gone', ['book_id' => 'b1', 'review_user_id' => 'alice', 'deleted' => true]],
            ['ok', ['book_id' => 'b1', 'review_user_id' => 'alice', 'deleted' => false]],
        ]);
        $replies->expects($this->once())->method('add')->with('b1', 'alice', 'ok');
        $service = $this->service($this->bookWithReviews([['user_id' => 'alice'], ['user_id' => 'carol']]));
        $this->inject($service, 'replies', $replies);

        $this->assertNotNull($service->addReply('b1', 'alice', 'other', 'bob', 'Bob', 'x'));
        $this->assertNotNull($service->addReply('b1', 'alice', 'gone', 'bob', 'Bob', 'x'));
        $this->assertNull($service->addReply('b1', 'alice', 'ok', 'bob', 'Bob', 'x'));
    }

    public function testDeletingAnAnsweredReplyLeavesAPlaceholder(): void
    {
        $replies = $this->createMock(ReviewReplies::class);
        $replies->method('findOwned')->willReturn(['_id' => 'r1', 'parent_id' => null]);
        $replies->method('hasAnswers')->willReturn(true);
        $replies->expects($this->once())->method('markDeleted')->with('r1');
        $replies->expects($this->never())->method('remove');
        $service = $this->service();
        $this->inject($service, 'replies', $replies);

        $this->assertTrue($service->deleteReply('r1', 'bob'));
    }

    public function testDeletingTheLastAnswerClearsPlaceholdersAboveIt(): void
    {
        // r3 answers placeholder r2, which answers live reply r1: deleting r3 takes r2 too, but not r1.
        $replies = $this->createMock(ReviewReplies::class);
        $replies->method('findOwned')->willReturn(['_id' => 'r3', 'parent_id' => 'r2']);
        $replies->method('hasAnswers')->willReturn(false);
        $replies->method('find')->willReturnMap([
            ['r2', ['parent_id' => 'r1', 'deleted' => true]],
            ['r1', ['parent_id' => null, 'deleted' => false]],
        ]);
        $removed = [];
        $replies->method('remove')->willReturnCallback(function (string $id) use (&$removed) {
            $removed[] = $id;
        });
        $service = $this->service();
        $this->inject($service, 'replies', $replies);

        $this->assertTrue($service->deleteReply('r3', 'bob'));
        $this->assertSame(['r3', 'r2'], $removed);
    }

    public function testDeletingAReviewTakesItsThreadAndRecountsTheRating(): void
    {
        $books = $this->bookWithReviews([]);
        $books->method('removeReview')->with('b1', 'alice')->willReturn(true);
        $books->expects($this->once())->method('updateBookRating')->with('b1', 0.0, 0);
        $replies = $this->createMock(ReviewReplies::class);
        $replies->expects($this->once())->method('deleteForReview')->with('b1', 'alice');
        $service = $this->service($books);
        $this->inject($service, 'replies', $replies);

        $this->assertTrue($service->deleteReview('b1', 'alice'));
    }

    public function testOnlyTheAuthorCanEditAReply(): void
    {
        $replies = $this->createMock(ReviewReplies::class);
        $replies->method('findOwned')->willReturn(null);
        $replies->expects($this->never())->method('updateOwned');
        $service = $this->service();
        $this->inject($service, 'replies', $replies);

        $this->assertFalse($service->editReply('0123456789abcdef01234567', 'not-the-author', 'Edited'));
    }
}

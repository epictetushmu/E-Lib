<?php

namespace Tests\Unit\Models;

use App\Database\DatabaseInterface;
use App\Models\Books;
use InvalidArgumentException;
use Tests\Support\ModelTestCase;

class BooksTest extends ModelTestCase
{
    private function makeBooks(?DatabaseInterface $db = null): Books
    {
        /** @var Books $books */
        $books = $this->makeModel(Books::class, $db ?? $this->createMock(DatabaseInterface::class));
        return $books;
    }

    public function testValidateRequiresTitleButNotAuthor(): void
    {
        $errors = $this->makeBooks()->validate([]);

        $this->assertArrayHasKey('title', $errors);
        $this->assertArrayNotHasKey('author', $errors);
    }

    public function testValidatePassesWithTitleOnly(): void
    {
        $errors = $this->makeBooks()->validate(['title' => 'Meditations']);

        $this->assertSame([], $errors);
    }

    public function testValidatePassesWithTitleAndAuthor(): void
    {
        $errors = $this->makeBooks()->validate(['title' => 'Meditations', 'author' => 'Marcus Aurelius']);

        $this->assertSame([], $errors);
    }

    public function testValidateRejectsYearOutOfRange(): void
    {
        $errors = $this->makeBooks()->validate([
            'title' => 'Title',
            'author' => 'Author',
            'year' => 500,
        ]);

        $this->assertArrayHasKey('year', $errors);
    }

    public function testValidateAcceptsCurrentYear(): void
    {
        $errors = $this->makeBooks()->validate([
            'title' => 'Title',
            'author' => 'Author',
            'year' => (int) date('Y'),
        ]);

        $this->assertArrayNotHasKey('year', $errors);
    }

    public function testValidateRejectsMalformedIsbn(): void
    {
        $errors = $this->makeBooks()->validate([
            'title' => 'Title',
            'author' => 'Author',
            'isbn' => 'not-an-isbn',
        ]);

        $this->assertArrayHasKey('isbn', $errors);
    }

    public function testValidateAccepts13DigitIsbn(): void
    {
        $errors = $this->makeBooks()->validate([
            'title' => 'Title',
            'author' => 'Author',
            'isbn' => '978-3-16-148410-0',
        ]);

        $this->assertArrayNotHasKey('isbn', $errors);
    }

    public function testGetBookDetailsRejectsMalformedId(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->makeBooks()->getBookDetails('not-a-valid-object-id');
    }

    public function testAddBookThrowsWhenValidationFails(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->makeBooks()->addBook(['author' => 'Missing a title']);
    }

    public function testAddBookInsertsValidatedData(): void
    {
        $db = $this->createMock(DatabaseInterface::class);
        $db->expects($this->once())
            ->method('insert')
            ->with('Books', $this->callback(fn ($data) => $data['title'] === 'Title' && $data['author'] === 'Author'))
            ->willReturn(['insertedId' => 'abc123']);

        $result = $this->makeBooks($db)->addBook(['title' => 'Title', 'author' => 'Author']);

        $this->assertSame(['insertedId' => 'abc123'], $result);
    }

    public function testAddBookInsertsWithoutAuthor(): void
    {
        $db = $this->createMock(DatabaseInterface::class);
        $db->expects($this->once())
            ->method('insert')
            ->with('Books', $this->callback(fn ($data) => $data['title'] === 'Title' && !isset($data['author'])))
            ->willReturn(['insertedId' => 'abc123']);

        $result = $this->makeBooks($db)->addBook(['title' => 'Title']);

        $this->assertSame(['insertedId' => 'abc123'], $result);
    }

    public function testGetAllBooksDelegatesToDatabase(): void
    {
        $expected = [['title' => 'A'], ['title' => 'B']];
        $db = $this->createMock(DatabaseInterface::class);
        $db->expects($this->once())
            ->method('find')
            ->with('Books', [], [])
            ->willReturn($expected);

        $this->assertSame($expected, $this->makeBooks($db)->getAllBooks());
    }

    public function testGetPublicBooksFiltersByStatus(): void
    {
        $db = $this->createMock(DatabaseInterface::class);
        $db->expects($this->once())
            ->method('find')
            ->with('Books', ['status' => 'public'], [])
            ->willReturn([]);

        $this->makeBooks($db)->getPublicBooks();
    }

    public function testSaveReviewReturnsFalseOnDatabaseException(): void
    {
        $db = $this->createMock(DatabaseInterface::class);
        $db->method('update')->willThrowException(new \RuntimeException('mongo down'));

        $result = $this->makeBooks($db)->saveReview('507f1f77bcf86cd799439011', ['user_id' => 'u1', 'rating' => 5]);

        $this->assertFalse($result);
    }

    public function testSaveReviewEditsExistingReviewOrPushesOnlyWhenUserHasNone(): void
    {
        $db = $this->createMock(DatabaseInterface::class);
        $calls = [];
        $db->method('update')->willReturnCallback(function ($collection, $filter, $update) use (&$calls) {
            $calls[] = [$filter, $update];
            return ['modifiedCount' => 1];
        });

        $review = ['user_id' => 'u1', 'username' => 'ann', 'rating' => 3, 'comment' => 'ok', 'created_at' => 'then'];
        $this->makeBooks($db)->saveReview('507f1f77bcf86cd799439011', $review);

        [[$editFilter, $edit], [$pushFilter, $push]] = $calls;
        $this->assertSame('u1', $editFilter['reviews.user_id']);
        $this->assertSame(3, $edit['$set']['reviews.$.rating']);
        $this->assertArrayNotHasKey('reviews.$.created_at', $edit['$set']);
        $this->assertSame(['$ne' => 'u1'], $pushFilter['reviews.user_id']);
        $this->assertSame($review, $push['$push']['reviews']);
    }

    public function testUpdateBookReturnsFalseWhenNoFieldsToUpdate(): void
    {
        $db = $this->createMock(DatabaseInterface::class);
        $db->expects($this->never())->method('update');

        $result = $this->makeBooks($db)->updateBook('507f1f77bcf86cd799439011', ['description' => '']);

        $this->assertFalse($result);
    }

    public function testDeleteBookAlsoDeletesItsSearchChunks(): void
    {
        $db = $this->createMock(DatabaseInterface::class);
        $deleted = [];
        $db->method('delete')->willReturnCallback(function (string $collection, array $filter) use (&$deleted) {
            $deleted[] = [$collection, $filter];
            return ['deletedCount' => 1];
        });

        $this->makeBooks($db)->deleteBook('507f1f77bcf86cd799439011');

        $this->assertSame('Books', $deleted[0][0]);
        $this->assertSame(['BookChunks', ['book_id' => '507f1f77bcf86cd799439011']], $deleted[1]);
    }

    public function testUpdateBookCopiesStatusChangesToSearchChunks(): void
    {
        $db = $this->createMock(DatabaseInterface::class);
        $updates = [];
        $db->method('update')->willReturnCallback(function (string $collection, array $filter, array $update) use (&$updates) {
            $updates[] = [$collection, $filter, $update];
            return ['modifiedCount' => 1];
        });

        $this->makeBooks($db)->updateBook('507f1f77bcf86cd799439011', ['status' => 'public', 'title' => 'T']);

        $this->assertCount(2, $updates);
        $this->assertSame(
            ['BookChunks', ['book_id' => '507f1f77bcf86cd799439011'], ['$set' => ['status' => 'public']]],
            $updates[1]
        );
    }

    public function testUpdateBookWithoutStatusLeavesSearchChunksAlone(): void
    {
        $db = $this->createMock(DatabaseInterface::class);
        $db->expects($this->once())->method('update')->with('Books');

        $this->makeBooks($db)->updateBook('507f1f77bcf86cd799439011', ['title' => 'T']);
    }

    public function testClaimNextForIndexingLeasesTheBookAndCoversEveryReindexCase(): void
    {
        $db = $this->createMock(DatabaseInterface::class);
        $db->expects($this->once())
            ->method('findOneAndUpdate')
            ->with(
                'Books',
                $this->callback(function (array $filter) {
                    $cases = array_map(fn ($c) => $c['index_status'], $filter['$or']);
                    $this->assertContains(['$exists' => false], $cases);
                    $this->assertContains('indexing', $cases);
                    $this->assertContains('failed', $cases);
                    $modelChange = $filter['$or'][4];
                    $this->assertSame(['$ne' => 'model-b'], $modelChange['index_model']);
                    $this->assertSame(['$lt' => 3], $filter['$or'][3]['index_attempts']);
                    // A lapsed lease is only re-claimed while attempts remain.
                    $this->assertSame(['$lt' => 3], $filter['$or'][2]['index_attempts']);
                    // Only PDFs.
                    $this->assertSame(1, preg_match('/' . $filter['file_path']->getPattern() . '/i', '/x/doc_1.PDF'));
                    $this->assertSame(0, preg_match('/' . $filter['file_path']->getPattern() . '/i', '/x/doc_1.docx'));
                    return true;
                }),
                $this->callback(fn (array $update) => $update['$set']['index_status'] === 'indexing'
                    && $update['$set']['index_lease_until']->toDateTime()->getTimestamp() >= time() + 299
                    // Every claim counts as an attempt, so a book that kills the worker runs out of them.
                    && $update['$inc'] === ['index_attempts' => 1])
            )
            ->willReturn(null);
        // Books whose worker died on every attempt are retired as failed.
        $db->expects($this->once())
            ->method('update')
            ->with('Books', $this->callback(fn (array $filter) => $filter['index_status'] === 'indexing'
                && $filter['index_attempts'] === ['$gte' => 3]), $this->callback(fn (array $update) => $update['$set']['index_status'] === 'failed'));

        $this->assertNull($this->makeBooks($db)->claimNextForIndexing('model-b', 300, 3));
    }
}

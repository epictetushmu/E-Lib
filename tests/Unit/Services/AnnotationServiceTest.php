<?php

namespace Tests\Unit\Services;

use App\Models\Annotations;
use App\Services\AnnotationService;
use InvalidArgumentException;
use Tests\Support\ServiceTestCase;

class AnnotationServiceTest extends ServiceTestCase
{
    private const USER = 'aaaaaaaaaaaaaaaaaaaaaaaa';
    private const BOOK = 'bbbbbbbbbbbbbbbbbbbbbbbb';
    private const ID = 'cccccccccccccccccccccccc';

    private function service(Annotations $annotations): AnnotationService
    {
        /** @var AnnotationService $service */
        $service = $this->makeService(AnnotationService::class, 'annotations', $annotations);
        return $service;
    }

    public function testCreateTakesUserFromCallerNotFromInput(): void
    {
        $annotations = $this->createMock(Annotations::class);
        $annotations->expects($this->once())->method('create')
            ->with($this->callback(fn (array $doc) => $doc['user_id'] === self::USER && !isset($doc['is_admin'])))
            ->willReturn(['insertedId' => self::ID]);

        $created = $this->service($annotations)->create(self::USER, self::BOOK, [
            'user_id' => 'dddddddddddddddddddddddd',
            'is_admin' => true,
            'page' => 2,
            'rects' => [[1, 2, 3, 4]],
            'quote' => '  some words ',
            'color' => 'olive',
        ]);

        $this->assertSame(self::ID, $created['id']);
        $this->assertSame('some words', $created['quote']);
        $this->assertArrayNotHasKey('user_id', $created);
    }

    public function testUpdateOfSomeoneElsesAnnotationReturnsNullAndWritesNothing(): void
    {
        $annotations = $this->createMock(Annotations::class);
        $annotations->method('findOwned')->with(self::ID, self::USER)->willReturn(null);
        $annotations->expects($this->never())->method('updateOwned');

        $this->assertNull($this->service($annotations)->update(self::USER, self::ID, ['note' => 'mine now']));
    }

    public function testUpdateChangesOnlyNoteAndColour(): void
    {
        $annotations = $this->createMock(Annotations::class);
        $annotations->method('findOwned')->willReturn([
            '_id' => self::ID, 'user_id' => self::USER, 'book_id' => self::BOOK, 'page' => 4,
            'rects' => [[1, 2, 3, 4]], 'quote' => 'q', 'note' => '', 'color' => 'saffron',
        ]);
        $annotations->method('validate')->willReturn([]);
        $annotations->expects($this->once())->method('updateOwned')
            ->with(self::ID, self::USER, ['note' => 'why', 'color' => 'aegean']);

        $updated = $this->service($annotations)->update(self::USER, self::ID, [
            'note' => ' why ', 'color' => 'aegean', 'page' => 99, 'user_id' => 'dddddddddddddddddddddddd',
        ]);

        $this->assertSame(4, $updated['page'] ?? null);
        $this->assertSame('why', $updated['note'] ?? null);
    }

    public function testUpdateRejectsInvalidValues(): void
    {
        $annotations = $this->createMock(Annotations::class);
        $annotations->method('findOwned')->willReturn(['_id' => self::ID, 'rects' => [[1, 2, 3, 4]], 'page' => 1]);
        $annotations->method('validate')->willReturn(['color' => 'Unknown highlight colour']);
        $annotations->expects($this->never())->method('updateOwned');

        $this->expectException(InvalidArgumentException::class);
        $this->service($annotations)->update(self::USER, self::ID, ['color' => 'nope']);
    }

    public function testDeleteReportsWhetherAnythingWasRemoved(): void
    {
        $annotations = $this->createMock(Annotations::class);
        $annotations->method('deleteOwned')->willReturnOnConsecutiveCalls(['deletedCount' => 1], ['deletedCount' => 0]);

        $service = $this->service($annotations);
        $this->assertTrue($service->delete(self::USER, self::ID));
        $this->assertFalse($service->delete(self::USER, self::ID));
    }
}

<?php

namespace Tests\Unit\Models;

use App\Database\DatabaseInterface;
use App\Models\Annotations;
use Tests\Support\ModelTestCase;

class AnnotationsTest extends ModelTestCase
{
    private const USER = 'aaaaaaaaaaaaaaaaaaaaaaaa';
    private const BOOK = 'bbbbbbbbbbbbbbbbbbbbbbbb';
    private const ID = 'cccccccccccccccccccccccc';

    private function makeAnnotations(?DatabaseInterface $db = null): Annotations
    {
        /** @var Annotations $annotations */
        $annotations = $this->makeModel(Annotations::class, $db ?? $this->createMock(DatabaseInterface::class));
        return $annotations;
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function highlight(array $overrides = []): array
    {
        return array_merge([
            'user_id' => self::USER,
            'book_id' => self::BOOK,
            'page' => 3,
            'rects' => [[10, 20.5, 110, 34]],
            'quote' => 'A library is a garden of ideas.',
            'note' => '',
            'color' => 'saffron',
        ], $overrides);
    }

    public function testValidHighlightPasses(): void
    {
        $this->assertSame([], $this->makeAnnotations()->validate($this->highlight()));
    }

    public function testPageNoteWithoutRectsNeedsText(): void
    {
        $model = $this->makeAnnotations();

        $this->assertSame([], $model->validate($this->highlight(['rects' => [], 'color' => null, 'note' => 'Re-read this'])));
        $this->assertArrayHasKey('note', $model->validate($this->highlight(['rects' => [], 'color' => null, 'note' => '  '])));
    }

    public function testRejectsUnknownColourOnHighlight(): void
    {
        $this->assertArrayHasKey('color', $this->makeAnnotations()->validate($this->highlight(['color' => 'red; drop'])));
    }

    /**
     * @dataProvider badRectsProvider
     */
    public function testRejectsMalformedRects(mixed $rects): void
    {
        $this->assertArrayHasKey('rects', $this->makeAnnotations()->validate($this->highlight(['rects' => $rects])));
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function badRectsProvider(): array
    {
        return [
            'not a list' => ['nope'],
            'three numbers' => [[[1, 2, 3]]],
            'string coordinate' => [[[1, 2, 3, '4']]],
            'keyed rect' => [[['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4]]],
            'too many' => [array_fill(0, Annotations::MAX_RECTS + 1, [1, 2, 3, 4])],
        ];
    }

    public function testRejectsNonPositivePageAndBadIds(): void
    {
        $errors = $this->makeAnnotations()->validate($this->highlight(['page' => 0, 'user_id' => 'x', 'book_id' => ['$ne' => 1]]));

        $this->assertArrayHasKey('page', $errors);
        $this->assertArrayHasKey('user_id', $errors);
        $this->assertArrayHasKey('book_id', $errors);
    }

    public function testRejectsNonStringNote(): void
    {
        $this->assertArrayHasKey('note', $this->makeAnnotations()->validate($this->highlight(['note' => ['x']])));
    }

    public function testOwnedQueriesAlwaysFilterByUser(): void
    {
        $db = $this->createMock(DatabaseInterface::class);
        $ownedFilter = $this->callback(fn (array $f) => $f['user_id'] === self::USER && (string) $f['_id'] === self::ID);
        $db->expects($this->once())->method('findOne')->with('Annotations', $ownedFilter)->willReturn(null);
        $db->expects($this->once())->method('update')->with('Annotations', $ownedFilter)->willReturn(['modifiedCount' => 0]);
        $db->expects($this->once())->method('delete')->with('Annotations', $ownedFilter)->willReturn(['deletedCount' => 0]);
        $db->expects($this->once())->method('find')
            ->with('Annotations', ['user_id' => self::USER, 'book_id' => self::BOOK])
            ->willReturn([]);

        $model = $this->makeAnnotations($db);
        $model->findOwned(self::ID, self::USER);
        $model->updateOwned(self::ID, self::USER, ['note' => 'x']);
        $model->deleteOwned(self::ID, self::USER);
        $model->findForUserAndBook(self::USER, self::BOOK);
    }
}

<?php

namespace Tests\Unit\Models;

use App\Database\DatabaseInterface;
use App\Models\Friendships;
use Tests\Support\ModelTestCase;

class FriendshipsTest extends ModelTestCase
{
    private const A = 'aaaaaaaaaaaaaaaaaaaaaaaa';
    private const B = 'bbbbbbbbbbbbbbbbbbbbbbbb';

    private function makeFriendships(DatabaseInterface $db): Friendships
    {
        /** @var Friendships $model */
        $model = $this->makeModel(Friendships::class, $db);
        return $model;
    }

    public function testPairIdIsTheSameWhicheverSideAsks(): void
    {
        $this->assertSame(self::A . ':' . self::B, Friendships::pairId(self::A, self::B));
        $this->assertSame(self::A . ':' . self::B, Friendships::pairId(self::B, self::A));
    }

    public function testFirstRequestInsertsAPendingPair(): void
    {
        $db = $this->createMock(DatabaseInterface::class);
        $db->expects($this->once())->method('insert')
            ->with('Friendships', $this->callback(fn ($doc) => $doc['_id'] === self::A . ':' . self::B
                && $doc['requester_id'] === self::B && $doc['status'] === 'pending'))
            ->willReturn(['insertedId' => self::A . ':' . self::B]);
        $db->expects($this->never())->method('update');

        $this->assertSame('requested', $this->makeFriendships($db)->request(self::B, self::A));
    }

    public function testCrossedRequestAcceptsTheirs(): void
    {
        $db = $this->createMock(DatabaseInterface::class);
        $db->method('insert')->willReturn(['error' => 'E11000 duplicate key']);
        // B asking A, when A already asked B, accepts A's request: the filter requires A to be the requester.
        $db->expects($this->once())->method('update')
            ->with('Friendships', $this->callback(fn ($f) => $f['requester_id'] === self::A && $f['status'] === 'pending'))
            ->willReturn(['modifiedCount' => 1]);

        $this->assertSame('accepted', $this->makeFriendships($db)->request(self::B, self::A));
    }

    public function testRepeatedOwnRequestChangesNothing(): void
    {
        $db = $this->createMock(DatabaseInterface::class);
        $db->method('insert')->willReturn(['error' => 'E11000 duplicate key']);
        $db->method('update')->willReturn(['modifiedCount' => 0]);

        $this->assertSame('exists', $this->makeFriendships($db)->request(self::A, self::B));
    }
}

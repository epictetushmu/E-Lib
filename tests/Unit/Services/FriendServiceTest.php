<?php

namespace Tests\Unit\Services;

use App\Models\Books;
use App\Models\Friendships;
use App\Models\Users;
use App\Services\FriendService;
use Tests\Support\ServiceTestCase;

class FriendServiceTest extends ServiceTestCase
{
    private const USER = 'aaaaaaaaaaaaaaaaaaaaaaaa';
    private const FRIEND = 'bbbbbbbbbbbbbbbbbbbbbbbb';
    private const PUBLIC_BOOK = 'cccccccccccccccccccccccc';
    private const DRAFT_BOOK = 'dddddddddddddddddddddddd';

    /**
     * @param array<string, mixed> $friend The friend's user document
     */
    private function makeLibraryService(bool $areFriends, array $friend): FriendService
    {
        $friendships = $this->createMock(Friendships::class);
        $friendships->method('areFriends')->with(self::USER, self::FRIEND)->willReturn($areFriends);
        $users = $this->createMock(Users::class);
        $users->method('getUserById')->willReturn($friend);
        $books = $this->createMock(Books::class);
        $books->method('getBookDetails')->willReturnCallback(fn (string $id) => match ($id) {
            self::PUBLIC_BOOK => ['_id' => $id, 'title' => 'Public', 'status' => 'public'],
            self::DRAFT_BOOK => ['_id' => $id, 'title' => 'Draft', 'status' => 'draft'],
            default => null,
        });
        $books->method('reviewedBy')->willReturn([
            ['_id' => self::PUBLIC_BOOK, 'title' => 'Public', 'author' => 'A', 'reviews' => [['rating' => 5, 'comment' => 'Great']]],
        ]);

        /** @var FriendService $service */
        $service = $this->makeService(FriendService::class, 'friendships', $friendships);
        $this->inject($service, 'users', $users);
        $this->inject($service, 'books', $books);
        return $service;
    }

    public function testFriendsSharingListsOnlyAcceptedFriendsWithTheSwitchOn(): void
    {
        $pending = 'eeeeeeeeeeeeeeeeeeeeeeee';
        $private = 'ffffffffffffffffffffffff';
        $friendships = $this->createMock(Friendships::class);
        $friendships->method('forUser')->willReturn([
            ['users' => [self::USER, self::FRIEND], 'status' => 'accepted'],
            ['users' => [$private, self::USER], 'status' => 'accepted'],
            ['users' => [self::USER, $pending], 'status' => 'pending'],
        ]);
        $users = $this->createMock(Users::class);
        $users->expects($this->once())->method('findAll')
            // Only accepted friends are looked up; the pending request never is.
            ->with($this->callback(fn ($f) => count($f['_id']['$in']) === 2))
            ->willReturn([
                ['_id' => self::FRIEND, 'username' => 'Nikos', 'sharing' => ['notes' => true]],
                ['_id' => $private, 'username' => 'Eleni'], // never turned notes on
            ]);

        /** @var FriendService $service */
        $service = $this->makeService(FriendService::class, 'friendships', $friendships);
        $this->inject($service, 'users', $users);

        $this->assertSame([self::FRIEND => 'Nikos'], $service->friendsSharing(self::USER, 'notes'));
    }

    public function testLibraryIsHiddenFromNonFriends(): void
    {
        $this->assertNull($this->makeLibraryService(false, ['username' => 'b'])->library(self::USER, self::FRIEND));
    }

    public function testLibraryShowsDefaultSectionsAndHidesReadingActivity(): void
    {
        $library = $this->makeLibraryService(true, [
            'username' => 'b',
            'savedBooks' => [self::PUBLIC_BOOK, self::DRAFT_BOOK],
            'downloadedBooks' => [self::PUBLIC_BOOK],
        ])->library(self::USER, self::FRIEND);

        $this->assertNotNull($library);
        $this->assertSame(['Public'], array_column($library['saved'] ?? [], 'title'), 'drafts never show');
        $this->assertSame(5, $library['reviews'][0]['rating'] ?? null);
        $this->assertSame('Great', $library['reviews'][0]['comment'] ?? null);
        $this->assertNull($library['loans'], 'loans are private by default');
        $this->assertNull($library['downloads'], 'downloads are private by default');
    }

    public function testLibraryFollowsTheFriendsSwitches(): void
    {
        $library = $this->makeLibraryService(true, [
            'username' => 'b',
            'savedBooks' => [self::PUBLIC_BOOK],
            'downloadedBooks' => [self::PUBLIC_BOOK, self::DRAFT_BOOK],
            'sharing' => ['saved' => false, 'reviews' => false, 'downloads' => true],
        ])->library(self::USER, self::FRIEND);

        $this->assertNotNull($library);
        $this->assertNull($library['saved']);
        $this->assertNull($library['reviews']);
        $this->assertSame(['Public'], array_column($library['downloads'] ?? [], 'title'));
    }

    public function testSharingDefaultsForUsersWhoNeverChose(): void
    {
        $this->assertSame(
            ['saved' => true, 'reviews' => true, 'loans' => false, 'downloads' => false, 'notes' => false],
            FriendService::sharing(['username' => 'x'])
        );
    }

    public function testSharingKeepsOnlyBooleanChoices(): void
    {
        $this->assertSame(
            ['saved' => false, 'reviews' => true, 'loans' => true, 'downloads' => false, 'notes' => false],
            FriendService::sharing(['sharing' => ['saved' => false, 'loans' => true, 'downloads' => 'yes']])
        );
    }

    public function testSetSharingWritesOnlyKnownBooleanSwitches(): void
    {
        $users = $this->createMock(Users::class);
        $users->expects($this->once())->method('updateById')
            ->with(self::USER, ['sharing.loans' => true, 'sharing.saved' => false]);
        $users->method('getUserById')->willReturn(['sharing' => ['loans' => true, 'saved' => false]]);

        /** @var FriendService $service */
        $service = $this->makeService(FriendService::class, 'users', $users);
        $result = $service->setSharing(self::USER, ['saved' => false, 'loans' => true, 'downloads' => 1, 'isAdmin' => true]);

        $this->assertSame(['saved' => false, 'reviews' => true, 'loans' => true, 'downloads' => false, 'notes' => false], $result);
    }
}

<?php

namespace Tests\Unit\Services;

use App\Models\Users;
use App\Services\FriendService;
use Tests\Support\ServiceTestCase;

class FriendServiceTest extends ServiceTestCase
{
    private const USER = 'aaaaaaaaaaaaaaaaaaaaaaaa';

    public function testSharingDefaultsForUsersWhoNeverChose(): void
    {
        $this->assertSame(
            ['saved' => true, 'reviews' => true, 'loans' => false, 'downloads' => false],
            FriendService::sharing(['username' => 'x'])
        );
    }

    public function testSharingKeepsOnlyBooleanChoices(): void
    {
        $this->assertSame(
            ['saved' => false, 'reviews' => true, 'loans' => true, 'downloads' => false],
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

        $this->assertSame(['saved' => false, 'reviews' => true, 'loans' => true, 'downloads' => false], $result);
    }
}

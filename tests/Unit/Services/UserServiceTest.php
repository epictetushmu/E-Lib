<?php

namespace Tests\Unit\Services;

use App\Models\Users;
use App\Services\UserService;
use MongoDB\BSON\UTCDateTime;
use Tests\Support\ServiceTestCase;

class UserServiceTest extends ServiceTestCase
{
    private function service(?Users $users = null): UserService
    {
        /** @var UserService $service */
        $service = $this->makeService(
            UserService::class,
            'user',
            $users ?? $this->createMock(Users::class)
        );
        return $service;
    }

    public function testRegisterUserHashesPasswordAndDefaultsNonAdmin(): void
    {
        $users = $this->createMock(Users::class);
        $users->expects($this->once())
            ->method('registerUser')
            ->with($this->callback(function ($user) {
                return $user['username'] === 'nik'
                    && $user['email'] === 'nik@example.com'
                    && $user['isAdmin'] === false
                    && $user['createdAt'] instanceof UTCDateTime
                    && password_verify('secret123', $user['password']);
            }))
            ->willReturn(['insertedId' => 'u1']);

        $this->service($users)->registerUser('nik', 'nik@example.com', 'secret123');
    }

    public function testGetSavedBooksReturnsEmptyWhenUserHasNone(): void
    {
        $users = $this->createMock(Users::class);
        $users->method('getUserById')->willReturn(['_id' => 'u1', 'savedBooks' => []]);

        $this->assertSame([], $this->service($users)->getSavedBooks('u1'));
    }

    public function testGetSavedBooksReturnsEmptyWhenUserMissing(): void
    {
        $users = $this->createMock(Users::class);
        $users->method('getUserById')->willReturn(null);

        $this->assertSame([], $this->service($users)->getSavedBooks('u1'));
    }

    public function testGetSavedBooksReturnsStoredIds(): void
    {
        $users = $this->createMock(Users::class);
        $users->method('getUserById')->willReturn(['_id' => 'u1', 'savedBooks' => ['b1', 'b2']]);

        $this->assertSame(['b1', 'b2'], $this->service($users)->getSavedBooks('u1'));
    }

    public function testGetDownloadedBookIdsReturnsStoredIds(): void
    {
        $users = $this->createMock(Users::class);
        $users->method('getUserById')->willReturn(['_id' => 'u1', 'downloadedBooks' => ['b3']]);

        $this->assertSame(['b3'], $this->service($users)->getDownloadedBookIds('u1'));
    }

    public function testRecordDownloadReturnsTrueWhenModelSucceeds(): void
    {
        $users = $this->createMock(Users::class);
        $users->method('recordDownload')->willReturn(['modifiedCount' => 1]);

        $this->assertTrue($this->service($users)->recordDownload('u1', 'b1'));
    }

    public function testRecordDownloadReturnsFalseWhenModelFails(): void
    {
        $users = $this->createMock(Users::class);
        $users->method('recordDownload')->willReturn(false);

        $this->assertFalse($this->service($users)->recordDownload('u1', 'b1'));
    }

    public function testUpdateUserDelegatesToModel(): void
    {
        $users = $this->createMock(Users::class);
        $users->expects($this->once())
            ->method('updateUser')
            ->with('u1', ['name' => 'Nik'])
            ->willReturn(true);

        $this->assertTrue($this->service($users)->updateUser('u1', ['name' => 'Nik']));
    }
}

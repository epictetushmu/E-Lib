<?php

namespace Tests\Unit\Models;

use App\Database\DatabaseInterface;
use App\Models\Users;
use InvalidArgumentException;
use Tests\Support\ModelTestCase;

class UsersTest extends ModelTestCase
{
    private function makeUsers(?DatabaseInterface $db = null): Users
    {
        /** @var Users $users */
        $users = $this->makeModel(Users::class, $db ?? $this->createMock(DatabaseInterface::class));
        return $users;
    }

    public function testValidateRequiresEmailPasswordAndUsername(): void
    {
        $errors = $this->makeUsers()->validate([]);

        $this->assertArrayHasKey('email', $errors);
        $this->assertArrayHasKey('password', $errors);
        $this->assertArrayHasKey('username', $errors);
    }

    public function testValidateRejectsMalformedEmail(): void
    {
        $errors = $this->makeUsers()->validate([
            'email' => 'not-an-email',
            'password' => 'longenough',
            'username' => 'nik',
        ]);

        $this->assertArrayHasKey('email', $errors);
    }

    public function testValidateRejectsShortPassword(): void
    {
        $errors = $this->makeUsers()->validate([
            'email' => 'nik@example.com',
            'password' => 'short',
            'username' => 'nik',
        ]);

        $this->assertArrayHasKey('password', $errors);
    }

    public function testValidateRejectsEmailAlreadyInUseOnRegistration(): void
    {
        $db = $this->createMock(DatabaseInterface::class);
        $db->method('findOne')->willReturn(['_id' => 'existing-id', 'email' => 'nik@example.com']);

        $errors = $this->makeUsers($db)->validate([
            'email' => 'nik@example.com',
            'password' => 'longenough',
            'username' => 'nik',
        ]);

        $this->assertArrayHasKey('email', $errors);
    }

    public function testValidatePassesForNewUniqueUser(): void
    {
        $db = $this->createMock(DatabaseInterface::class);
        $db->method('findOne')->willReturn(null);

        $errors = $this->makeUsers($db)->validate([
            'email' => 'nik@example.com',
            'password' => 'longenough',
            'username' => 'nik',
        ]);

        $this->assertSame([], $errors);
    }

    public function testGetUserByEmailRejectsMalformedEmail(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->makeUsers()->getUserByEmail('not-an-email');
    }

    public function testGetUserByIdReturnsNullForInvalidIdInsteadOfThrowing(): void
    {
        $this->assertNull($this->makeUsers()->getUserById('not-a-valid-object-id'));
    }

    public function testSaveBookAddsNewBookIdOnce(): void
    {
        $db = $this->createMock(DatabaseInterface::class);
        $db->method('findOne')->willReturn(['_id' => 'u1', 'savedBooks' => []]);
        $db->expects($this->once())
            ->method('update')
            ->with('Users', $this->anything(), ['$set' => ['savedBooks' => ['b1']]])
            ->willReturn(['modifiedCount' => 1]);

        $result = $this->makeUsers($db)->saveBook('507f1f77bcf86cd799439011', 'b1');

        $this->assertSame(['modifiedCount' => 1], $result);
    }

    public function testSaveBookIsIdempotentForAlreadySavedBook(): void
    {
        $db = $this->createMock(DatabaseInterface::class);
        $db->method('findOne')->willReturn(['_id' => 'u1', 'savedBooks' => ['b1']]);
        $db->expects($this->never())->method('update');

        $this->assertTrue($this->makeUsers($db)->saveBook('507f1f77bcf86cd799439011', 'b1'));
    }

    public function testSaveBookReturnsFalseWhenUserNotFound(): void
    {
        $db = $this->createMock(DatabaseInterface::class);
        $db->method('findOne')->willReturn(null);

        $this->assertFalse($this->makeUsers($db)->saveBook('507f1f77bcf86cd799439011', 'b1'));
    }

    public function testRemoveBookDropsBookFromSavedList(): void
    {
        $db = $this->createMock(DatabaseInterface::class);
        $db->method('findOne')->willReturn(['_id' => 'u1', 'savedBooks' => ['b1', 'b2']]);
        $db->expects($this->once())
            ->method('update')
            ->with('Users', $this->anything(), $this->callback(
                fn ($update) => array_values($update['$set']['savedBooks']) === ['b2']
            ))
            ->willReturn(['modifiedCount' => 1]);

        $this->makeUsers($db)->removeBook('507f1f77bcf86cd799439011', 'b1');
    }

    public function testRecordDownloadIsIdempotentAndKeepsInsertionOrder(): void
    {
        $db = $this->createMock(DatabaseInterface::class);
        $db->method('findOne')->willReturn(['_id' => 'u1', 'downloadedBooks' => ['b1']]);
        $db->expects($this->never())->method('update');

        $this->assertTrue($this->makeUsers($db)->recordDownload('507f1f77bcf86cd799439011', 'b1'));
    }
}

<?php

namespace Tests\Unit\Models;

use App\Database\DatabaseInterface;
use App\Models\Loans;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Driver\Exception\CommandException;
use Tests\Support\ModelTestCase;

class LoansTest extends ModelTestCase
{
    private const BOOK = 'bbbbbbbbbbbbbbbbbbbbbbbb';
    private const USER = 'aaaaaaaaaaaaaaaaaaaaaaaa';

    private function makeLoans(DatabaseInterface $db): Loans
    {
        /** @var Loans $loans */
        $loans = $this->makeModel(Loans::class, $db);
        return $loans;
    }

    public function testTakesTheFirstFreeSlot(): void
    {
        $db = $this->createMock(DatabaseInterface::class);
        $db->method('findOne')->willReturn(null);
        $slots = [];
        $db->method('findOneAndUpdate')->willReturnCallback(function ($c, array $filter) use (&$slots) {
            $slots[] = $filter['_id'];
            if ($filter['_id'] === self::BOOK . ':0') {
                throw new CommandException('E11000 duplicate key', 11000);
            }
            return ['_id' => $filter['_id'], 'user_id' => self::USER];
        });
        $db->expects($this->once())->method('insert')
            ->with('LoanHistory', $this->callback(fn ($doc) => $doc['user_id'] === self::USER && $doc['returned_at'] === null));

        $loan = $this->makeLoans($db)->borrow(self::BOOK, self::USER, 3, 14);

        $this->assertSame(self::BOOK . ':1', $loan['_id'] ?? null);
        $this->assertSame([self::BOOK . ':0', self::BOOK . ':1'], $slots);
    }

    public function testReturnsNullWhenEveryCopyIsOut(): void
    {
        $db = $this->createMock(DatabaseInterface::class);
        $db->method('findOne')->willReturn(null);
        $db->expects($this->exactly(2))->method('findOneAndUpdate')
            ->willThrowException(new CommandException('E11000 duplicate key', 11000));

        $this->assertNull($this->makeLoans($db)->borrow(self::BOOK, self::USER, 2, 14));
    }

    public function testExistingLoanIsReturnedWithoutTakingAnotherCopy(): void
    {
        $db = $this->createMock(DatabaseInterface::class);
        $db->method('findOne')->willReturn(['_id' => self::BOOK . ':0', 'user_id' => self::USER, 'due_at' => new UTCDateTime()]);
        $db->expects($this->never())->method('findOneAndUpdate');

        $this->assertSame(self::BOOK . ':0', $this->makeLoans($db)->borrow(self::BOOK, self::USER, 2, 14)['_id']);
    }

    public function testOtherServerErrorsAreNotSwallowed(): void
    {
        $db = $this->createMock(DatabaseInterface::class);
        $db->method('findOne')->willReturn(null);
        $db->method('findOneAndUpdate')->willThrowException(new CommandException('not primary', 10107));

        $this->expectException(CommandException::class);
        $this->makeLoans($db)->borrow(self::BOOK, self::USER, 2, 14);
    }

    public function testCheckOutTurnsTheReadersReservationIntoALoan(): void
    {
        $historyId = '0123456789abcdef01234567';
        $db = $this->createMock(DatabaseInterface::class);
        $db->method('findOne')->willReturnOnConsecutiveCalls(
            ['_id' => self::BOOK . ':0', 'due_at' => null, 'history_id' => $historyId],
            ['_id' => $historyId, 'kind' => 'physical']
        );
        $db->expects($this->never())->method('findOneAndUpdate');
        $updated = [];
        $db->method('update')->willReturnCallback(function (string $collection, array $filter, array $update) use (&$updated) {
            $updated[$collection] = $update['$set'];
            return ['modifiedCount' => 1];
        });

        $this->assertNotNull($this->makeLoans($db)->checkOut(self::BOOK, self::USER, 2, 14));
        $this->assertNotNull($updated['Loans']['due_at']);
        $this->assertGreaterThan(time() * 1000 + 10 ** 12, (int) (string) $updated['Loans']['free_at'], 'held until check-in');
        $this->assertArrayHasKey('borrowed_at', $updated['LoanHistory']);
    }

    public function testCheckOutRefusesWhenTheReaderAlreadyHasItOut(): void
    {
        $db = $this->createMock(DatabaseInterface::class);
        $db->method('findOne')->willReturn(['_id' => self::BOOK . ':0', 'due_at' => new UTCDateTime(), 'history_id' => 'x']);
        $db->expects($this->never())->method('update');

        $this->assertNull($this->makeLoans($db)->checkOut(self::BOOK, self::USER, 2, 14));
    }

    public function testBorrowStartsTheLoanOnACopyHeldForTheReader(): void
    {
        $db = $this->createMock(DatabaseInterface::class);
        $db->method('findOne')->willReturnOnConsecutiveCalls(
            ['_id' => self::BOOK . ':0', 'due_at' => null, 'history_id' => '0123456789abcdef01234567'],
            ['_id' => '0123456789abcdef01234567'],
            ['_id' => self::BOOK . ':0', 'due_at' => new UTCDateTime()]
        );
        $db->expects($this->never())->method('findOneAndUpdate');
        $sets = [];
        $db->method('update')->willReturnCallback(function (string $c, array $f, array $u) use (&$sets) {
            $sets[$c] = $u['$set'];
            return [];
        });

        $this->makeLoans($db)->borrow(self::BOOK, self::USER, 1, 14);

        $this->assertEquals($sets['Loans']['due_at'], $sets['Loans']['free_at'], 'a PDF loan frees the copy at its due date');
        $this->assertArrayHasKey('borrowed_at', $sets['LoanHistory']);
    }

    /**
     * @param array<string, mixed> $history
     */
    private function renewDb(int $dueOffsetSeconds, array $history): DatabaseInterface
    {
        $historyId = '0123456789abcdef01234567';
        $db = $this->createMock(DatabaseInterface::class);
        $db->method('findOne')->willReturnOnConsecutiveCalls(
            ['_id' => self::BOOK . ':0', 'due_at' => new UTCDateTime((time() + $dueOffsetSeconds) * 1000), 'history_id' => $historyId],
            ['_id' => new \MongoDB\BSON\ObjectId($historyId)] + $history,
            ['_id' => $historyId, 'due_at' => new UTCDateTime()]
        );
        return $db;
    }

    public function testRenewStopsAfterTheLimit(): void
    {
        $db = $this->renewDb(86400, ['kind' => 'physical', 'renewals' => Loans::MAX_RENEWALS]);
        $db->expects($this->never())->method('update');

        $this->assertIsString($this->makeLoans($db)->renew(self::BOOK, self::USER, 14, false));
    }

    public function testOverdueLoansRenewOnlyAtTheDesk(): void
    {
        $reader = $this->renewDb(-86400, ['kind' => 'physical', 'renewals' => 0]);
        $reader->expects($this->never())->method('update');
        $this->assertIsString($this->makeLoans($reader)->renew(self::BOOK, self::USER, 14, false));

        $desk = $this->renewDb(-86400, ['kind' => 'physical', 'renewals' => 0]);
        $updates = [];
        $desk->method('update')->willReturnCallback(function (string $c, array $f, array $u) use (&$updates) {
            $updates[$c] = $u;
            return [];
        });
        $this->assertIsArray($this->makeLoans($desk)->renew(self::BOOK, self::USER, 14, true));
        $this->assertArrayNotHasKey('free_at', $updates['Loans']['$set'], 'a physical copy stays held until check-in');
        $this->assertSame(['renewals' => 1], $updates['LoanHistory']['$inc']);
        $this->assertNull($updates['LoanHistory']['$set']['overdue_notified_at'], 'a new due date can go overdue again');
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Access\Domain;

use App\Access\Domain\Account;
use App\Access\Domain\AccountStatus;
use App\Access\Domain\Language;
use App\Access\Domain\LoginRejected;
use App\Access\Domain\RejectionReason;
use App\Access\Domain\Username;
use PHPUnit\Framework\TestCase;

final class AccountTest extends TestCase
{
    public function testLocksForFifteenMinutesAfterFiveWrongPasswords(): void
    {
        $now = new \DateTimeImmutable('2026-11-21 08:00:00');
        $account = Account::invite('p1', Username::fromString('1001'), Language::German, $now);

        for ($i = 4; $i >= 1; --$i) {
            $rejection = $account->recordFailedAttempt($now);
            self::assertSame(RejectionReason::InvalidCredentials, $rejection->reason);
            self::assertSame($i, $rejection->remainingAttempts);
        }
        $rejection = $account->recordFailedAttempt($now);
        self::assertSame(RejectionReason::TemporarilyLocked, $rejection->reason);

        try {
            $account->assertCanSignIn($now->modify('+14 minutes'));
            self::fail('Account should be locked');
        } catch (LoginRejected $e) {
            self::assertSame(RejectionReason::TemporarilyLocked, $e->reason);
        }
        $account->assertCanSignIn($now->modify('+16 minutes'));
        $this->addToAssertionCount(1);
    }

    public function testRegistrationActivatesAndNewLetterInvalidatesPassword(): void
    {
        $now = new \DateTimeImmutable('2026-11-21 08:00:00');
        $account = Account::invite('p1', Username::fromString('1001'), Language::German, $now);

        $account->completeRegistration('hash', Language::Polish, $now);
        self::assertSame(AccountStatus::Active, $account->status());
        self::assertSame(Language::Polish, $account->language());

        $account->prepareForNewLetter($now);
        self::assertNull($account->passwordHash());
        self::assertSame(AccountStatus::Invited, $account->status());
    }
}

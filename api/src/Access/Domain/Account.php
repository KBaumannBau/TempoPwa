<?php

declare(strict_types=1);

namespace App\Access\Domain;

/**
 * Access of one person to the app (aggregate root, table tempo.account).
 * Holds the password hash and the temporary lock after too many failed attempts.
 */
class Account
{
    public const MAX_FAILED_ATTEMPTS = 5;
    private const LOCK_DURATION = 'PT15M';

    private string $username;
    private ?string $passwordHash = null;
    private int $failedAttempts = 0;
    private ?\DateTimeImmutable $lockedUntil = null;
    private ?\DateTimeImmutable $registeredAt = null;

    private function __construct(
        private string $personId,
        Username $username,
        private AccountStatus $status,
        private Language $language,
        private \DateTimeImmutable $updatedAt,
    ) {
        $this->username = $username->value;
    }

    public static function invite(string $personId, Username $username, Language $language, \DateTimeImmutable $now): self
    {
        return new self($personId, $username, AccountStatus::Invited, $language, $now);
    }

    public function personId(): string
    {
        return $this->personId;
    }

    public function username(): Username
    {
        return Username::fromString($this->username);
    }

    public function status(): AccountStatus
    {
        return $this->status;
    }

    public function language(): Language
    {
        return $this->language;
    }

    public function passwordHash(): ?string
    {
        return $this->passwordHash;
    }

    /** @throws LoginRejected */
    public function assertCanSignIn(\DateTimeImmutable $now): void
    {
        if ($this->status === AccountStatus::Locked) {
            throw LoginRejected::accountLocked();
        }
        if ($this->lockedUntil !== null && $this->lockedUntil > $now) {
            throw LoginRejected::temporarilyLocked($this->lockedUntil);
        }
    }

    /** Counts a wrong password and returns the rejection to throw (after the change has been saved). */
    public function recordFailedAttempt(\DateTimeImmutable $now): LoginRejected
    {
        ++$this->failedAttempts;
        $this->updatedAt = $now;
        if ($this->failedAttempts >= self::MAX_FAILED_ATTEMPTS) {
            $this->failedAttempts = 0;
            $this->lockedUntil = $now->add(new \DateInterval(self::LOCK_DURATION));

            return LoginRejected::temporarilyLocked($this->lockedUntil);
        }

        return LoginRejected::invalidCredentials(self::MAX_FAILED_ATTEMPTS - $this->failedAttempts);
    }

    public function recordSuccessfulSignIn(\DateTimeImmutable $now): void
    {
        $this->failedAttempts = 0;
        $this->lockedUntil = null;
        $this->updatedAt = $now;
    }

    /** First own password after the letter: the account becomes active. */
    public function completeRegistration(string $passwordHash, Language $language, \DateTimeImmutable $now): void
    {
        $this->passwordHash = $passwordHash;
        $this->status = AccountStatus::Active;
        $this->language = $language;
        $this->registeredAt ??= $now;
        $this->recordSuccessfulSignIn($now);
    }

    /** A new letter invalidates the current password (forgotten password, new phone without password). */
    public function prepareForNewLetter(\DateTimeImmutable $now): void
    {
        if ($this->status === AccountStatus::Locked) {
            throw LoginRejected::accountLocked();
        }
        $this->passwordHash = null;
        $this->status = AccountStatus::Invited;
        $this->updatedAt = $now;
    }

    public function rehashPassword(string $passwordHash, \DateTimeImmutable $now): void
    {
        $this->passwordHash = $passwordHash;
        $this->updatedAt = $now;
    }
}

<?php

declare(strict_types=1);

namespace App\Access\Domain;

/** Registration letter with a one-time password (table tempo.invitation). Valid for 14 days, usable once. */
class Invitation
{
    public const VALID_DAYS = 14;

    private ?\DateTimeImmutable $usedAt = null;
    private ?\DateTimeImmutable $revokedAt = null;

    private function __construct(
        private string $id,
        private string $personId,
        private string $secretHash,
        private ?string $issuedBy,
        private \DateTimeImmutable $issuedAt,
        private \DateTimeImmutable $validUntil,
    ) {
    }

    public static function issue(string $id, string $personId, string $secretHash, ?string $issuedBy, \DateTimeImmutable $now): self
    {
        return new self($id, $personId, $secretHash, $issuedBy, $now, $now->modify('+'.self::VALID_DAYS.' days'));
    }

    public function id(): string
    {
        return $this->id;
    }

    public function personId(): string
    {
        return $this->personId;
    }

    public function secretHash(): string
    {
        return $this->secretHash;
    }

    public function validUntil(): \DateTimeImmutable
    {
        return $this->validUntil;
    }

    /** Neither used nor replaced by a newer letter. Expiry is checked separately to give a precise message. */
    public function isOpen(): bool
    {
        return $this->usedAt === null && $this->revokedAt === null;
    }

    public function isExpired(\DateTimeImmutable $now): bool
    {
        return $this->validUntil < $now;
    }

    public function redeem(\DateTimeImmutable $now): void
    {
        $this->usedAt = $now;
    }

    public function revoke(\DateTimeImmutable $now): void
    {
        $this->revokedAt ??= $now;
    }
}

<?php

declare(strict_types=1);

namespace App\Access\Domain;

/** A registered phone (table tempo.device). One active device per person; it stays signed in until revoked. */
class Device
{
    /** Do not write on every request */
    private const TOUCH_INTERVAL = 'PT5M';

    private DeviceStatus $status = DeviceStatus::Active;
    private ?\DateTimeImmutable $lastSeenAt;
    private ?\DateTimeImmutable $signedOutAt = null;
    private ?string $signedOutBy = null;

    private function __construct(
        private string $id,
        private string $personId,
        private string $label,
        private string $keyHash,
        private \DateTimeImmutable $registeredAt,
    ) {
        $this->lastSeenAt = $registeredAt;
    }

    public static function register(DeviceKey $key, string $personId, string $label, \DateTimeImmutable $now): self
    {
        return new self($key->deviceId, $personId, mb_substr(trim($label) ?: 'Browser', 0, 80), $key->hash(), $now);
    }

    public function id(): string
    {
        return $this->id;
    }

    public function personId(): string
    {
        return $this->personId;
    }

    public function isActive(): bool
    {
        return $this->status === DeviceStatus::Active;
    }

    public function matches(DeviceKey $key): bool
    {
        return $key->deviceId === $this->id && hash_equals($this->keyHash, $key->hash());
    }

    public function signOut(\DateTimeImmutable $now, ?string $byPersonId): void
    {
        if (!$this->isActive()) {
            return;
        }
        $this->status = DeviceStatus::SignedOut;
        $this->signedOutAt = $now;
        $this->signedOutBy = $byPersonId;
    }

    /** Remembers the last contact; returns true if it was updated (then the cookie is renewed as well). */
    public function touch(\DateTimeImmutable $now): bool
    {
        if ($this->lastSeenAt !== null && $this->lastSeenAt->add(new \DateInterval(self::TOUCH_INTERVAL)) > $now) {
            return false;
        }
        $this->lastSeenAt = $now;

        return true;
    }
}

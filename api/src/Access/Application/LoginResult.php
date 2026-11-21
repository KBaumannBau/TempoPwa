<?php

declare(strict_types=1);

namespace App\Access\Application;

use App\Access\Domain\DeviceKey;
use App\Access\Domain\Language;

/** Either the phone is signed in (device key), or the person used the letter and must set an own password first. */
final readonly class LoginResult
{
    private function __construct(
        public string $personId,
        public ?DeviceKey $deviceKey,
        public ?RegistrationTicket $registration,
    ) {
    }

    public static function signedIn(string $personId, DeviceKey $key): self
    {
        return new self($personId, $key, null);
    }

    public static function passwordChangeRequired(string $personId, string $invitationId, Language $language, string $deviceLabel): self
    {
        return new self($personId, null, new RegistrationTicket($invitationId, $language, $deviceLabel));
    }

    public function isPasswordChangeRequired(): bool
    {
        return $this->registration !== null;
    }
}

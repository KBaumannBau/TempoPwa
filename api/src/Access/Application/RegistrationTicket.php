<?php

declare(strict_types=1);

namespace App\Access\Application;

use App\Access\Domain\Language;

/** Short-lived proof that the one-time password was correct. Allows exactly one thing: setting the own password. */
final readonly class RegistrationTicket
{
    public function __construct(
        public string $invitationId,
        public Language $language,
        public string $deviceLabel,
    ) {
    }
}

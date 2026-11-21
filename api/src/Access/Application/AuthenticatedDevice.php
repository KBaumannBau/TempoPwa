<?php

declare(strict_types=1);

namespace App\Access\Application;

final readonly class AuthenticatedDevice
{
    public function __construct(
        public string $personId,
        public string $deviceId,
        /** true when the last contact was updated; the cookie is then renewed (sliding expiry) */
        public bool $renewCredential,
    ) {
    }
}

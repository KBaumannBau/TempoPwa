<?php

declare(strict_types=1);

namespace App\Access\Application;

final readonly class IssuedLetter
{
    public function __construct(
        public string $username,
        #[\SensitiveParameter] public string $oneTimePassword,
        public \DateTimeImmutable $validUntil,
    ) {
    }
}

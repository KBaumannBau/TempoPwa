<?php

declare(strict_types=1);

namespace App\Access\Application;

use App\Access\Domain\Language;

final readonly class LoginCommand
{
    public function __construct(
        public string $username,
        #[\SensitiveParameter] public string $password,
        /** Display language chosen on the device; stored when the registration is completed */
        public Language $language,
        public string $deviceLabel,
        public string $clientKey,
    ) {
    }
}

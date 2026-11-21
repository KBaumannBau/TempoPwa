<?php

declare(strict_types=1);

namespace App\Access\Application;

final readonly class SetPasswordCommand
{
    public function __construct(
        public RegistrationTicket $ticket,
        #[\SensitiveParameter] public string $newPassword,
    ) {
    }
}

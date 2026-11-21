<?php

declare(strict_types=1);

namespace App\Access\Domain;

enum AccountStatus: string
{
    /** Person does not use the app (e.g. confirms hours with a PIN on the foreman's phone) */
    case None = 'none';
    /** A letter with a one-time password has been issued, registration not completed */
    case Invited = 'invited';
    case Active = 'active';
    /** Locked by the office (left the company, misuse). Not the temporary lock after failed attempts. */
    case Locked = 'locked';
}

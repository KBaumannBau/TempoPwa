<?php

declare(strict_types=1);

namespace App\Access\Application;

use App\Access\Domain\LoginRejected;

/**
 * Limits sign-in attempts per client (IP), independent of the account. Protects against trying many usernames.
 * The per-account limit (5 attempts, 15 minutes) lives in the Account aggregate.
 */
interface LoginThrottle
{
    /** @throws LoginRejected when the client has used up its attempts */
    public function consume(string $clientKey, \DateTimeImmutable $now): void;
}

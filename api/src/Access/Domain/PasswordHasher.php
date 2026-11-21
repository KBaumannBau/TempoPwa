<?php

declare(strict_types=1);

namespace App\Access\Domain;

/** Slow password hashing (argon2id/bcrypt). Used for own passwords and one-time passwords. */
interface PasswordHasher
{
    public function hash(string $plain): string;

    public function verify(string $hash, string $plain): bool;

    public function needsRehash(string $hash): bool;
}

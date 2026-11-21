<?php

declare(strict_types=1);

namespace App\Access\Infrastructure\Security;

use App\Access\Domain\PasswordHasher;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;

/** Uses the named hasher `access` from security.yaml (algorithm auto). */
final readonly class SymfonyPasswordHasher implements PasswordHasher
{
    private PasswordHasherInterface $hasher;

    public function __construct(PasswordHasherFactoryInterface $factory)
    {
        $this->hasher = $factory->getPasswordHasher('access');
    }

    public function hash(#[\SensitiveParameter] string $plain): string
    {
        return $this->hasher->hash($plain);
    }

    public function verify(string $hash, #[\SensitiveParameter] string $plain): bool
    {
        return $this->hasher->verify($hash, $plain);
    }

    public function needsRehash(string $hash): bool
    {
        return $this->hasher->needsRehash($hash);
    }
}

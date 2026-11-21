<?php

declare(strict_types=1);

namespace App\Access\Infrastructure\Security;

use Symfony\Component\Security\Core\User\UserInterface;

/** The signed-in phone of a person. Identified by the device cookie, not by a session. */
final readonly class AuthenticatedUser implements UserInterface
{
    public const ROLE = 'ROLE_DEVICE';

    public function __construct(public string $personId, public string $deviceId)
    {
    }

    public function getRoles(): array
    {
        return [self::ROLE];
    }

    public function getUserIdentifier(): string
    {
        return $this->personId;
    }
}

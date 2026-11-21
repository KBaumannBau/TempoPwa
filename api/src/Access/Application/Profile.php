<?php

declare(strict_types=1);

namespace App\Access\Application;

/** What the app needs to know about the signed-in person. */
final readonly class Profile
{
    /**
     * @param list<string> $roles all current role keys
     */
    public function __construct(
        public string $personId,
        public string $username,
        public string $firstName,
        public string $lastName,
        public string $primaryRole,
        public array $roles,
        public string $language,
    ) {
    }
}

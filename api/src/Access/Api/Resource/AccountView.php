<?php

declare(strict_types=1);

namespace App\Access\Api\Resource;

use ApiPlatform\Metadata\ApiProperty;
use App\Access\Application\Profile;

/** The signed-in person as the app sees it. */
final readonly class AccountView
{
    /**
     * @param list<string> $roles
     */
    public function __construct(
        #[ApiProperty(required: true, description: 'Person id (UUID)')]
        public string $personId,
        #[ApiProperty(required: true, description: 'Login name (personnel number)')]
        public string $username,
        #[ApiProperty(required: true)]
        public string $firstName,
        #[ApiProperty(required: true)]
        public string $lastName,
        #[ApiProperty(required: true, description: 'Role that decides the start page, e.g. skilled_worker, site_foreman, site_manager')]
        public string $role,
        #[ApiProperty(required: true, description: 'All current roles')]
        public array $roles,
        #[ApiProperty(required: true, description: 'Display language of the app: de, pl, ro, hr, en, fr, it')]
        public string $language,
    ) {
    }

    public static function fromProfile(Profile $profile): self
    {
        return new self($profile->personId, $profile->username, $profile->firstName, $profile->lastName, $profile->primaryRole, $profile->roles, $profile->language);
    }
}

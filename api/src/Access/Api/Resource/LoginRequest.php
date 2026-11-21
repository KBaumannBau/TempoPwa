<?php

declare(strict_types=1);

namespace App\Access\Api\Resource;

use ApiPlatform\Metadata\ApiProperty;
use App\Access\Domain\Language;
use Symfony\Component\Validator\Constraints as Assert;

final class LoginRequest
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 64)]
    #[ApiProperty(description: 'Personnel number', example: '1001')]
    public string $username = '';

    #[Assert\NotBlank]
    #[Assert\Length(max: 200)]
    #[ApiProperty(description: 'Own password, or the one-time password from the letter (XXXX-XXXX)')]
    public string $password = '';

    #[Assert\Choice(callback: [Language::class, 'codes'])]
    #[ApiProperty(description: 'Display language chosen on the device; stored with the first registration', example: 'de')]
    public string $language = 'de';

    #[Assert\Length(max: 80)]
    #[ApiProperty(description: 'Name of the phone for the device list', example: 'iPhone')]
    public string $deviceLabel = 'Browser';
}

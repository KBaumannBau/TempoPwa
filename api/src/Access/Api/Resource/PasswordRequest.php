<?php

declare(strict_types=1);

namespace App\Access\Api\Resource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Response;
use App\Access\Api\State\SetPasswordProcessor;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    shortName: 'Password',
    operations: [
        new Post(
            uriTemplate: '/auth/password',
            status: 204,
            output: false,
            processor: SetPasswordProcessor::class,
            openapi: new Operation(
                tags: ['Authentication'],
                summary: 'Set the own password after signing in with the letter',
                description: 'Needs the cookie `tempo_registration` from `POST /api/auth/login`. Rules: at least 8 characters, one letter and one digit, not the username or the one-time password. On success the account is active and the phone receives `tempo_device`.',
                responses: [401 => new Response(OpenApiResponses::UNAUTHORIZED), 403 => new Response(OpenApiResponses::FORBIDDEN), 410 => new Response(OpenApiResponses::GONE), 422 => new Response(OpenApiResponses::UNPROCESSABLE)],
            ),
        ),
    ],
    formats: ['json' => ['application/json']],
)]
final class PasswordRequest
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 200)]
    #[ApiProperty(description: 'New own password')]
    public string $newPassword = '';
}

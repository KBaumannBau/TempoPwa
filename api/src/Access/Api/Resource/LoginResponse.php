<?php

declare(strict_types=1);

namespace App\Access\Api\Resource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Response;
use App\Access\Api\State\LoginProcessor;

#[ApiResource(
    shortName: 'Login',
    operations: [
        new Post(
            uriTemplate: '/auth/login',
            status: 200,
            input: LoginRequest::class,
            processor: LoginProcessor::class,
            openapi: new Operation(
                tags: ['Authentication'],
                summary: 'Sign in with username and password',
                description: <<<'TXT'
                    With the own password: the phone is registered and receives the HttpOnly cookie `tempo_device`
                    (valid 400 days, renewed on use). A previously registered phone of the person is signed out.

                    With the one-time password from the letter: `passwordChangeRequired = true` and a short-lived cookie
                    `tempo_registration` (10 minutes). The only next step is `POST /api/auth/password`.
                    TXT,
                responses: [401 => new Response(OpenApiResponses::UNAUTHORIZED), 403 => new Response(OpenApiResponses::FORBIDDEN), 410 => new Response(OpenApiResponses::GONE), 422 => new Response(OpenApiResponses::UNPROCESSABLE), 429 => new Response(OpenApiResponses::TOO_MANY)],
            ),
        ),
    ],
    formats: ['json' => ['application/json']],
)]
final readonly class LoginResponse
{
    public function __construct(
        #[ApiProperty(required: true)]
        public AccountView $account,
        #[ApiProperty(required: true, description: 'true after signing in with the letter: set an own password next')]
        public bool $passwordChangeRequired,
    ) {
    }
}

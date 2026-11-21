<?php

declare(strict_types=1);

namespace App\Access\Api\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Response;
use App\Access\Api\State\LogoutProcessor;
use App\Access\Api\State\MeProvider;

/** Operations on the signed-in phone (authenticated by the cookie `tempo_device`). */
#[ApiResource(
    shortName: 'Me',
    operations: [
        new Get(
            uriTemplate: '/auth/me',
            output: AccountView::class,
            provider: MeProvider::class,
            openapi: new Operation(
                tags: ['Authentication'],
                summary: 'Who is signed in on this phone?',
                description: 'The app calls this on start and whenever it is online again. A signed-out device or a locked account is answered with 401 device_signed_out / 403 account_locked; the app then deletes its local data.',
                responses: [401 => new Response(OpenApiResponses::UNAUTHORIZED), 403 => new Response(OpenApiResponses::FORBIDDEN)],
            ),
        ),
        new Post(
            uriTemplate: '/auth/logout',
            status: 204,
            input: false,
            output: false,
            read: false,
            processor: LogoutProcessor::class,
            openapi: new Operation(
                tags: ['Authentication'],
                summary: 'Sign out this phone',
                description: 'The device is revoked immediately; the cookie is deleted.',
                responses: [401 => new Response(OpenApiResponses::UNAUTHORIZED)],
            ),
        ),
    ],
    formats: ['json' => ['application/json']],
)]
final class Me
{
}

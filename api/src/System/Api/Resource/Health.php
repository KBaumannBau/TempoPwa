<?php

declare(strict_types=1);

namespace App\System\Api\Resource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\OpenApi\Model\Operation;
use App\System\Api\State\HealthProvider;

/**
 * Smoke test: is the API running, can it reach the database?
 * Reachable without sign-in, returns no business data.
 */
#[ApiResource(
    shortName: 'Health',
    operations: [
        new Get(
            uriTemplate: '/health',
            openapi: new Operation(
                summary: 'Zustand der API',
                description: 'Prüft, ob die API läuft und die Datenbank erreichbar ist.',
            ),
            provider: HealthProvider::class,
        ),
    ],
    formats: ['json' => ['application/json']],
)]
final readonly class Health
{
    public const string OK = 'ok';
    public const string DEGRADED = 'degraded';
    public const string UNAVAILABLE = 'unavailable';

    public function __construct(
        #[ApiProperty(required: true, description: 'ok, wenn alle Prüfungen bestanden sind, sonst degraded')]
        public string $status,
        #[ApiProperty(required: true, description: 'ok oder unavailable')]
        public string $database,
        #[ApiProperty(required: true, description: 'Serverzeit (UTC)')]
        public \DateTimeImmutable $time,
        #[ApiProperty(required: true, description: 'Version der API, z. B. 0.2.0 oder 0.2.0+abc1234 (Build aus main)')]
        public string $version,
    ) {
    }
}

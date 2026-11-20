<?php

declare(strict_types=1);

namespace App\System\Api\Resource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\OpenApi\Model\Operation;
use App\System\Api\State\HealthProvider;

/**
 * Smoke-Test: Läuft die API, erreicht sie die Datenbank?
 * Ohne Anmeldung erreichbar, liefert keine Fachdaten.
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
    public function __construct(
        #[ApiProperty(required: true, description: 'ok, wenn alle Prüfungen bestanden sind, sonst gestoert')]
        public string $status,
        #[ApiProperty(required: true, description: 'ok oder gestoert')]
        public string $datenbank,
        #[ApiProperty(required: true, description: 'Serverzeit (UTC)')]
        public \DateTimeImmutable $zeit,
    ) {
    }
}

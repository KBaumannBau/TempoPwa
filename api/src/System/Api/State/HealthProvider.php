<?php

declare(strict_types=1);

namespace App\System\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\System\Api\Resource\Health;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;

/**
 * @implements ProviderInterface<Health>
 */
final readonly class HealthProvider implements ProviderInterface
{
    public function __construct(
        private Connection $connection,
        private ClockInterface $clock,
        private LoggerInterface $logger,
        #[Autowire(param: 'app.version')]
        private string $version,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): Health
    {
        $datenbank = $this->datenbankErreichbar() ? 'ok' : 'gestoert';

        return new Health(
            status: $datenbank === 'ok' ? 'ok' : 'gestoert',
            datenbank: $datenbank,
            zeit: $this->clock->now()->setTimezone(new \DateTimeZone('UTC')),
            version: $this->version,
        );
    }

    private function datenbankErreichbar(): bool
    {
        try {
            return (int) $this->connection->fetchOne('SELECT 1') === 1;
        } catch (\Throwable $e) {
            $this->logger->error('Health: Datenbank nicht erreichbar', ['exception' => $e]);

            return false;
        }
    }
}

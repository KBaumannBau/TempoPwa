<?php

declare(strict_types=1);

namespace App\System\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\System\Api\Resource\Health;
use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

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
        $databaseReachable = $this->isDatabaseReachable();

        return new Health(
            status: $databaseReachable ? Health::OK : Health::DEGRADED,
            database: $databaseReachable ? Health::OK : Health::UNAVAILABLE,
            time: $this->clock->now()->setTimezone(new \DateTimeZone('UTC')),
            version: $this->version,
        );
    }

    private function isDatabaseReachable(): bool
    {
        try {
            return (int) $this->connection->fetchOne('SELECT 1') === 1;
        } catch (\Throwable $e) {
            $this->logger->error('Health: database not reachable', ['exception' => $e]);

            return false;
        }
    }
}

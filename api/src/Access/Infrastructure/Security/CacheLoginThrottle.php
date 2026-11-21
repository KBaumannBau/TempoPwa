<?php

declare(strict_types=1);

namespace App\Access\Infrastructure\Security;

use App\Access\Application\LoginThrottle;
use App\Access\Domain\LoginRejected;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Fixed window per client: at most 30 sign-in attempts in 15 minutes.
 * Small on purpose; can be replaced by symfony/rate-limiter behind the same interface.
 */
final readonly class CacheLoginThrottle implements LoginThrottle
{
    private const LIMIT = 30;
    private const WINDOW_SECONDS = 900;

    public function __construct(private CacheItemPoolInterface $cache)
    {
    }

    public function consume(string $clientKey, \DateTimeImmutable $now): void
    {
        $item = $this->cache->getItem('access_login_'.hash('sha256', $clientKey));
        /** @var array{count: int, windowEnds: int}|null $state */
        $state = $item->isHit() ? $item->get() : null;
        if ($state === null || $state['windowEnds'] <= $now->getTimestamp()) {
            $state = ['count' => 0, 'windowEnds' => $now->getTimestamp() + self::WINDOW_SECONDS];
        }
        if ($state['count'] >= self::LIMIT) {
            throw LoginRejected::temporarilyLocked((new \DateTimeImmutable())->setTimestamp($state['windowEnds']));
        }
        ++$state['count'];
        $item->set($state);
        $item->expiresAt((new \DateTimeImmutable())->setTimestamp($state['windowEnds']));
        $this->cache->save($item);
    }
}

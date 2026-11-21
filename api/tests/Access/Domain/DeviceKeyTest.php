<?php

declare(strict_types=1);

namespace App\Tests\Access\Domain;

use App\Access\Domain\DeviceKey;
use PHPUnit\Framework\TestCase;

final class DeviceKeyTest extends TestCase
{
    public function testTokenRoundTrip(): void
    {
        $key = DeviceKey::generate();
        $parsed = DeviceKey::fromToken($key->token());

        self::assertNotNull($parsed);
        self::assertSame($key->deviceId, $parsed->deviceId);
        self::assertSame($key->hash(), $parsed->hash());
    }

    public function testRejectsGarbage(): void
    {
        self::assertNull(DeviceKey::fromToken('nonsense'));
        self::assertNull(DeviceKey::fromToken('00000000-0000-4000-8000-000000000001.c2hvcnQ'));
    }
}

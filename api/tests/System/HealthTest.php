<?php

declare(strict_types=1);

namespace App\Tests\System;

use ApiPlatform\Test\ApiTestCase;

final class HealthTest extends ApiTestCase
{
    protected static ?bool $alwaysBootKernel = true;

    public function testHealthReportsOkAndVersion(): void
    {
        $response = static::createClient()->request('GET', '/api/health', ['headers' => ['Accept' => 'application/json']]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains(['status' => 'ok', 'database' => 'ok']);
        self::assertMatchesRegularExpression('/^\d+\.\d+\.\d+/', (string) $response->toArray()['version']);
    }
}

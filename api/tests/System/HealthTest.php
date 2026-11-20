<?php

declare(strict_types=1);

namespace App\Tests\System;

use ApiPlatform\Test\ApiTestCase;

final class HealthTest extends ApiTestCase
{
    protected static ?bool $alwaysBootKernel = true;

    public function testHealthMeldetOk(): void
    {
        static::createClient()->request('GET', '/api/health', ['headers' => ['Accept' => 'application/json']]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains(['status' => 'ok', 'datenbank' => 'ok']);
    }
}

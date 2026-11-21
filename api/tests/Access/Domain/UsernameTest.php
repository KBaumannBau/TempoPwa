<?php

declare(strict_types=1);

namespace App\Tests\Access\Domain;

use App\Access\Domain\Username;
use PHPUnit\Framework\TestCase;

final class UsernameTest extends TestCase
{
    public function testIgnoresCaseAndWhitespace(): void
    {
        self::assertTrue(Username::fromString(' ab 1001 ')->equals(Username::fromString('AB1001')));
    }

    public function testRejectsEmpty(): void
    {
        self::assertNull(Username::tryFromString('   '));
    }
}

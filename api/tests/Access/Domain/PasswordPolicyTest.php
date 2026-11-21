<?php

declare(strict_types=1);

namespace App\Tests\Access\Domain;

use App\Access\Domain\PasswordPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PasswordPolicyTest extends TestCase
{
    /** @return iterable<string, array{string, ?string}> */
    public static function cases(): iterable
    {
        yield 'too short' => ['abc1', 'Mindestens 8 Zeichen.'];
        yield 'no digit' => ['Baustelle', 'Mindestens ein Buchstabe und eine Ziffer.'];
        yield 'no letter' => ['12345678', 'Mindestens ein Buchstabe und eine Ziffer.'];
        yield 'umlaut counts as letter' => ['Größe2026', null];
        yield 'same as one-time password' => ['k7q4-mx2p', 'Bitte ein neues Passwort wählen, nicht das aus dem Brief oder das bisherige.'];
        yield 'fine' => ['Baustelle-2026', null];
    }

    #[DataProvider('cases')]
    public function testRules(string $password, ?string $expected): void
    {
        self::assertSame($expected, PasswordPolicy::violation($password, ['K7Q4MX2P', '1001']));
    }
}

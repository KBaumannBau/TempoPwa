<?php

declare(strict_types=1);

namespace App\Access\Domain;

/** Display language of the mobile app. Office and supervisor screens are always German. */
enum Language: string
{
    case German = 'de';
    case Polish = 'pl';
    case Romanian = 'ro';
    case Croatian = 'hr';
    case English = 'en';
    case French = 'fr';
    case Italian = 'it';

    /** @return list<string> */
    public static function codes(): array
    {
        return array_map(static fn (self $l): string => $l->value, self::cases());
    }

    public static function fromCodeOrDefault(?string $code): self
    {
        return self::tryFrom((string) $code) ?? self::German;
    }
}

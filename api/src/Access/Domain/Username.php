<?php

declare(strict_types=1);

namespace App\Access\Domain;

/**
 * Login name of a person. For own staff this is the personnel number; subcontractor
 * staff will get an issued identifier later. Case and whitespace do not matter.
 */
final readonly class Username
{
    private const MAX_LENGTH = 64;

    private function __construct(public string $value)
    {
    }

    public static function fromString(string $raw): self
    {
        $value = self::normalize($raw);
        if ($value === '' || mb_strlen($value) > self::MAX_LENGTH) {
            throw new \InvalidArgumentException('Invalid username.');
        }

        return new self($value);
    }

    public static function tryFromString(string $raw): ?self
    {
        try {
            return self::fromString($raw);
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    private static function normalize(string $raw): string
    {
        return mb_strtoupper((string) preg_replace('/\s+/u', '', trim($raw)));
    }
}

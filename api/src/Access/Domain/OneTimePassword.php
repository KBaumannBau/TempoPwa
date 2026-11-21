<?php

declare(strict_types=1);

namespace App\Access\Domain;

/** One-time password printed on the registration letter, format XXXX-XXXX, without easily confused characters. */
final readonly class OneTimePassword
{
    private const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    private function __construct(public string $value)
    {
    }

    public static function generate(): self
    {
        $characters = '';
        for ($i = 0; $i < 8; ++$i) {
            $characters .= self::ALPHABET[random_int(0, \strlen(self::ALPHABET) - 1)];
        }

        return new self(substr($characters, 0, 4).'-'.substr($characters, 4));
    }

    /** Form that gets hashed and compared: upper case, without spaces and dashes */
    public static function normalize(string $input): string
    {
        return strtoupper((string) preg_replace('/[\s-]/', '', trim($input)));
    }

    public function normalized(): string
    {
        return self::normalize($this->value);
    }
}

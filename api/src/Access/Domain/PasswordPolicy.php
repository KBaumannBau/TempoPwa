<?php

declare(strict_types=1);

namespace App\Access\Domain;

/**
 * Rules for a person's own password (from TempoVibe src/domain/zugang.ts). The app shows the same rules as a preview;
 * this class is binding. Messages are German because they double as translation keys in the app.
 */
final class PasswordPolicy
{
    public const MIN_LENGTH = 8;

    /**
     * @param list<string> $forbidden values the new password must not equal (username, one-time password)
     */
    public static function violation(string $password, array $forbidden = []): ?string
    {
        if (mb_strlen($password) < self::MIN_LENGTH) {
            return 'Mindestens 8 Zeichen.';
        }
        if (preg_match('/\p{L}/u', $password) !== 1 || preg_match('/\d/', $password) !== 1) {
            return 'Mindestens ein Buchstabe und eine Ziffer.';
        }
        foreach ($forbidden as $value) {
            if ($value !== '' && self::normalize($value) === self::normalize($password)) {
                return 'Bitte ein neues Passwort wählen, nicht das aus dem Brief oder das bisherige.';
            }
        }

        return null;
    }

    private static function normalize(string $value): string
    {
        return mb_strtoupper((string) preg_replace('/[\s-]/u', '', trim($value)));
    }
}

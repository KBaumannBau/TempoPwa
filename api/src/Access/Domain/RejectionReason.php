<?php

declare(strict_types=1);

namespace App\Access\Domain;

/** Machine-readable reasons; the API returns them as `code`, the app shows its own translated text. */
enum RejectionReason: string
{
    case InvalidCredentials = 'invalid_credentials';
    case TemporarilyLocked = 'temporarily_locked';
    case AccountLocked = 'account_locked';
    case OneTimePasswordExpired = 'one_time_password_expired';
    case RegistrationExpired = 'registration_expired';
    case PasswordPolicyViolated = 'password_policy_violated';
    case DeviceSignedOut = 'device_signed_out';
    case Unauthenticated = 'unauthenticated';

    public function httpStatus(): int
    {
        return match ($this) {
            self::InvalidCredentials, self::RegistrationExpired, self::DeviceSignedOut, self::Unauthenticated => 401,
            self::AccountLocked => 403,
            self::OneTimePasswordExpired => 410,
            self::PasswordPolicyViolated => 422,
            self::TemporarilyLocked => 429,
        };
    }
}

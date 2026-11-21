<?php

declare(strict_types=1);

namespace App\Access\Api\Resource;

/** Descriptions of the documented rejections (application/problem+json with `code`) of the access operations. */
final class OpenApiResponses
{
    public const UNAUTHORIZED = 'Rejected: invalid_credentials (with remainingAttempts), registration_expired, device_signed_out or unauthenticated';
    public const FORBIDDEN = 'Rejected: account_locked (locked by the office)';
    public const GONE = 'Rejected: one_time_password_expired (letter older than 14 days)';
    public const UNPROCESSABLE = 'Rejected: password_policy_violated (detail holds the rule) or invalid input';
    public const TOO_MANY = 'Rejected: temporarily_locked (5 wrong passwords or too many attempts from this client; lockedUntil, Retry-After)';
}

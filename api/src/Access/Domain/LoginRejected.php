<?php

declare(strict_types=1);

namespace App\Access\Domain;

/**
 * A sign-in or device check was rejected for a business reason.
 * `detail` is a German text; the app uses it as a translation key where it has no own text.
 */
final class LoginRejected extends \DomainException
{
    private function __construct(
        public readonly RejectionReason $reason,
        string $detail,
        public readonly ?int $remainingAttempts = null,
        public readonly ?\DateTimeImmutable $lockedUntil = null,
    ) {
        parent::__construct($detail);
    }

    public static function invalidCredentials(int $remainingAttempts): self
    {
        return new self(RejectionReason::InvalidCredentials, 'Benutzername oder Passwort stimmt nicht.', remainingAttempts: max(0, $remainingAttempts));
    }

    public static function temporarilyLocked(\DateTimeImmutable $until): self
    {
        return new self(RejectionReason::TemporarilyLocked, 'Zu viele falsche Eingaben.', lockedUntil: $until);
    }

    public static function accountLocked(): self
    {
        return new self(RejectionReason::AccountLocked, 'Dein Zugang ist gesperrt. Bitte melde dich in der Lohnbuchhaltung.');
    }

    public static function oneTimePasswordExpired(): self
    {
        return new self(RejectionReason::OneTimePasswordExpired, 'Das Einmal-Passwort ist abgelaufen. Die Lohnbuchhaltung schickt dir einen neuen Brief.');
    }

    public static function registrationExpired(): self
    {
        return new self(RejectionReason::RegistrationExpired, 'Die Anmeldung mit dem Brief ist abgelaufen. Bitte melde dich noch einmal mit dem Einmal-Passwort an.');
    }

    public static function passwordPolicyViolated(string $germanMessage): self
    {
        return new self(RejectionReason::PasswordPolicyViolated, $germanMessage);
    }

    public static function unauthenticated(): self
    {
        return new self(RejectionReason::Unauthenticated, 'Nicht angemeldet.');
    }

    public static function deviceSignedOut(): self
    {
        return new self(RejectionReason::DeviceSignedOut, 'Es wurde von dir oder von der Lohnbuchhaltung abgemeldet. Von hier kann nichts mehr gesendet werden.');
    }
}

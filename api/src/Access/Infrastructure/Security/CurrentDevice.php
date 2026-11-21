<?php

declare(strict_types=1);

namespace App\Access\Infrastructure\Security;

use App\Access\Domain\LoginRejected;
use App\Access\Domain\RejectionReason;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Gives operations the signed-in device or rejects with a precise code (device signed out, account locked,
 * not signed in) instead of a generic 403.
 */
final readonly class CurrentDevice
{
    public function __construct(private Security $security, private RequestStack $requests)
    {
    }

    /** @throws LoginRejected */
    public function require(): AuthenticatedUser
    {
        $user = $this->security->getUser();
        if ($user instanceof AuthenticatedUser) {
            return $user;
        }

        throw match ($this->requests->getCurrentRequest()?->attributes->get(DeviceCookieAuthenticator::REJECTION_ATTRIBUTE)) {
            RejectionReason::DeviceSignedOut => LoginRejected::deviceSignedOut(),
            RejectionReason::AccountLocked => LoginRejected::accountLocked(),
            default => LoginRejected::unauthenticated(),
        };
    }
}

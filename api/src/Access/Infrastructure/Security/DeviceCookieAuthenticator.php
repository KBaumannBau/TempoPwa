<?php

declare(strict_types=1);

namespace App\Access\Infrastructure\Security;

use App\Access\Application\VerifyDeviceHandler;
use App\Access\Domain\DeviceKey;
use App\Access\Domain\LoginRejected;
use App\Access\Domain\RejectionReason;
use App\Access\Infrastructure\Http\AccessProblemResponder;
use App\Access\Infrastructure\Http\AuthCookies;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

/**
 * Authenticates every API request that carries the device cookie. Stateless: no session, one indexed lookup.
 * A rejected cookie does not block the request; public operations (sign-in) keep working, protected ones
 * answer with the reason via CurrentDevice.
 */
final class DeviceCookieAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    public const REJECTION_ATTRIBUTE = '_access_rejection';

    public function __construct(
        private readonly VerifyDeviceHandler $verifyDevice,
        private readonly AuthCookies $cookies,
        private readonly AccessProblemResponder $problems,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return $this->cookies->deviceToken($request) !== null;
    }

    public function authenticate(Request $request): Passport
    {
        $key = DeviceKey::fromToken((string) $this->cookies->deviceToken($request))
            ?? throw new CustomUserMessageAuthenticationException(RejectionReason::DeviceSignedOut->value);
        try {
            $device = $this->verifyDevice->handle($key);
        } catch (LoginRejected $e) {
            throw new CustomUserMessageAuthenticationException($e->reason->value, previous: $e);
        }
        if ($device->renewCredential) {
            $this->cookies->issueDevice($key);
        }

        return new SelfValidatingPassport(new UserBadge(
            $device->personId,
            static fn (): AuthenticatedUser => new AuthenticatedUser($device->personId, $device->deviceId),
        ));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $reason = RejectionReason::tryFrom($exception->getMessageKey()) ?? RejectionReason::DeviceSignedOut;
        $request->attributes->set(self::REJECTION_ATTRIBUTE, $reason);
        if ($reason === RejectionReason::DeviceSignedOut) {
            $this->cookies->expireDevice();
        }

        return null;
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return $this->problems->respond(LoginRejected::unauthenticated());
    }
}

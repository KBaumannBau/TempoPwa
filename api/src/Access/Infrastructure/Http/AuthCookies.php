<?php

declare(strict_types=1);

namespace App\Access\Infrastructure\Http;

use App\Access\Domain\DeviceKey;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

/**
 * The two HttpOnly cookies of the access context. Processors and the authenticator queue cookies here;
 * AuthCookieListener writes them to the response.
 *
 * Secure is left to Symfony (null = secure when the request came via HTTPS), so local http://localhost works.
 */
final readonly class AuthCookies
{
    public const DEVICE = 'tempo_device';
    public const REGISTRATION = 'tempo_registration';
    /** Browsers cap cookie lifetime at 400 days; the cookie is renewed on use (sliding) */
    private const DEVICE_LIFETIME = '+400 days';
    private const QUEUE_ATTRIBUTE = '_access_cookies';

    public function __construct(private RequestStack $requests, private ClockInterface $clock)
    {
    }

    public function deviceToken(Request $request): ?string
    {
        $value = $request->cookies->get(self::DEVICE);

        return \is_string($value) && $value !== '' ? $value : null;
    }

    public function registrationToken(): ?string
    {
        $value = $this->requests->getMainRequest()?->cookies->get(self::REGISTRATION);

        return \is_string($value) && $value !== '' ? $value : null;
    }

    public function issueDevice(DeviceKey $key): void
    {
        $this->queue(Cookie::create(self::DEVICE, $key->token(), $this->clock->now()->modify(self::DEVICE_LIFETIME), '/api', null, null, true, false, Cookie::SAMESITE_STRICT));
    }

    public function expireDevice(): void
    {
        $this->queue(Cookie::create(self::DEVICE, null, 1, '/api', null, null, true, false, Cookie::SAMESITE_STRICT));
    }

    public function issueRegistration(string $token, int $ttlSeconds): void
    {
        $this->queue(Cookie::create(self::REGISTRATION, $token, $this->clock->now()->modify('+'.$ttlSeconds.' seconds'), '/api/auth', null, null, true, false, Cookie::SAMESITE_STRICT));
    }

    public function expireRegistration(): void
    {
        $this->queue(Cookie::create(self::REGISTRATION, null, 1, '/api/auth', null, null, true, false, Cookie::SAMESITE_STRICT));
    }

    public function applyTo(Request $request, Response $response): void
    {
        /** @var array<string, Cookie> $queued */
        $queued = $request->attributes->get(self::QUEUE_ATTRIBUTE, []);
        if ($queued === []) {
            return;
        }
        foreach ($queued as $cookie) {
            $response->headers->setCookie($cookie);
        }
        $response->headers->set('Cache-Control', 'no-store');
    }

    private function queue(Cookie $cookie): void
    {
        $request = $this->requests->getMainRequest() ?? throw new \LogicException('No request to attach the cookie to.');
        /** @var array<string, Cookie> $queued */
        $queued = $request->attributes->get(self::QUEUE_ATTRIBUTE, []);
        $queued[$cookie->getName()] = $cookie;
        $request->attributes->set(self::QUEUE_ATTRIBUTE, $queued);
    }
}

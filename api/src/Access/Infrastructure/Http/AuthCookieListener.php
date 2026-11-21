<?php

declare(strict_types=1);

namespace App\Access\Infrastructure\Http;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/** Writes queued access cookies to the response, also to error responses (e.g. expire a revoked device cookie). */
#[AsEventListener(event: KernelEvents::RESPONSE)]
final readonly class AuthCookieListener
{
    public function __construct(private AuthCookies $cookies)
    {
    }

    public function __invoke(ResponseEvent $event): void
    {
        if ($event->isMainRequest()) {
            $this->cookies->applyTo($event->getRequest(), $event->getResponse());
        }
    }
}

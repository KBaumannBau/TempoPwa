<?php

declare(strict_types=1);

namespace App\Access\Infrastructure\Http;

use App\Access\Domain\LoginRejected;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/** Turns business rejections into problem+json before the generic error handling of API Platform runs. */
#[AsEventListener(event: KernelEvents::EXCEPTION, priority: 10)]
final readonly class LoginRejectedListener
{
    public function __construct(private AccessProblemResponder $problems)
    {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        while (!$exception instanceof LoginRejected && $exception->getPrevious() !== null) {
            $exception = $exception->getPrevious();
        }
        if ($exception instanceof LoginRejected) {
            $event->setResponse($this->problems->respond($exception));
        }
    }
}

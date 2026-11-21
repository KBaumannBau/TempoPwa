<?php

declare(strict_types=1);

namespace App\Access\Infrastructure\Http;

use App\Access\Domain\LoginRejected;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * RFC 9457 problem details with the machine-readable `code` the app switches on.
 * Example: {"type": "/errors/access/invalid_credentials", "status": 401, "code": "invalid_credentials", "remainingAttempts": 3, …}
 */
final readonly class AccessProblemResponder
{
    public function respond(LoginRejected $rejection): JsonResponse
    {
        $body = [
            'type' => '/errors/access/'.$rejection->reason->value,
            'title' => 'Access rejected',
            'status' => $rejection->reason->httpStatus(),
            'detail' => $rejection->getMessage(),
            'code' => $rejection->reason->value,
        ];
        if ($rejection->remainingAttempts !== null) {
            $body['remainingAttempts'] = $rejection->remainingAttempts;
        }
        if ($rejection->lockedUntil !== null) {
            $body['lockedUntil'] = $rejection->lockedUntil->setTimezone(new \DateTimeZone('UTC'))->format(\DATE_ATOM);
        }

        $response = new JsonResponse($body, $rejection->reason->httpStatus(), ['Content-Type' => 'application/problem+json', 'Cache-Control' => 'no-store']);
        if ($rejection->lockedUntil !== null) {
            $response->headers->set('Retry-After', (string) max(1, $rejection->lockedUntil->getTimestamp() - time()));
        }

        return $response;
    }
}

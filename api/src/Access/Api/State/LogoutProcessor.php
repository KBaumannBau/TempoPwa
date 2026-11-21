<?php

declare(strict_types=1);

namespace App\Access\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Access\Application\SignOutHandler;
use App\Access\Infrastructure\Http\AuthCookies;
use App\Access\Infrastructure\Security\CurrentDevice;

/**
 * @implements ProcessorInterface<null, null>
 */
final readonly class LogoutProcessor implements ProcessorInterface
{
    public function __construct(
        private CurrentDevice $currentDevice,
        private SignOutHandler $signOut,
        private AuthCookies $cookies,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        $user = $this->currentDevice->require();
        $this->signOut->handle($user->deviceId, $user->personId);
        $this->cookies->expireDevice();

        return null;
    }
}

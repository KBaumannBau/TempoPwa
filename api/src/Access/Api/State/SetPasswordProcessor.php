<?php

declare(strict_types=1);

namespace App\Access\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Access\Api\Resource\PasswordRequest;
use App\Access\Application\SetPasswordCommand;
use App\Access\Application\SetPasswordHandler;
use App\Access\Domain\LoginRejected;
use App\Access\Infrastructure\Http\AuthCookies;
use App\Access\Infrastructure\Security\RegistrationTicketCodec;

/**
 * @implements ProcessorInterface<PasswordRequest, null>
 */
final readonly class SetPasswordProcessor implements ProcessorInterface
{
    public function __construct(
        private SetPasswordHandler $setPassword,
        private AuthCookies $cookies,
        private RegistrationTicketCodec $tickets,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        \assert($data instanceof PasswordRequest);

        $ticket = $this->tickets->decode($this->cookies->registrationToken()) ?? throw LoginRejected::registrationExpired();
        $result = $this->setPassword->handle(new SetPasswordCommand($ticket, $data->newPassword));

        if ($result->deviceKey !== null) {
            $this->cookies->issueDevice($result->deviceKey);
        }
        $this->cookies->expireRegistration();

        return null;
    }
}

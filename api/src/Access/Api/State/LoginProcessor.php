<?php

declare(strict_types=1);

namespace App\Access\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Access\Api\Resource\AccountView;
use App\Access\Api\Resource\LoginRequest;
use App\Access\Api\Resource\LoginResponse;
use App\Access\Application\LoginCommand;
use App\Access\Application\LoginHandler;
use App\Access\Application\ProfileReader;
use App\Access\Domain\Language;
use App\Access\Infrastructure\Http\AuthCookies;
use App\Access\Infrastructure\Security\RegistrationTicketCodec;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @implements ProcessorInterface<LoginRequest, LoginResponse>
 */
final readonly class LoginProcessor implements ProcessorInterface
{
    public function __construct(
        private LoginHandler $login,
        private ProfileReader $profiles,
        private AuthCookies $cookies,
        private RegistrationTicketCodec $tickets,
        private RequestStack $requests,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): LoginResponse
    {
        \assert($data instanceof LoginRequest);

        $result = $this->login->handle(new LoginCommand(
            username: $data->username,
            password: $data->password,
            language: Language::fromCodeOrDefault($data->language),
            deviceLabel: $data->deviceLabel,
            clientKey: $this->requests->getMainRequest()?->getClientIp() ?? 'unknown',
        ));

        if ($result->registration !== null) {
            $this->cookies->issueRegistration($this->tickets->encode($result->registration), RegistrationTicketCodec::TTL_SECONDS);
        } elseif ($result->deviceKey !== null) {
            $this->cookies->issueDevice($result->deviceKey);
            $this->cookies->expireRegistration();
        }

        return new LoginResponse(AccountView::fromProfile($this->profiles->forPerson($result->personId)), $result->isPasswordChangeRequired());
    }
}

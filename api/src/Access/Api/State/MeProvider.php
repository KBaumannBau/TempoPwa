<?php

declare(strict_types=1);

namespace App\Access\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Access\Api\Resource\AccountView;
use App\Access\Application\ProfileReader;
use App\Access\Infrastructure\Security\CurrentDevice;

/**
 * @implements ProviderInterface<AccountView>
 */
final readonly class MeProvider implements ProviderInterface
{
    public function __construct(private CurrentDevice $currentDevice, private ProfileReader $profiles)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): AccountView
    {
        return AccountView::fromProfile($this->profiles->forPerson($this->currentDevice->require()->personId));
    }
}

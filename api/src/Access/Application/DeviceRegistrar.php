<?php

declare(strict_types=1);

namespace App\Access\Application;

use App\Access\Domain\Device;
use App\Access\Domain\DeviceKey;
use App\Access\Domain\DeviceRepository;

/** Registers a new phone for a person. There is only one active device per person: the previous one is signed out. */
final readonly class DeviceRegistrar
{
    public function __construct(private DeviceRepository $devices)
    {
    }

    public function register(string $personId, string $label, \DateTimeImmutable $now): DeviceKey
    {
        $this->devices->findActiveForPerson($personId)?->signOut($now, $personId);
        $key = DeviceKey::generate();
        $this->devices->add(Device::register($key, $personId, $label, $now));

        return $key;
    }
}

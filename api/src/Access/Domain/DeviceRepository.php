<?php

declare(strict_types=1);

namespace App\Access\Domain;

interface DeviceRepository
{
    public function find(string $id): ?Device;

    public function findActiveForPerson(string $personId): ?Device;

    public function add(Device $device): void;
}

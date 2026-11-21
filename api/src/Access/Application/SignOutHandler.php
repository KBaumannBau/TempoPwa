<?php

declare(strict_types=1);

namespace App\Access\Application;

use App\Access\Domain\DeviceRepository;
use Psr\Clock\ClockInterface;

/** The person signs out this phone. Everything sent from it afterwards is rejected. */
final readonly class SignOutHandler
{
    public function __construct(
        private DeviceRepository $devices,
        private UnitOfWork $unitOfWork,
        private ClockInterface $clock,
    ) {
    }

    public function handle(string $deviceId, string $personId): void
    {
        $this->devices->find($deviceId)?->signOut($this->clock->now(), $personId);
        $this->unitOfWork->commit();
    }
}

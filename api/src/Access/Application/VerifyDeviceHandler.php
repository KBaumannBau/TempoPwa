<?php

declare(strict_types=1);

namespace App\Access\Application;

use App\Access\Domain\AccountRepository;
use App\Access\Domain\AccountStatus;
use App\Access\Domain\DeviceKey;
use App\Access\Domain\DeviceRepository;
use App\Access\Domain\LoginRejected;
use Psr\Clock\ClockInterface;

/** Checks the device credential on every request. Revocation takes effect immediately. */
final readonly class VerifyDeviceHandler
{
    public function __construct(
        private DeviceRepository $devices,
        private AccountRepository $accounts,
        private UnitOfWork $unitOfWork,
        private ClockInterface $clock,
    ) {
    }

    /** @throws LoginRejected */
    public function handle(DeviceKey $key): AuthenticatedDevice
    {
        $device = $this->devices->find($key->deviceId);
        if ($device === null || !$device->isActive() || !$device->matches($key)) {
            throw LoginRejected::deviceSignedOut();
        }
        $account = $this->accounts->findByPersonId($device->personId());
        if ($account === null || $account->status() === AccountStatus::Locked) {
            throw LoginRejected::accountLocked();
        }

        $touched = $device->touch($this->clock->now());
        if ($touched) {
            $this->unitOfWork->commit();
        }

        return new AuthenticatedDevice($device->personId(), $device->id(), $touched);
    }
}

<?php

declare(strict_types=1);

namespace App\Access\Infrastructure\Doctrine;

use App\Access\Domain\Device;
use App\Access\Domain\DeviceRepository;
use App\Access\Domain\DeviceStatus;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineDeviceRepository implements DeviceRepository
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function find(string $id): ?Device
    {
        return $this->em->find(Device::class, $id);
    }

    public function findActiveForPerson(string $personId): ?Device
    {
        return $this->em->getRepository(Device::class)->findOneBy(['personId' => $personId, 'status' => DeviceStatus::Active]);
    }

    public function add(Device $device): void
    {
        $this->em->persist($device);
    }
}

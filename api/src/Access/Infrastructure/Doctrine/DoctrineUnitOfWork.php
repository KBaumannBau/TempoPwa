<?php

declare(strict_types=1);

namespace App\Access\Infrastructure\Doctrine;

use App\Access\Application\UnitOfWork;
use Doctrine\ORM\EntityManagerInterface;

/** flush() runs in one transaction; the one-device-per-person constraint is checked at commit (deferred). */
final readonly class DoctrineUnitOfWork implements UnitOfWork
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function commit(): void
    {
        $this->em->flush();
    }
}

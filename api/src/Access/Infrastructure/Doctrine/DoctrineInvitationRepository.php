<?php

declare(strict_types=1);

namespace App\Access\Infrastructure\Doctrine;

use App\Access\Domain\Invitation;
use App\Access\Domain\InvitationRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineInvitationRepository implements InvitationRepository
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function find(string $id): ?Invitation
    {
        return $this->em->find(Invitation::class, $id);
    }

    public function findOpenForPerson(string $personId): ?Invitation
    {
        return $this->findAllOpenForPerson($personId)[0] ?? null;
    }

    public function findAllOpenForPerson(string $personId): array
    {
        /** @var list<Invitation> */
        return $this->em->createQueryBuilder()
            ->select('i')
            ->from(Invitation::class, 'i')
            ->where('i.personId = :person')
            ->andWhere('i.usedAt IS NULL')
            ->andWhere('i.revokedAt IS NULL')
            ->orderBy('i.issuedAt', 'DESC')
            ->setParameter('person', $personId)
            ->getQuery()
            ->getResult();
    }

    public function add(Invitation $invitation): void
    {
        $this->em->persist($invitation);
    }
}

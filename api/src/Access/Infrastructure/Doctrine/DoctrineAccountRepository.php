<?php

declare(strict_types=1);

namespace App\Access\Infrastructure\Doctrine;

use App\Access\Domain\Account;
use App\Access\Domain\AccountRepository;
use App\Access\Domain\Username;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineAccountRepository implements AccountRepository
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function findByUsername(Username $username): ?Account
    {
        return $this->em->getRepository(Account::class)->findOneBy(['username' => $username->value]);
    }

    public function findByPersonId(string $personId): ?Account
    {
        return $this->em->find(Account::class, $personId);
    }

    public function add(Account $account): void
    {
        $this->em->persist($account);
    }
}

<?php

declare(strict_types=1);

namespace App\Access\Infrastructure\Console;

use App\Access\Application\UnitOfWork;
use App\Access\Domain\Account;
use App\Access\Domain\AccountRepository;
use App\Access\Domain\Language;
use App\Access\Domain\Username;
use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Uid\Uuid;

/**
 * Creates a person with role and an invited account. Interim tool until the office screens
 * (Personnel context, /pc/mitarbeiter) exist; use only invented persons in test environments.
 */
#[AsCommand(name: 'tempo:access:create-person', description: 'Create a person with a role and an invited account')]
final readonly class CreatePersonCommand
{
    public function __construct(
        private Connection $connection,
        private AccountRepository $accounts,
        private UnitOfWork $unitOfWork,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'Personnel number, also the username')] string $personnelNumber,
        #[Argument] string $firstName,
        #[Argument] string $lastName,
        #[Option(description: 'Role key, e.g. skilled_worker, site_foreman, site_manager')] string $role = 'skilled_worker',
        #[Option(description: 'wage_earner, salaried or apprentice')] string $employment = 'wage_earner',
        #[Option(description: 'de, pl, ro, hr, en, fr, it')] string $language = 'de',
    ): int {
        $id = Uuid::v4()->toRfc4122();
        $this->connection->transactional(function () use ($id, $personnelNumber, $firstName, $lastName, $role, $employment, $language): void {
            $this->connection->insert('tempo.person', [
                'id' => $id, 'personnel_number' => $personnelNumber, 'first_name' => $firstName,
                'last_name' => $lastName, 'employment_type' => $employment,
            ]);
            $this->connection->insert('tempo.person_role', ['person_id' => $id, 'role' => $role]);
            $this->accounts->add(Account::invite($id, Username::fromString($personnelNumber), Language::fromCodeOrDefault($language), $this->clock->now()));
            $this->unitOfWork->commit();
        });

        $io->success(\sprintf('Person %s %s created (%s), username %s. Next: tempo:access:issue-letter %s', $firstName, $lastName, $id, $personnelNumber, $personnelNumber));

        return Command::SUCCESS;
    }
}

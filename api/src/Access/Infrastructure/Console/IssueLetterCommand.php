<?php

declare(strict_types=1);

namespace App\Access\Infrastructure\Console;

use App\Access\Application\IssueLetterHandler;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/** Issues a registration letter and prints the one-time password once. Interim tool until the office screen exists. */
#[AsCommand(name: 'tempo:access:issue-letter', description: 'Issue a registration letter with a one-time password')]
final readonly class IssueLetterCommand
{
    public function __construct(private IssueLetterHandler $issueLetter)
    {
    }

    public function __invoke(SymfonyStyle $io, #[Argument(description: 'Username (personnel number)')] string $username): int
    {
        $letter = $this->issueLetter->handle($username);
        $io->definitionList(
            ['Username' => $letter->username],
            ['One-time password' => $letter->oneTimePassword],
            ['Valid until' => $letter->validUntil->setTimezone(new \DateTimeZone('Europe/Berlin'))->format('d.m.Y H:i')],
        );
        $io->note('Older letters and the current password of this person are no longer valid.');

        return Command::SUCCESS;
    }
}

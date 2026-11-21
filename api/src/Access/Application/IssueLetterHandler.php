<?php

declare(strict_types=1);

namespace App\Access\Application;

use App\Access\Domain\AccountRepository;
use App\Access\Domain\Invitation;
use App\Access\Domain\InvitationRepository;
use App\Access\Domain\OneTimePassword;
use App\Access\Domain\PasswordHasher;
use App\Access\Domain\Username;
use Psr\Clock\ClockInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Issues a registration letter: new one-time password (valid 14 days), older letters and the current password
 * become invalid. The plain one-time password is returned once for printing and never stored.
 */
final readonly class IssueLetterHandler
{
    public function __construct(
        private AccountRepository $accounts,
        private InvitationRepository $invitations,
        private PasswordHasher $hasher,
        private UnitOfWork $unitOfWork,
        private ClockInterface $clock,
    ) {
    }

    public function handle(string $username, ?string $issuedBy = null): IssuedLetter
    {
        $now = $this->clock->now();
        $account = $this->accounts->findByUsername(Username::fromString($username))
            ?? throw new \DomainException(\sprintf('No account for username "%s".', $username));

        foreach ($this->invitations->findAllOpenForPerson($account->personId()) as $old) {
            $old->revoke($now);
        }
        $account->prepareForNewLetter($now);

        $password = OneTimePassword::generate();
        $invitation = Invitation::issue(Uuid::v4()->toRfc4122(), $account->personId(), $this->hasher->hash($password->normalized()), $issuedBy, $now);
        $this->invitations->add($invitation);
        $this->unitOfWork->commit();

        return new IssuedLetter($account->username()->value, $password->value, $invitation->validUntil());
    }
}

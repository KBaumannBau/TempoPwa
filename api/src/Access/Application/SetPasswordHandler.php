<?php

declare(strict_types=1);

namespace App\Access\Application;

use App\Access\Domain\AccountRepository;
use App\Access\Domain\InvitationRepository;
use App\Access\Domain\LoginRejected;
use App\Access\Domain\OneTimePassword;
use App\Access\Domain\PasswordHasher;
use App\Access\Domain\PasswordPolicy;
use Psr\Clock\ClockInterface;

/** Completes the registration with the letter: own password, account active, phone signed in. */
final readonly class SetPasswordHandler
{
    public function __construct(
        private AccountRepository $accounts,
        private InvitationRepository $invitations,
        private DeviceRegistrar $devices,
        private PasswordHasher $hasher,
        private UnitOfWork $unitOfWork,
        private ClockInterface $clock,
    ) {
    }

    /** @throws LoginRejected */
    public function handle(SetPasswordCommand $command): LoginResult
    {
        $now = $this->clock->now();
        $invitation = $this->invitations->find($command->ticket->invitationId);
        if ($invitation === null || !$invitation->isOpen()) {
            throw LoginRejected::registrationExpired();
        }
        if ($invitation->isExpired($now)) {
            throw LoginRejected::oneTimePasswordExpired();
        }
        $account = $this->accounts->findByPersonId($invitation->personId()) ?? throw LoginRejected::registrationExpired();
        $account->assertCanSignIn($now);

        $violation = PasswordPolicy::violation($command->newPassword, [$account->username()->value]);
        if ($violation === null && $this->hasher->verify($invitation->secretHash(), OneTimePassword::normalize($command->newPassword))) {
            $violation = 'Bitte ein neues Passwort wählen, nicht das aus dem Brief oder das bisherige.';
        }
        if ($violation !== null) {
            throw LoginRejected::passwordPolicyViolated($violation);
        }

        $account->completeRegistration($this->hasher->hash($command->newPassword), $command->ticket->language, $now);
        $invitation->redeem($now);
        $key = $this->devices->register($account->personId(), $command->ticket->deviceLabel, $now);
        $this->unitOfWork->commit();

        return LoginResult::signedIn($account->personId(), $key);
    }
}

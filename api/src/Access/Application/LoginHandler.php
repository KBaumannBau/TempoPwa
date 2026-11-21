<?php

declare(strict_types=1);

namespace App\Access\Application;

use App\Access\Domain\Account;
use App\Access\Domain\AccountRepository;
use App\Access\Domain\InvitationRepository;
use App\Access\Domain\LoginRejected;
use App\Access\Domain\OneTimePassword;
use App\Access\Domain\PasswordHasher;
use App\Access\Domain\Username;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;

/**
 * Sign-in with username and either the own password or the one-time password from the letter.
 */
final readonly class LoginHandler
{
    public function __construct(
        private AccountRepository $accounts,
        private InvitationRepository $invitations,
        private DeviceRegistrar $devices,
        private PasswordHasher $hasher,
        private LoginThrottle $throttle,
        private UnitOfWork $unitOfWork,
        private ClockInterface $clock,
        private LoggerInterface $logger,
    ) {
    }

    /** @throws LoginRejected */
    public function handle(LoginCommand $command): LoginResult
    {
        $now = $this->clock->now();
        $this->throttle->consume($command->clientKey, $now);

        $username = Username::tryFromString($command->username);
        $account = $username !== null ? $this->accounts->findByUsername($username) : null;
        if ($account === null) {
            // Same amount of work as for a known user, so usernames cannot be probed by response time
            $this->hasher->hash($command->password);
            $this->logger->notice('Access: sign-in with unknown username');

            throw LoginRejected::invalidCredentials(Account::MAX_FAILED_ATTEMPTS - 1);
        }

        $account->assertCanSignIn($now);

        $hash = $account->passwordHash();
        if ($hash !== null && $this->hasher->verify($hash, $command->password)) {
            if ($this->hasher->needsRehash($hash)) {
                $account->rehashPassword($this->hasher->hash($command->password), $now);
            }
            $account->recordSuccessfulSignIn($now);
            $key = $this->devices->register($account->personId(), $command->deviceLabel, $now);
            $this->unitOfWork->commit();
            $this->logger->info('Access: signed in', ['person' => $account->personId(), 'device' => $key->deviceId]);

            return LoginResult::signedIn($account->personId(), $key);
        }

        $invitation = $this->invitations->findOpenForPerson($account->personId());
        if ($invitation !== null && $this->hasher->verify($invitation->secretHash(), OneTimePassword::normalize($command->password))) {
            if ($invitation->isExpired($now)) {
                throw LoginRejected::oneTimePasswordExpired();
            }
            $account->recordSuccessfulSignIn($now);
            $this->unitOfWork->commit();
            $this->logger->info('Access: one-time password accepted', ['person' => $account->personId()]);

            return LoginResult::passwordChangeRequired($account->personId(), $invitation->id(), $command->language, $command->deviceLabel);
        }

        $rejection = $account->recordFailedAttempt($now);
        $this->unitOfWork->commit();
        $this->logger->notice('Access: wrong password', ['person' => $account->personId(), 'reason' => $rejection->reason->value]);

        throw $rejection;
    }
}

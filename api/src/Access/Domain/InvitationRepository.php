<?php

declare(strict_types=1);

namespace App\Access\Domain;

interface InvitationRepository
{
    public function find(string $id): ?Invitation;

    /** The newest letter of the person that is neither used nor revoked */
    public function findOpenForPerson(string $personId): ?Invitation;

    /** @return list<Invitation> */
    public function findAllOpenForPerson(string $personId): array;

    public function add(Invitation $invitation): void;
}

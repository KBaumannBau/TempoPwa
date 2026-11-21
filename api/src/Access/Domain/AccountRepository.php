<?php

declare(strict_types=1);

namespace App\Access\Domain;

interface AccountRepository
{
    public function findByUsername(Username $username): ?Account;

    public function findByPersonId(string $personId): ?Account;

    public function add(Account $account): void;
}

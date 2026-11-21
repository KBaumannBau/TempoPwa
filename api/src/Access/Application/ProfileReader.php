<?php

declare(strict_types=1);

namespace App\Access\Application;

/** Read side for the profile (person, roles, account). */
interface ProfileReader
{
    public function forPerson(string $personId): Profile;
}

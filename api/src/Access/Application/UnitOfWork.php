<?php

declare(strict_types=1);

namespace App\Access\Application;

/** Writes all changes of a use case in one transaction. */
interface UnitOfWork
{
    public function commit(): void;
}

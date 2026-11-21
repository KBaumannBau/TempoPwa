<?php

declare(strict_types=1);

namespace App\Access\Infrastructure\Doctrine;

use App\Access\Application\Profile;
use App\Access\Application\ProfileReader;
use Doctrine\DBAL\Connection;

final readonly class DbalProfileReader implements ProfileReader
{
    /** Which role decides the start page when a person has several (field roles first) */
    private const ROLE_PRIORITY = [
        'site_foreman', 'site_manager', 'senior_site_manager', 'foreman', 'skilled_worker', 'helper',
        'subcontractor_worker', 'admin', 'management', 'commercial_management', 'payroll', 'accounting',
        'billing', 'work_preparation', 'purchasing', 'subcontractor_contact',
    ];

    public function __construct(private Connection $connection)
    {
    }

    public function forPerson(string $personId): Profile
    {
        $row = $this->connection->fetchAssociative(
            'SELECT p.id, a.username, p.first_name, p.last_name, a.language
               FROM tempo.person p
               JOIN tempo.account a ON a.person_id = p.id
              WHERE p.id = :id',
            ['id' => $personId],
        );
        if ($row === false) {
            throw new \DomainException(\sprintf('No profile for person %s.', $personId));
        }

        /** @var list<string> $roles */
        $roles = $this->connection->fetchFirstColumn(
            'SELECT role FROM tempo.person_role
              WHERE person_id = :id AND valid_from <= current_date AND (valid_to IS NULL OR valid_to >= current_date)',
            ['id' => $personId],
        );
        usort($roles, static fn (string $a, string $b): int => self::rank($a) <=> self::rank($b));

        return new Profile(
            personId: (string) $row['id'],
            username: (string) $row['username'],
            firstName: (string) $row['first_name'],
            lastName: (string) $row['last_name'],
            primaryRole: $roles[0] ?? 'skilled_worker',
            roles: $roles,
            language: (string) $row['language'],
        );
    }

    private static function rank(string $role): int
    {
        $index = array_search($role, self::ROLE_PRIORITY, true);

        return $index === false ? \PHP_INT_MAX : $index;
    }
}

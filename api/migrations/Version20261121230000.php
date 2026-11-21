<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Access context (sign-in): roles, persons, accounts, registration letters, devices.
 *
 * Based on TempoVibe docs/datenbankschema.sql (se.rolle, se.person, se.person_rolle, se.zugang, se.einladung,
 * se.geraet), with English names (docs/database/naming.md). Deviations:
 * - text columns with CHECK instead of PostgreSQL enums (simpler with Doctrine, easy to extend),
 * - account.username as own column (subcontractor staff have no personnel number), no e-mail CHECK,
 * - invitation.revoked_at (a new letter invalidates older ones),
 * - one active device per person as DEFERRABLE exclusion constraint (sign out old + register new in one commit),
 * - subcontractor columns, RLS and the audit log follow with their own migrations.
 */
final class Version20261121230000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Access: role, person, person_role, account, invitation, device';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SCHEMA IF NOT EXISTS tempo');

        $this->addSql(<<<'SQL'
            CREATE TABLE tempo.role (
              key           text PRIMARY KEY,
              label         text NOT NULL,
              per_site      boolean NOT NULL DEFAULT false,
              own_staff     boolean NOT NULL DEFAULT true
            )
            SQL);
        $this->addSql(<<<'SQL'
            INSERT INTO tempo.role (key, label, per_site, own_staff) VALUES
              ('management',            'Geschäftsleitung',           false, true),
              ('commercial_management', 'Kaufmännische Leitung',      false, true),
              ('accounting',            'Buchhaltung',                false, true),
              ('payroll',               'Personal / HR / Lohn',       false, true),
              ('billing',               'Abrechnung',                 false, true),
              ('work_preparation',      'Arbeitsvorbereitung',        false, true),
              ('purchasing',            'Einkauf',                    false, true),
              ('senior_site_manager',   'Oberbauleitung',             false, true),
              ('site_manager',          'Bauleitung',                 true,  true),
              ('site_foreman',          'Polier',                     true,  true),
              ('foreman',               'Vorarbeiter',                true,  true),
              ('skilled_worker',        'Facharbeiter',               false, true),
              ('helper',                'Helfer (eigene)',            false, true),
              ('subcontractor_worker',  'NU-Person',                  false, false),
              ('subcontractor_contact', 'NU-Firma (Ansprechpartner)', false, false),
              ('admin',                 'Administrator',              false, true)
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE tempo.person (
              id                uuid PRIMARY KEY DEFAULT gen_random_uuid(),
              personnel_number  text UNIQUE,
              first_name        text NOT NULL,
              last_name         text NOT NULL,
              employment_type   text CHECK (employment_type IN ('wage_earner', 'salaried', 'apprentice')),
              job_title         text,
              entry_date        date,
              exit_date         date,
              under_18          boolean NOT NULL DEFAULT false,
              single_parent     boolean NOT NULL DEFAULT false,
              supervisor_id     uuid REFERENCES tempo.person(id),
              uses_app          boolean NOT NULL DEFAULT true,
              contact_phone     text,
              created_at        timestamptz(0) NOT NULL DEFAULT now(),
              created_by        uuid REFERENCES tempo.person(id),
              locked_at         timestamptz(0)
            )
            SQL);
        $this->addSql("COMMENT ON COLUMN tempo.person.uses_app IS 'false = hours are recorded by the site foreman and confirmed with a PIN'");

        $this->addSql(<<<'SQL'
            CREATE TABLE tempo.person_role (
              person_id   uuid NOT NULL REFERENCES tempo.person(id),
              role        text NOT NULL REFERENCES tempo.role(key),
              valid_from  date NOT NULL DEFAULT current_date,
              valid_to    date,
              granted_by  uuid REFERENCES tempo.person(id),
              granted_at  timestamptz(0) NOT NULL DEFAULT now(),
              PRIMARY KEY (person_id, role, valid_from)
            )
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE tempo.account (
              person_id        uuid PRIMARY KEY REFERENCES tempo.person(id),
              username         varchar(64) NOT NULL UNIQUE,
              status           text NOT NULL DEFAULT 'none' CHECK (status IN ('none', 'invited', 'active', 'locked')),
              email            text UNIQUE,
              password_hash    text,
              pin_hash         text,
              pin_is_initial   boolean NOT NULL DEFAULT false,
              failed_attempts  integer NOT NULL DEFAULT 0 CHECK (failed_attempts >= 0),
              locked_until     timestamptz(0),
              language         text NOT NULL DEFAULT 'de' CHECK (language IN ('de', 'pl', 'ro', 'hr', 'en', 'fr', 'it')),
              intro_seen       boolean NOT NULL DEFAULT false,
              registered_at    timestamptz(0),
              updated_at       timestamptz(0) NOT NULL DEFAULT now()
            )
            SQL);
        $this->addSql("COMMENT ON COLUMN tempo.account.password_hash IS 'Own password of every app user (argon2id/bcrypt), never plain'");

        $this->addSql(<<<'SQL'
            CREATE TABLE tempo.invitation (
              id           uuid PRIMARY KEY DEFAULT gen_random_uuid(),
              person_id    uuid NOT NULL REFERENCES tempo.person(id),
              secret_hash  text NOT NULL,
              delivery     text NOT NULL DEFAULT 'letter' CHECK (delivery IN ('letter', 'email')),
              issued_by    uuid REFERENCES tempo.person(id),
              issued_at    timestamptz(0) NOT NULL DEFAULT now(),
              valid_until  timestamptz(0) NOT NULL,
              used_at      timestamptz(0),
              revoked_at   timestamptz(0)
            )
            SQL);
        $this->addSql('CREATE INDEX invitation_open_per_person ON tempo.invitation (person_id, issued_at DESC) WHERE used_at IS NULL AND revoked_at IS NULL');

        $this->addSql(<<<'SQL'
            CREATE TABLE tempo.device (
              id             uuid PRIMARY KEY,
              person_id      uuid NOT NULL REFERENCES tempo.person(id),
              label          text NOT NULL,
              key_hash       text NOT NULL,
              status         text NOT NULL DEFAULT 'active' CHECK (status IN ('active', 'signed_out')),
              registered_at  timestamptz(0) NOT NULL DEFAULT now(),
              last_seen_at   timestamptz(0),
              signed_out_at  timestamptz(0),
              signed_out_by  uuid REFERENCES tempo.person(id),
              CONSTRAINT device_one_active_per_person EXCLUDE USING btree (person_id WITH =)
                WHERE (status = 'active') DEFERRABLE INITIALLY DEFERRED
            )
            SQL);
        $this->addSql("COMMENT ON COLUMN tempo.device.key_hash IS 'SHA-256 of the random device secret; the secret lives only in the HttpOnly cookie'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE tempo.device');
        $this->addSql('DROP TABLE tempo.invitation');
        $this->addSql('DROP TABLE tempo.account');
        $this->addSql('DROP TABLE tempo.person_role');
        $this->addSql('DROP TABLE tempo.person');
        $this->addSql('DROP TABLE tempo.role');
    }
}

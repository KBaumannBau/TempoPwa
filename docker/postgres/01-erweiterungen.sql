-- Läuft nur beim ersten Start mit leerem Datenvolume (docker-entrypoint-initdb.d).
-- Erweiterungen, die das Schema (TempoVibe/docs/datenbankschema.sql) voraussetzt.
-- Schemas, Rollen (se_app, se_loeschlauf) und Tabellen kommen über Doctrine-Migrationen.
CREATE EXTENSION IF NOT EXISTS pgcrypto;
CREATE EXTENSION IF NOT EXISTS btree_gist;

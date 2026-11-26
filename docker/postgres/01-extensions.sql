-- Runs only on the first start with an empty data volume (docker-entrypoint-initdb.d).
-- Extensions required by the schema (TempoVibe/docs/datenbankschema.sql).
-- Schemas, roles and tables come via Doctrine migrations.
CREATE EXTENSION IF NOT EXISTS pgcrypto;
CREATE EXTENSION IF NOT EXISTS btree_gist;

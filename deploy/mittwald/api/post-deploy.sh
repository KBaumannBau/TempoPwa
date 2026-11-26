#!/bin/sh
# Runs on the Mittwald server after the rsync of the API (from .github/workflows/deploy.yml).
# Usage: sh post-deploy.sh <target folder>
set -eu
TARGET="$1"
cd "$TARGET"

# Find PHP 8.4 (Mittwald provides several versions)
PHP=""
for candidate in php8.4 php84 php; do
  if command -v "$candidate" >/dev/null 2>&1 && "$candidate" -r 'exit(PHP_VERSION_ID >= 80400 ? 0 : 1);'; then PHP="$candidate"; break; fi
done
if [ -z "$PHP" ]; then
  echo "FEHLER: Kein PHP >= 8.4 auf dem Server gefunden (php -v: $(php -r 'echo PHP_VERSION;' 2>/dev/null || echo '-'))." >&2
  echo "Im mStudio die PHP-Version des Projekts auf 8.4 stellen." >&2
  exit 1
fi
echo "PHP: $($PHP -r 'echo PHP_VERSION;') ($PHP)"

# --- Create or complete .env.local ----------------------------------------------------
# The real .env.local is never overwritten. Only keys that are missing or still hold a
# placeholder (change-me) are added. Source: .env.local.initial from the pipeline.
umask 077
[ -f .env.local ] || { : > .env.local; echo ".env.local neu angelegt."; }
chmod 600 .env.local

# Set line "KEY=VALUE" if KEY is missing or still a placeholder
set_if_missing() {
  line="$1"
  key="${line%%=*}"
  current="$(grep -E "^${key}=" .env.local | tail -n 1 || true)"
  if [ -z "$current" ] || printf '%s' "$current" | grep -q 'change-me'; then
    grep -vE "^${key}=" .env.local > .env.local.tmp || true
    printf '%s\n' "$line" >> .env.local.tmp
    mv .env.local.tmp .env.local
    chmod 600 .env.local
    echo "  .env.local: $key gesetzt"
  fi
}

if [ -f .env.local.initial ]; then
  while IFS= read -r line || [ -n "$line" ]; do
    case "$line" in ''|'#'*) continue ;; esac
    set_if_missing "$line"
  done < .env.local.initial
  rm -f .env.local.initial
fi

# Without these values Symfony would run with APP_ENV=dev from api/.env – the dev packages are not installed on the server
set_if_missing "APP_ENV=prod"
set_if_missing "APP_DEBUG=0"

# Generate APP_SECRET on the server only, never from the pipeline
set_if_missing "APP_SECRET=$($PHP -r 'echo bin2hex(random_bytes(32));')"

# Symfony does not start without DATABASE_URL: set a placeholder, health then reports "degraded"
if ! grep -qE '^DATABASE_URL=' .env.local; then
  set_if_missing 'DATABASE_URL="postgresql://tempo:change-me@localhost:5432/tempo?serverVersion=17&charset=utf8"'
  echo "WARNUNG: Keine DATABASE_URL – Repo-Secret TEMPO_API_DATABASE_URL setzen oder .env.local von Hand ergänzen."
fi

# --- Cache and migrations ----------------------------------------------------------------
rm -rf var/cache/prod
"$PHP" bin/console cache:warmup --env=prod --no-debug

# Migrations only when the database is reachable
if "$PHP" bin/console dbal:run-sql "SELECT 1" --env=prod --no-debug >/dev/null 2>&1; then
  echo "Datenbank erreichbar."
  "$PHP" bin/console doctrine:migrations:migrate --env=prod --no-debug --no-interaction --allow-no-migration
else
  echo "WARNUNG: Datenbank nicht erreichbar – Migrationen übersprungen. DATABASE_URL in $TARGET.env.local prüfen."
fi

echo "API bereit."

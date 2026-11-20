#!/bin/sh
# Läuft auf dem Mittwald-Server nach dem rsync der API (aus .github/workflows/deploy.yml).
# Aufruf: sh nach-dem-deploy.sh <Zielordner>
set -eu
ZIEL="$1"
cd "$ZIEL"

# PHP 8.4 finden (Mittwald stellt mehrere Versionen bereit)
PHP=""
for k in php8.4 php84 php; do
  if command -v "$k" >/dev/null 2>&1 && "$k" -r 'exit(PHP_VERSION_ID >= 80400 ? 0 : 1);'; then PHP="$k"; break; fi
done
if [ -z "$PHP" ]; then
  echo "FEHLER: Kein PHP >= 8.4 auf dem Server gefunden (php -v: $(php -r 'echo PHP_VERSION;' 2>/dev/null || echo '-'))." >&2
  echo "Im mStudio die PHP-Version des Projekts auf 8.4 stellen." >&2
  exit 1
fi
echo "PHP: $($PHP -r 'echo PHP_VERSION;') ($PHP)"

# .env.local nur beim ersten Deploy anlegen; danach wird sie auf dem Server gepflegt
if [ ! -f .env.local ]; then
  umask 077
  {
    echo "APP_ENV=prod"
    echo "APP_DEBUG=0"
    echo "APP_SECRET=$($PHP -r 'echo bin2hex(random_bytes(32));')"
    echo "# DATABASE_URL und CORS_ALLOW_ORIGIN eintragen, Vorlage: deploy/mittwald/api/env.local.vorlage"
    echo "DATABASE_URL=\"postgresql://tempo:change-me@localhost:5432/tempo?serverVersion=16&charset=utf8\""
  } > .env.local
  echo "HINWEIS: .env.local neu angelegt – DATABASE_URL ist noch ein Platzhalter."
fi

# Cache für prod neu aufbauen
rm -rf var/cache/prod
"$PHP" bin/console cache:warmup --env=prod --no-debug

# Migrationen nur, wenn die Datenbank erreichbar ist
if "$PHP" bin/console dbal:run-sql "SELECT 1" --env=prod --no-debug >/dev/null 2>&1; then
  "$PHP" bin/console doctrine:migrations:migrate --env=prod --no-debug --no-interaction --allow-no-migration
else
  echo "WARNUNG: Datenbank nicht erreichbar – Migrationen übersprungen. DATABASE_URL in $ZIEL/.env.local prüfen."
fi

echo "API bereit."

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

# --- .env.local anlegen bzw. ergänzen -----------------------------------------------
# Die echte .env.local wird nie überschrieben. Ergänzt werden nur Schlüssel, die fehlen oder noch
# einen Platzhalter (change-me) enthalten. Quelle: .env.local.erstanlage aus der Pipeline.
umask 077
[ -f .env.local ] || { : > .env.local; echo ".env.local neu angelegt."; }
chmod 600 .env.local

# Zeile "KEY=WERT" setzen, wenn KEY fehlt oder noch ein Platzhalter ist
ergaenzen() {
  zeile="$1"
  key="${zeile%%=*}"
  aktuell="$(grep -E "^${key}=" .env.local | tail -n 1 || true)"
  if [ -z "$aktuell" ] || printf '%s' "$aktuell" | grep -q 'change-me'; then
    grep -vE "^${key}=" .env.local > .env.local.tmp || true
    printf '%s\n' "$zeile" >> .env.local.tmp
    mv .env.local.tmp .env.local
    chmod 600 .env.local
    echo "  .env.local: $key gesetzt"
  fi
}

if [ -f .env.local.erstanlage ]; then
  while IFS= read -r zeile || [ -n "$zeile" ]; do
    case "$zeile" in ''|'#'*) continue ;; esac
    ergaenzen "$zeile"
  done < .env.local.erstanlage
  rm -f .env.local.erstanlage
fi

# Ohne diese Werte liefe Symfony mit APP_ENV=dev aus api/.env – die dev-Pakete sind auf dem Server nicht installiert
ergaenzen "APP_ENV=prod"
ergaenzen "APP_DEBUG=0"

# APP_SECRET nur auf dem Server erzeugen, nie aus der Pipeline
ergaenzen "APP_SECRET=$($PHP -r 'echo bin2hex(random_bytes(32));')"

# Ohne DATABASE_URL startet Symfony nicht: Platzhalter setzen, Health meldet dann "gestoert"
if ! grep -qE '^DATABASE_URL=' .env.local; then
  ergaenzen 'DATABASE_URL="postgresql://tempo:change-me@localhost:5432/tempo?serverVersion=17&charset=utf8"'
  echo "WARNUNG: Keine DATABASE_URL – Repo-Secret TEMPO_API_DATABASE_URL setzen oder .env.local von Hand ergänzen."
fi

# --- Cache und Migrationen --------------------------------------------------------------
rm -rf var/cache/prod
"$PHP" bin/console cache:warmup --env=prod --no-debug

# Migrationen nur, wenn die Datenbank erreichbar ist
if "$PHP" bin/console dbal:run-sql "SELECT 1" --env=prod --no-debug >/dev/null 2>&1; then
  echo "Datenbank erreichbar."
  "$PHP" bin/console doctrine:migrations:migrate --env=prod --no-debug --no-interaction --allow-no-migration
else
  echo "WARNUNG: Datenbank nicht erreichbar – Migrationen übersprungen. DATABASE_URL in $ZIEL.env.local prüfen."
fi

echo "API bereit."

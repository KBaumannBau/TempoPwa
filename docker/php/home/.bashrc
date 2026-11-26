# Shell in the php container (user www, home = docker/php/home/)
# Start: docker compose exec php bash   –   overview: help

API_DIR=/var/www/html/api

PS1="\[\e[36m\]tempo-api\[\e[0m\] \w $ "
SHELL=/bin/bash
export SHELL

eval "$(dircolors)"
export LS_OPTIONS="--color=auto"

alias ls='ls $LS_OPTIONS'
alias ll='ls $LS_OPTIONS -l'
alias l='ls $LS_OPTIONS -lA'

# --- Symfony console from anywhere ----------------------------------------------
function console() {
  pushd . >/dev/null
  cd "$API_DIR" || return
  ./bin/console "$@"
  local rc=$?
  popd >/dev/null || return
  return $rc
}

function c() {
  console "$@"
}

# --- Symfony & API Platform -------------------------------------------------------
alias ccc="c cache:clear"
alias cdr="c debug:router"
alias cdc="c debug:container"
alias oas="c api:openapi:export --yaml"
alias health="curl -s http://nginx:8001/api/health; echo"
alias log="tail -f $API_DIR/var/log/dev.log"

# --- Doctrine & database (schema via migrations only) ------------------------------
alias cdmd="c doctrine:migrations:diff"
alias cdmg="c doctrine:migrations:generate"
alias cdmm="c doctrine:migrations:migrate --no-interaction"
alias cdms="c doctrine:migrations:status"
alias cdmx="c doctrine:migrations:execute"
alias cdsv="c doctrine:schema:validate"
alias sql="c dbal:run-sql"

# --- Composer -----------------------------------------------------------------------
alias update="cd $API_DIR && composer up"

# --- Tests ----------------------------------------------------------------------------
alias test="cd $API_DIR && php bin/phpunit --display-notices --display-deprecations --display-warnings --display-phpunit-notices"
alias report="cd $API_DIR && php -d xdebug.mode=coverage bin/phpunit --coverage-clover=$API_DIR/var/coverage/coverage.xml --coverage-html=$API_DIR/var/coverage"

# Create the test database (if needed) and bring it up to date with the migrations
function testdb() {
  c doctrine:database:create --env=test --if-not-exists \
    && c doctrine:migrations:migrate --env=test --no-interaction --allow-no-migration
}

# One-time setup after the first "docker compose up -d"
function install() {
  pushd . >/dev/null
  cd "$API_DIR" || return
  composer install -o \
    && c doctrine:migrations:migrate --no-interaction --allow-no-migration \
    && testdb
  popd >/dev/null || return
}

# --- Completion for the aliases -------------------------------------------------------
_alias_completion() {
  local cur=""
  if [[ -v COMP_WORDS ]]; then
    cur="${COMP_WORDS[COMP_CWORD]}"
  fi
  local aliases
  aliases=$(alias | cut -d'=' -f1 | sed 's/^alias //'; echo c console install testdb help)
  mapfile -t COMPREPLY < <(compgen -W "$aliases" -- "$cur")
}
complete -F _alias_completion -E

# --- Overview ----------------------------------------------------------------------------
function _cmd() {
  printf "  \e[33m%-8s\e[0m - %s\n" "$1" "$2"
}

function _section() {
  echo -e "\n\e[1;32m $1\e[0m"
  echo -e "\e[32m--------------------------------------------------------\e[0m"
}

function help() {
  clear

  echo -e "\n\e[1;33m     ⏱️  TEMPO API – BEFEHLE IM PHP-CONTAINER ⏱️\e[0m"
  echo -e "\e[36m--------------------------------------------------------\e[0m"

  _section "🛠️ Symfony & API Platform"
  _cmd "c" "Symfony-Konsole aus jedem Verzeichnis"
  printf "  \e[33m%-8s\e[0m   %s \e[36m%s\e[0m\n" " " "z. B." "c debug:router"
  _cmd "ccc" "Cache leeren"
  _cmd "cdr" "Alle Routen anzeigen"
  _cmd "cdc" "Services im Container anzeigen"
  _cmd "oas" "OpenAPI-Schema als YAML ausgeben"
  _cmd "health" "/api/health über nginx abfragen"
  _cmd "log" "dev.log mitlesen"

  _section "💾 Doctrine & Datenbank"
  _cmd "cdmd" "Migration aus den Entities erzeugen (diff)"
  _cmd "cdmg" "Leere Migration anlegen"
  _cmd "cdmm" "Migrationen ausführen"
  _cmd "cdms" "Stand der Migrationen"
  _cmd "cdmx" "Einzelne Migration ausführen"
  _cmd "cdsv" "Mapping und Schema prüfen"
  _cmd "sql" "SQL ausführen, z. B. sql 'select 1'"

  _section "🚀 Composer & Einrichtung"
  _cmd "update" "composer up"
  _cmd "install" "composer install, Migrationen, Test-DB"
  _cmd "testdb" "Test-Datenbank anlegen und migrieren"

  _section "🧐 Tests"
  _cmd "test" "PHPUnit (vorher einmal testdb)"
  _cmd "report" "PHPUnit mit Coverage nach var/coverage/"

  _section "📂 Dateien"
  _cmd "ls" "Dateien anzeigen"
  _cmd "ll" "Lange Liste"
  _cmd "l" "Lange Liste mit versteckten Dateien"

  _section "📱 PWA (läuft im pwa-Container, nicht hier)"
  echo -e "  \e[36mdocker compose exec pwa npm run build\e[0m"
  echo -e "  \e[36mdocker compose exec pwa npm run api:types\e[0m"

  _section "❓ System"
  _cmd "help" "Diese Übersicht"
  echo
}

cd "$API_DIR" || true

case "$-" in
*i*)
  echo ' '
  echo '  ,---------------------------------⹁ '
  echo '  |  ⏱️  Tempo API (Baumann)  ⏱️    |'
  echo '  `---------------------------------´ '
  echo ' '
  echo ' ... "help" für alle Befehle,'
  echo '     oder "tab" für die Vervollständigung!'
  echo ' '
  ;;
esac

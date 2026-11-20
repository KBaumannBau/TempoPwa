#!/usr/bin/env bash
# =============================================================================
# Tempo – Deployment über Git
#
# Deployt wird immer über GitHub Actions (.github/workflows/deploy.yml). Dieses Skript sorgt dafür,
# dass vorher alles stimmt, und löst den Deploy über Commit und Push aus.
#
#   ./deployment.sh [-m "Nachricht"]
#       Testinstanz: aktuellen Stand von main pushen. Mit -m werden offene Änderungen vorher
#       als Commit mit dieser Nachricht aufgenommen. Version auf dem Server: X.Y.Z+<commit>.
#
#   ./deployment.sh release [patch|minor|major|X.Y.Z] [-m "Nachricht"]
#       Release: Version in VERSION erhöhen (Standard: patch), README-Badge und pwa/package.json
#       nachziehen, Commit "Release vX.Y.Z", Tag vX.Y.Z, Push. Die Pipeline deployt die Version
#       X.Y.Z und legt ein GitHub-Release an.
#
# Optionen:
#   --ohne-tests   PHPUnit und den PWA-Build vorher nicht laufen lassen
#   --ja           ohne Rückfrage pushen
#   -h, --help     diese Hilfe
#
# Voraussetzungen: Branch main, Stand von origin/main enthalten, für die Tests laufende Container
# (docker compose up -d).
# =============================================================================
set -euo pipefail

cd "$(dirname "$0")"

ROT=$'\033[31m'; GRUEN=$'\033[32m'; GELB=$'\033[33m'; FETT=$'\033[1m'; AUS=$'\033[0m'
info()   { printf '%s\n' "${FETT}›${AUS} $*"; }
ok()     { printf '%s\n' "${GRUEN}✓${AUS} $*"; }
warnung(){ printf '%s\n' "${GELB}!${AUS} $*"; }
fehler() { printf '%s\n' "${ROT}✗ $*${AUS}" >&2; exit 1; }

hilfe() { sed -n '3,/^# ====/p' "$0" | sed -e '$d' -e 's/^# \{0,1\}//'; exit 0; }

# --- Argumente ------------------------------------------------------------------------------
MODUS=test; STUFE=patch; NACHRICHT=""; TESTS=1; JA=0
while [ $# -gt 0 ]; do
  case "$1" in
    release) MODUS=release
             if [ $# -gt 1 ] && [[ "$2" =~ ^(patch|minor|major|[0-9]+\.[0-9]+\.[0-9]+)$ ]]; then STUFE="$2"; shift; fi ;;
    -m) [ $# -gt 1 ] || fehler "-m braucht eine Nachricht."; NACHRICHT="$2"; shift ;;
    --ohne-tests) TESTS=0 ;;
    --ja) JA=1 ;;
    -h|--help) hilfe ;;
    *) fehler "Unbekanntes Argument: $1 (Hilfe: ./deployment.sh --help)" ;;
  esac
  shift
done

# --- Git prüfen -----------------------------------------------------------------------------
[ -f VERSION ] || fehler "Datei VERSION fehlt im Wurzelordner."
ZWEIG="$(git rev-parse --abbrev-ref HEAD)"
[ "$ZWEIG" = "main" ] || fehler "Deployt wird nur von main (aktuell: $ZWEIG)."

info "Hole origin …"
git fetch --quiet --tags origin
if ! git merge-base --is-ancestor origin/main HEAD; then
  fehler "origin/main hat Commits, die hier fehlen. Erst: git pull --rebase"
fi

if [ -n "$(git status --porcelain)" ]; then
  if [ -z "$NACHRICHT" ]; then
    git status --short
    fehler "Es gibt nicht committete Änderungen. Committen oder mit -m \"Nachricht\" aufnehmen lassen."
  fi
fi

# --- Tests ----------------------------------------------------------------------------------
if [ "$TESTS" = 1 ]; then
  docker compose ps --status running --services 2>/dev/null | grep -qx php \
    || fehler "Container laufen nicht (docker compose up -d) – oder mit --ohne-tests überspringen."
  info "PHPUnit …"
  docker compose exec -T php vendor/bin/phpunit >/dev/null || fehler "PHPUnit ist rot."
  ok "PHPUnit grün"
  info "PWA-Build (Typecheck + Vite) …"
  docker compose exec -T pwa npm run build >/dev/null || fehler "npm run build ist fehlgeschlagen."
  ok "PWA-Build fehlerfrei"
fi

# --- Version --------------------------------------------------------------------------------
ALT="$(tr -d '[:space:]' < VERSION)"
[[ "$ALT" =~ ^([0-9]+)\.([0-9]+)\.([0-9]+)$ ]] || fehler "VERSION enthält keine gültige Version: '$ALT'"
MAJ="${BASH_REMATCH[1]}"; MIN="${BASH_REMATCH[2]}"; PAT="${BASH_REMATCH[3]}"

if [ "$MODUS" = release ]; then
  case "$STUFE" in
    patch) NEU="$MAJ.$MIN.$((PAT + 1))" ;;
    minor) NEU="$MAJ.$((MIN + 1)).0" ;;
    major) NEU="$((MAJ + 1)).0.0" ;;
    *)     NEU="$STUFE" ;;
  esac
  git rev-parse -q --verify "refs/tags/v$NEU" >/dev/null && fehler "Tag v$NEU gibt es schon."
fi

# --- Zusammenfassung und Rückfrage ----------------------------------------------------------
echo
if [ "$MODUS" = release ]; then
  info "Release ${FETT}v$NEU${AUS} (bisher $ALT)"
else
  info "Testinstanz mit Version ${FETT}$ALT+<commit>${AUS}"
fi
[ -n "$NACHRICHT" ] && info "Offene Änderungen werden committet: \"$NACHRICHT\""
NEUE_COMMITS="$(git log --oneline origin/main..HEAD | wc -l | tr -d ' ')"
info "Noch nicht gepushte Commits: $NEUE_COMMITS"
if [ "$JA" != 1 ]; then
  read -r -p "Weiter? Danach wird gepusht und deployt. [j/N] " antwort
  [[ "$antwort" =~ ^[jJyY]$ ]] || fehler "Abgebrochen."
fi

# --- Commit(s) ------------------------------------------------------------------------------
if [ -n "$NACHRICHT" ] && [ -n "$(git status --porcelain)" ]; then
  git add -A
  git commit --quiet -m "$NACHRICHT"
  ok "Commit: $NACHRICHT"
fi

if [ "$MODUS" = release ]; then
  printf '%s\n' "$NEU" > VERSION
  # Badge im README und Version der PWA nachziehen
  perl -pi -e "s#(img\.shields\.io/badge/version-)[0-9]+\.[0-9]+\.[0-9]+#\${1}$NEU#" README.md
  perl -0pi -e "s/(\"name\": \"tempo-pwa\",\n  \"private\": true,\n  \"version\": \")[^\"]+/\${1}$NEU/" pwa/package.json
  perl -0pi -e "s/(\"name\": \"tempo-pwa\",\n  \"version\": \")[^\"]+/\${1}$NEU/; s/(\"\": \{\n      \"name\": \"tempo-pwa\",\n      \"version\": \")[^\"]+/\${1}$NEU/" pwa/package-lock.json
  git add VERSION README.md pwa/package.json pwa/package-lock.json
  git commit --quiet -m "Release v$NEU"
  git tag -a "v$NEU" -m "Tempo v$NEU"
  ok "Commit und Tag v$NEU"
fi

# --- Push -----------------------------------------------------------------------------------
if [ "$MODUS" = release ]; then
  git push --atomic origin main "v$NEU"
else
  if [ "$(git rev-parse HEAD)" = "$(git rev-parse origin/main)" ]; then
    warnung "Nichts Neues zu pushen – für einen erneuten Deploy: GitHub → Actions → \"Deploy to Mittwald\" → Run workflow."
    exit 0
  fi
  git push origin main
fi

REPO_URL="$(git remote get-url origin | sed -e 's#^git@github.com:#https://github.com/#' -e 's#\.git$##')"
echo
ok "Gepusht. Deploy läuft: $REPO_URL/actions"
echo "  App:  https://tempo-app.p-yt27xz.project.space/"
echo "  API:  https://tempo-api.p-yt27xz.project.space/api/docs"

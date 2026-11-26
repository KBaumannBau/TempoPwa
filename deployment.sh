#!/usr/bin/env bash
# =============================================================================
# Tempo – deployment via Git
#
# Deployment always runs through GitHub Actions (.github/workflows/deploy.yml). This script makes sure
# everything is in order first and triggers the deploy by commit and push.
#
#   ./deployment.sh [-m "message"]
#       Test instance: push the current state of main. With -m, open changes are committed first
#       with this message. Version on the server: X.Y.Z+<commit>.
#
#   ./deployment.sh release [patch|minor|major|X.Y.Z] [-m "message"]
#       Release: bump the version in VERSION (default: patch), update the README badge and
#       pwa/package.json, commit "Release vX.Y.Z", tag vX.Y.Z, push. The pipeline deploys version
#       X.Y.Z and creates a GitHub release.
#
# Options:
#   --skip-tests   do not run PHPUnit and the PWA build first
#   --yes          push without asking
#   -h, --help     this help
#
# Requirements: branch main, origin/main contained, running containers for the tests
# (docker compose up -d).
# =============================================================================
set -euo pipefail

cd "$(dirname "$0")"

RED=$'\033[31m'; GREEN=$'\033[32m'; YELLOW=$'\033[33m'; BOLD=$'\033[1m'; RESET=$'\033[0m'
info()   { printf '%s\n' "${BOLD}›${RESET} $*"; }
ok()     { printf '%s\n' "${GREEN}✓${RESET} $*"; }
warn()   { printf '%s\n' "${YELLOW}!${RESET} $*"; }
fail()   { printf '%s\n' "${RED}✗ $*${RESET}" >&2; exit 1; }

usage() { sed -n '3,/^# ====/p' "$0" | sed -e '$d' -e 's/^# \{0,1\}//'; exit 0; }

# --- Arguments -------------------------------------------------------------------------------
MODE=test; BUMP=patch; MESSAGE=""; TESTS=1; YES=0
while [ $# -gt 0 ]; do
  case "$1" in
    release) MODE=release
             if [ $# -gt 1 ] && [[ "$2" =~ ^(patch|minor|major|[0-9]+\.[0-9]+\.[0-9]+)$ ]]; then BUMP="$2"; shift; fi ;;
    -m) [ $# -gt 1 ] || fail "-m braucht eine Nachricht."; MESSAGE="$2"; shift ;;
    --skip-tests) TESTS=0 ;;
    --yes) YES=1 ;;
    -h|--help) usage ;;
    *) fail "Unbekanntes Argument: $1 (Hilfe: ./deployment.sh --help)" ;;
  esac
  shift
done

# --- Check Git -------------------------------------------------------------------------------
[ -f VERSION ] || fail "Datei VERSION fehlt im Wurzelordner."
BRANCH="$(git rev-parse --abbrev-ref HEAD)"
[ "$BRANCH" = "main" ] || fail "Deployt wird nur von main (aktuell: $BRANCH)."

info "Hole origin …"
git fetch --quiet --tags origin
if ! git merge-base --is-ancestor origin/main HEAD; then
  fail "origin/main hat Commits, die hier fehlen. Erst: git pull --rebase"
fi

if [ -n "$(git status --porcelain)" ]; then
  if [ -z "$MESSAGE" ]; then
    git status --short
    fail "Es gibt nicht committete Änderungen. Committen oder mit -m \"Nachricht\" aufnehmen lassen."
  fi
fi

# --- Tests ----------------------------------------------------------------------------------
if [ "$TESTS" = 1 ]; then
  docker compose ps --status running --services 2>/dev/null | grep -qx php \
    || fail "Container laufen nicht (docker compose up -d) – oder mit --skip-tests überspringen."
  info "PHPUnit …"
  docker compose exec -T php vendor/bin/phpunit >/dev/null || fail "PHPUnit ist rot."
  ok "PHPUnit grün"
  info "PWA-Build (Typecheck + Vite) …"
  docker compose exec -T pwa npm run build >/dev/null || fail "npm run build ist fehlgeschlagen."
  ok "PWA-Build fehlerfrei"
fi

# --- Version --------------------------------------------------------------------------------
OLD="$(tr -d '[:space:]' < VERSION)"
[[ "$OLD" =~ ^([0-9]+)\.([0-9]+)\.([0-9]+)$ ]] || fail "VERSION enthält keine gültige Version: '$OLD'"
MAJ="${BASH_REMATCH[1]}"; MIN="${BASH_REMATCH[2]}"; PAT="${BASH_REMATCH[3]}"

if [ "$MODE" = release ]; then
  case "$BUMP" in
    patch) NEW="$MAJ.$MIN.$((PAT + 1))" ;;
    minor) NEW="$MAJ.$((MIN + 1)).0" ;;
    major) NEW="$((MAJ + 1)).0.0" ;;
    *)     NEW="$BUMP" ;;
  esac
  git rev-parse -q --verify "refs/tags/v$NEW" >/dev/null && fail "Tag v$NEW gibt es schon."
fi

# --- Summary and confirmation --------------------------------------------------------------
echo
if [ "$MODE" = release ]; then
  info "Release ${BOLD}v$NEW${RESET} (bisher $OLD)"
else
  info "Testinstanz mit Version ${BOLD}$OLD+<commit>${RESET}"
fi
[ -n "$MESSAGE" ] && info "Offene Änderungen werden committet: \"$MESSAGE\""
UNPUSHED="$(git log --oneline origin/main..HEAD | wc -l | tr -d ' ')"
info "Noch nicht gepushte Commits: $UNPUSHED"
if [ "$YES" != 1 ]; then
  read -r -p "Weiter? Danach wird gepusht und deployt. [j/N] " answer
  [[ "$answer" =~ ^[jJyY]$ ]] || fail "Abgebrochen."
fi

# --- Commit(s) ------------------------------------------------------------------------------
if [ -n "$MESSAGE" ] && [ -n "$(git status --porcelain)" ]; then
  git add -A
  git commit --quiet -m "$MESSAGE"
  ok "Commit: $MESSAGE"
fi

if [ "$MODE" = release ]; then
  printf '%s\n' "$NEW" > VERSION
  # Update the badge in the README and the version of the PWA
  perl -pi -e "s#(img\.shields\.io/badge/version-)[0-9]+\.[0-9]+\.[0-9]+#\${1}$NEW#" README.md
  perl -0pi -e "s/(\"name\": \"tempo-pwa\",\n  \"private\": true,\n  \"version\": \")[^\"]+/\${1}$NEW/" pwa/package.json
  perl -0pi -e "s/(\"name\": \"tempo-pwa\",\n  \"version\": \")[^\"]+/\${1}$NEW/; s/(\"\": \{\n      \"name\": \"tempo-pwa\",\n      \"version\": \")[^\"]+/\${1}$NEW/" pwa/package-lock.json
  git add VERSION README.md pwa/package.json pwa/package-lock.json
  git commit --quiet -m "Release v$NEW"
  git tag -a "v$NEW" -m "Tempo v$NEW"
  ok "Commit und Tag v$NEW"
fi

# --- Push -----------------------------------------------------------------------------------
if [ "$MODE" = release ]; then
  git push --atomic origin main "v$NEW"
else
  if [ "$(git rev-parse HEAD)" = "$(git rev-parse origin/main)" ]; then
    warn "Nichts Neues zu pushen – für einen erneuten Deploy: GitHub → Actions → \"Deploy to Mittwald\" → Run workflow."
    exit 0
  fi
  git push origin main
fi

REPO_URL="$(git remote get-url origin | sed -e 's#^git@github.com:#https://github.com/#' -e 's#\.git$##')"
echo
ok "Gepusht. Deploy läuft: $REPO_URL/actions"
echo "  App:  https://tempo-app.p-yt27xz.project.space/"
echo "  API:  https://tempo-api.p-yt27xz.project.space/api/docs"

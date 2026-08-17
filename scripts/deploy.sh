#!/usr/bin/env bash
# omahub production deploy script
#
# How this app is deployed: a Docker Caddy reverse proxy + shared php-fpm
# container (`reverse-proxy-fpm-1`) bind-mount this repo at /srv/omahub — the
# repo itself IS the deployment. So "deploy" = pull + rebuild assets + refresh
# caches. No container restart is needed: opcache revalidates every 2s and
# Vite emits content-hashed asset filenames that bust browser caches on their own.
#
# Steps:
#   1. Preflight: clean working tree, fpm container running
#   2. git fetch + git pull --ff-only
#   3. composer install  (only if composer.lock changed; via container — host has no php)
#   4. npm ci            (only if package-lock.json changed)
#   5. npm run build     (always — Tailwind utilities like new h-*/w-* classes only
#                         exist in the production CSS once rebuilt; skipping this is
#                         the classic "deployed but styles missing" trap)
#   6. Refresh Laravel caches inside the fpm container: view:clear, cache:clear, config:cache
#   7. Smoke test: homepage HTTP 200 AND served HTML references the freshly built
#      CSS asset (catches stale proxy/CDN/opcache serving old assets)
#
# Usage:
#   ./scripts/deploy.sh            # normal deploy
#   ./scripts/deploy.sh --force    # rebuild + refresh caches even if HEAD unchanged
#
# Env overrides:
#   FPM_CONTAINER  php-fpm container mounting this repo (default: reverse-proxy-fpm-1)
#   FPM_APP_PATH   repo mount path inside the container (default: /srv/omahub)
#   APP_URL        base URL smoke-tested after deploy (default: https://omahub.dev)

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$REPO_ROOT"

FPM_CONTAINER="${FPM_CONTAINER:-reverse-proxy-fpm-1}"
FPM_APP_PATH="${FPM_APP_PATH:-/srv/omahub}"
APP_URL="${APP_URL:-https://omahub.dev}"
FORCE=0
[[ "${1:-}" == "--force" ]] && FORCE=1

ARTISAN=(docker exec "$FPM_CONTAINER" php "$FPM_APP_PATH/artisan")
BRANCH="$(git branch --show-current)"

fail() { echo "✗ $*" >&2; exit 1; }
say()  { echo "· $*"; }

# --- 1. preflight -----------------------------------------------------------
say "Deploying omahub (branch: $BRANCH)"
if [[ -n "$(git status --porcelain)" ]]; then
  fail "Working tree is dirty — commit or stash changes before deploying."
fi
if ! docker ps --format '{{.Names}}' | grep -qx "$FPM_CONTAINER"; then
  fail "Container '$FPM_CONTAINER' is not running."
fi

# --- 2. pull ----------------------------------------------------------------
BEFORE="$(git rev-parse HEAD)"
git fetch origin
# `git pull --ff-only` is the authoritative guard: it refuses if origin rewrote
# history or the branches truly diverged, and with `set -e` below a failed pull
# aborts the script before anything is built or deployed.
git pull --ff-only origin "$BRANCH"
AFTER="$(git rev-parse HEAD)"

if [[ "$BEFORE" == "$AFTER" && "$FORCE" != 1 ]]; then
  say "Already up to date at $(git log --format='%h %s' -1 HEAD) — nothing to deploy."
  exit 0
fi

if [[ "$BEFORE" != "$AFTER" ]]; then
  say "New commits:"
  git log --oneline --no-decorate "$BEFORE..$AFTER" | sed 's/^/    /'
fi

# --- 3. composer (only when lockfile changed) --------------------------------
CHANGED="$(git diff --name-only "$BEFORE..$AFTER" 2>/dev/null || true)"
run_composer() {
  if command -v composer >/dev/null 2>&1; then
    composer install --no-dev --no-interaction --prefer-dist --no-progress "$@"
  elif docker exec "$FPM_CONTAINER" sh -c 'command -v composer' >/dev/null 2>&1; then
    # No php/composer on the host — run inside the fpm container as the host uid
    # so created files keep host ownership on the bind mount.
    docker exec -u "$(id -u):$(id -g)" "$FPM_CONTAINER" sh -c 'mkdir -p /tmp/composer-home'
    docker exec -u "$(id -u):$(id -g)" -e COMPOSER_HOME=/tmp/composer-home \
      "$FPM_CONTAINER" composer install --no-dev --no-interaction --prefer-dist --no-progress "$@"
  else
    fail "composer.lock changed, but composer is available neither on the host nor in '$FPM_CONTAINER'."
  fi
}
if grep -q '^composer\.lock$' <<<"$CHANGED"; then
  say "composer.lock changed — installing dependencies"
  run_composer
fi

# --- 4. npm ci (only when lockfile changed) -----------------------------------
if grep -q '^package-lock\.json$' <<<"$CHANGED"; then
  say "package-lock.json changed — reinstalling node modules"
  npm ci --no-audit --no-fund
fi

# --- 5. build frontend assets ------------------------------------------------
say "Building frontend assets (npm run build)"
npm run build

# --- 6. refresh Laravel caches in the container -------------------------------
say "Refreshing Laravel caches in '$FPM_CONTAINER'"
"${ARTISAN[@]}" view:clear
"${ARTISAN[@]}" cache:clear
"${ARTISAN[@]}" config:cache

# --- 7. smoke test -------------------------------------------------------------
say "Smoke test: $APP_URL"
ENTRY_CSS="$(node -e "
const m = require('./public/build/manifest.json');
const k = Object.keys(m).find(k => k.endsWith('.css') && m[k].isEntry);
if (!k) { console.error('no entry CSS in manifest'); process.exit(1); }
console.log(m[k].file);
")"
HTTP_CODE="$(curl -s -o /dev/null -w '%{http_code}' "$APP_URL/")"
[[ "$HTTP_CODE" == "200" ]] || fail "Homepage returned HTTP $HTTP_CODE (expected 200)."
curl -fsS -o /dev/null "$APP_URL/build/$ENTRY_CSS" || fail "Built CSS asset $ENTRY_CSS is not served."
curl -fsS "$APP_URL/" | grep -q "/build/$ENTRY_CSS" \
  || fail "Served homepage does not reference the freshly built CSS ($ENTRY_CSS) — stale cache/proxy?"

say "Deploy complete: $(git log --format='%h %s' -1 HEAD) — homepage 200, serving $ENTRY_CSS"
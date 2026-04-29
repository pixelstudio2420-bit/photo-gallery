#!/usr/bin/env bash
# ────────────────────────────────────────────────────────────────────────
# Photo Gallery — zero-downtime deploy script
# ────────────────────────────────────────────────────────────────────────
# Usage on the server:
#   cd /var/www/photo-gallery
#   sudo -u www-data ./docs/deployment/deploy.sh
#
# What it does:
#   1. Pull latest code
#   2. Install composer + npm dependencies
#   3. Build Vite assets
#   4. Put app in maintenance mode (5-second window)
#   5. Run migrations
#   6. Rebuild caches
#   7. Restart queue workers (so they pick up the new code)
#   8. Exit maintenance mode
#
# Safe to re-run on failure — each step is idempotent.
# ────────────────────────────────────────────────────────────────────────

set -Eeuo pipefail

APP_ROOT="/var/www/photo-gallery"
cd "$APP_ROOT"

step() { echo -e "\n\033[1;34m▶ $1\033[0m"; }
ok()   { echo -e "\033[1;32m✔ $1\033[0m"; }
fail() { echo -e "\033[1;31m✘ $1\033[0m"; exit 1; }

# ── 1. Pull latest code ─────────────────────────────────────────────
step "Pulling latest code"
git fetch --all --prune
git reset --hard origin/main
ok "code updated"

# ── 2. Install dependencies ─────────────────────────────────────────
step "Installing composer dependencies (--no-dev)"
composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist
ok "composer installed"

step "Installing npm dependencies"
npm ci --silent
ok "npm installed"

# ── 3. Build assets ─────────────────────────────────────────────────
step "Building Vite assets"
npm run build
ok "assets built"

# ── 4. Maintenance mode ─────────────────────────────────────────────
step "Entering maintenance mode"
php artisan down --render="errors::503" --retry=60 --secret="deploy-$(date +%s)" || true
ok "maintenance ON"

# Roll back to live on any error past this point
trap 'php artisan up; fail "deploy failed — restored live"' ERR

# ── 5. Migrations ───────────────────────────────────────────────────
step "Running migrations (--force for prod)"
php artisan migrate --force
ok "migrations ran"

# ── 6. Rebuild caches ───────────────────────────────────────────────
step "Clearing + rebuilding caches"
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
ok "caches rebuilt"

# ── 7. Restart queue workers ────────────────────────────────────────
step "Restarting queue workers"
php artisan queue:restart
ok "queue workers will restart on next poll"

# Optional: kick supervisor directly if running as root
if command -v supervisorctl >/dev/null 2>&1; then
    sudo supervisorctl restart photo-gallery:* || true
fi

# ── 8. Exit maintenance ─────────────────────────────────────────────
step "Leaving maintenance mode"
php artisan up
ok "site is live"

# ── 9. Post-deploy smoke check ──────────────────────────────────────
step "Running smoke test"
php artisan app:smoke-test || echo "⚠ smoke test had failures — investigate"

# ── Done ────────────────────────────────────────────────────────────
echo -e "\n\033[1;32m═══ Deploy complete — $(date) ═══\033[0m"

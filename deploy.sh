#!/bin/bash
# ============================================
# Photo Gallery — Production Deploy Script
# ============================================
# Usage:
#   bash deploy.sh             — Full deploy (preflight + backup + install + migrate + optimize)
#   bash deploy.sh optimize    — Only run optimization commands
#   bash deploy.sh rollback    — Clear all caches (recover from a bad deploy)
#   bash deploy.sh preflight   — Run preflight checks only (no changes)
#
# Behaviour:
#   - Fails fast on unsafe configuration (APP_DEBUG=true in production,
#     unset APP_KEY, missing DB, etc.). Use FORCE_DEPLOY=1 to override
#     specific warnings (still hard-fails on critical issues).
#   - Takes a database snapshot before running migrations.
# ============================================

set -euo pipefail

APP_DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$APP_DIR"

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

info()  { echo -e "${GREEN}[OK]${NC}  $1"; }
warn()  { echo -e "${YELLOW}[!!]${NC}  $1"; }
fail()  { echo -e "${RED}[ERR]${NC} $1"; exit 1; }

# Read a single env value safely (handles quotes + #comments)
env_get() {
    local key="$1"
    grep -E "^${key}=" .env 2>/dev/null \
        | head -n1 \
        | cut -d= -f2- \
        | sed -e 's/^[[:space:]]*//' -e 's/[[:space:]]*$//' \
        | sed -e 's/^"\(.*\)"$/\1/' -e "s/^'\(.*\)'\$/\1/"
}

# ─── Pre-flight checks (fail-fast on unsafe config) ──────────────────
preflight() {
    echo ""
    echo "=========================================="
    echo "  Pre-flight checks"
    echo "=========================================="

    php -v > /dev/null 2>&1 || fail "PHP not found"
    PHP_MAJOR=$(php -r 'echo PHP_MAJOR_VERSION;')
    PHP_MINOR=$(php -r 'echo PHP_MINOR_VERSION;')
    if [ "$PHP_MAJOR" -lt 8 ] || { [ "$PHP_MAJOR" -eq 8 ] && [ "$PHP_MINOR" -lt 2 ]; }; then
        fail "PHP $PHP_MAJOR.$PHP_MINOR found — Laravel 12 requires >= 8.2"
    fi
    info "PHP $(php -r 'echo PHP_VERSION;')"

    composer --version > /dev/null 2>&1 || fail "Composer not found"
    info "Composer available"

    [ -f .env ] || fail ".env file not found — copy .env.production and configure values"
    info ".env file found"

    APP_ENV="$(env_get APP_ENV)"
    APP_DEBUG="$(env_get APP_DEBUG)"
    APP_KEY="$(env_get APP_KEY)"
    DB_CONNECTION="$(env_get DB_CONNECTION)"
    DB_HOST="$(env_get DB_HOST)"
    DB_DATABASE="$(env_get DB_DATABASE)"

    [ "$APP_ENV" = "production" ] || warn "APP_ENV=$APP_ENV (expected 'production')"

    # Hard-fail: APP_DEBUG must be false in production. This leaks secrets.
    if [ "$APP_ENV" = "production" ] && [ "$APP_DEBUG" = "true" ]; then
        fail "APP_DEBUG=true in production. Refusing to deploy — set APP_DEBUG=false in .env."
    fi
    info "APP_DEBUG=$APP_DEBUG"

    # Hard-fail: APP_KEY must be set
    if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "CHANGE_ME" ] || [[ "$APP_KEY" != base64:* ]]; then
        fail "APP_KEY is not set. Run: php artisan key:generate"
    fi
    info "APP_KEY set"

    # Hard-fail: DB driver mismatch (this fork is Postgres-only)
    if [ "$DB_CONNECTION" != "pgsql" ]; then
        fail "DB_CONNECTION=$DB_CONNECTION — this fork requires 'pgsql'. See README.PGSQL.md."
    fi
    info "DB_CONNECTION=pgsql"

    [ -z "$DB_HOST" ]                   && fail "DB_HOST is empty in .env"
    [ -z "$DB_DATABASE" ]               && fail "DB_DATABASE is empty in .env"
    [ "$DB_DATABASE" = "CHANGE_ME" ]    && fail "DB_DATABASE still set to CHANGE_ME"

    # Hard-fail: PHP pdo_pgsql extension
    if ! php -m | grep -qi '^pdo_pgsql$'; then
        fail "PHP extension pdo_pgsql is not loaded — Postgres connections will fail. Install php-pgsql."
    fi
    info "PHP pdo_pgsql extension loaded"

    # Hard-fail: DB connectivity
    if ! php -r 'require "vendor/autoload.php"; $app=require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); try { Illuminate\Support\Facades\DB::connection()->getPdo(); } catch (Throwable $e) { fwrite(STDERR, $e->getMessage()); exit(1); }' > /dev/null 2>&1; then
        fail "Cannot connect to database (DB_HOST=$DB_HOST, DB_DATABASE=$DB_DATABASE). Check credentials and network."
    fi
    info "Database reachable"

    # Hard-fail: storage writable
    [ -w storage ]              || fail "storage/ is not writable"
    [ -w bootstrap/cache ]      || fail "bootstrap/cache is not writable"
    info "storage/ + bootstrap/cache writable"

    # Soft warning: disk space
    if command -v df >/dev/null 2>&1; then
        FREE_MB=$(df -m . | awk 'NR==2 {print $4}')
        if [ "$FREE_MB" -lt 500 ]; then
            warn "Only ${FREE_MB}MB free disk space — may run out during build"
        else
            info "Free disk space: ${FREE_MB}MB"
        fi
    fi

    # Soft warning: pg_dump availability (needed for backups)
    if ! command -v pg_dump >/dev/null 2>&1; then
        PG_DUMP_PATH="$(env_get PG_DUMP_PATH)"
        if [ -z "$PG_DUMP_PATH" ] || [ ! -x "$PG_DUMP_PATH" ]; then
            warn "pg_dump not in PATH and PG_DUMP_PATH unset — automated backups will fall back to PHP/PDO dumper (slower, larger files)"
        fi
    else
        info "pg_dump available"
    fi
}

# ─── Pre-deploy database snapshot ────────────────────────────────────
backup_before_migrate() {
    echo ""
    echo "=========================================="
    echo "  Pre-migrate database snapshot"
    echo "=========================================="
    if php artisan backup:database --quiet-success 2>/dev/null; then
        info "Database snapshot created (storage/app/backups/)"
    else
        warn "backup:database command failed — continuing without snapshot"
        if [ "${FORCE_DEPLOY:-0}" != "1" ]; then
            fail "Refusing to migrate without a recent backup. Re-run with FORCE_DEPLOY=1 to skip (NOT RECOMMENDED)."
        fi
    fi
}

# ─── Install dependencies ────────────────────────────────────────────
install_deps() {
    echo ""
    echo "=========================================="
    echo "  Installing dependencies"
    echo "=========================================="

    composer install --no-dev --optimize-autoloader --no-interaction
    info "Composer dependencies installed (production, no-dev)"

    if [ -f package.json ]; then
        if command -v npm >/dev/null 2>&1; then
            npm ci 2>/dev/null || npm install
            npm run build || fail "Vite build failed — fix the build errors before deploying"
            info "Vite assets built"
        else
            warn "npm not found — skipping frontend build (assets in public/build may be stale)"
        fi
    fi
}

# ─── Database migration ──────────────────────────────────────────────
migrate() {
    echo ""
    echo "=========================================="
    echo "  Database migration"
    echo "=========================================="

    php artisan migrate --force --no-interaction
    info "Migrations applied"
}

# ─── Optimization ────────────────────────────────────────────────────
optimize() {
    echo ""
    echo "=========================================="
    echo "  Optimizing for production"
    echo "=========================================="

    php artisan config:clear   2>/dev/null || true
    php artisan route:clear    2>/dev/null || true
    php artisan view:clear     2>/dev/null || true
    php artisan event:clear    2>/dev/null || true

    php artisan config:cache;  info "Config cached"
    php artisan route:cache;   info "Routes cached"
    php artisan view:cache;    info "Views cached"
    php artisan event:cache;   info "Events cached"
    php artisan optimize;      info "Framework optimized"

    php artisan storage:link 2>/dev/null && info "Storage linked" || true

    if [[ "$OSTYPE" == "linux-gnu"* ]] || [[ "$OSTYPE" == "darwin"* ]]; then
        chmod -R 775 storage bootstrap/cache
        info "Permissions set on storage & bootstrap/cache"
    fi
}

# ─── Rollback / Clear caches ─────────────────────────────────────────
rollback() {
    echo ""
    echo "=========================================="
    echo "  Clearing all caches"
    echo "=========================================="

    php artisan config:clear
    php artisan route:clear
    php artisan view:clear
    php artisan event:clear
    php artisan cache:clear
    php artisan optimize:clear
    info "All caches cleared"
}

# ─── Queue restart ───────────────────────────────────────────────────
restart_queue() {
    echo ""
    echo "=========================================="
    echo "  Restarting queue workers"
    echo "=========================================="

    php artisan queue:restart
    info "Queue restart signal sent"
}

# ─── Smoke test ──────────────────────────────────────────────────────
smoke_test() {
    echo ""
    echo "=========================================="
    echo "  Post-deploy smoke test"
    echo "=========================================="

    APP_URL="$(env_get APP_URL)"
    if [ -z "$APP_URL" ]; then
        warn "APP_URL not set — skipping HTTP smoke test"
        return
    fi

    if command -v curl >/dev/null 2>&1; then
        HTTP_CODE=$(curl -s -o /dev/null -w '%{http_code}' --max-time 10 "$APP_URL" || echo "000")
        if [[ "$HTTP_CODE" =~ ^(200|301|302)$ ]]; then
            info "Smoke test OK (HTTP $HTTP_CODE from $APP_URL)"
        else
            warn "Smoke test returned HTTP $HTTP_CODE from $APP_URL — investigate immediately"
        fi
    fi
}

# ─── Summary ─────────────────────────────────────────────────────────
summary() {
    echo ""
    echo "=========================================="
    echo -e "  ${GREEN}Deploy complete!${NC}"
    echo "=========================================="
    echo ""
    echo "  Next steps:"
    echo "  - Tail logs:        tail -f storage/logs/laravel.log"
    echo "  - Monitor queue:    php artisan queue:work --tries=3"
    echo "  - Verify Sentry:    check Sentry dashboard for new release"
    echo ""
}

# ─── Main ────────────────────────────────────────────────────────────
case "${1:-full}" in
    full)
        preflight
        backup_before_migrate
        install_deps
        migrate
        optimize
        restart_queue
        smoke_test
        summary
        ;;
    preflight)
        preflight
        info "Preflight passed — safe to run 'bash deploy.sh' for full deploy"
        ;;
    optimize)
        preflight
        optimize
        restart_queue
        info "Optimization complete"
        ;;
    rollback)
        rollback
        info "Rollback complete — caches cleared"
        ;;
    *)
        echo "Usage: bash deploy.sh [full|preflight|optimize|rollback]"
        exit 1
        ;;
esac

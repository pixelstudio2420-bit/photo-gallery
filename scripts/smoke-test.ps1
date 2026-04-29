# ─────────────────────────────────────────────────────────────────────
#  Smoke test: verify pgsql fork code is syntactically correct +
#  Laravel can boot against the configured DB connection.
# ─────────────────────────────────────────────────────────────────────
#  Prerequisites:
#    - Postgres running (e.g. via `docker compose up -d`)
#    - .env configured with DB_CONNECTION=pgsql + correct creds
#    - composer install + npm install completed
#  Usage:
#    powershell -ExecutionPolicy Bypass -File scripts/smoke-test.ps1
# ─────────────────────────────────────────────────────────────────────

$ErrorActionPreference = "Continue"
$root = Split-Path -Parent $PSScriptRoot
Set-Location $root

$pass = 0
$fail = 0

function Test-Step {
    param([string]$label, [scriptblock]$check)
    Write-Host "  ▶ $label..." -NoNewline
    $result = & $check
    if ($result) {
        Write-Host " ✅" -ForegroundColor Green
        $script:pass++
    } else {
        Write-Host " ❌" -ForegroundColor Red
        $script:fail++
    }
}

Write-Host ""
Write-Host "═══ Photo Gallery (Postgres fork) — smoke test ═══" -ForegroundColor Cyan
Write-Host ""

# 1. PHP syntax check on all modified files
Write-Host "─── 1. PHP lint (modified files) ───"
$modifiedFiles = @(
    "app/Models/Booking.php",
    "app/Models/BlogNewsSource.php",
    "app/Http/Controllers/Admin/DashboardController.php",
    "app/Http/Controllers/Admin/SettingsController.php",
    "app/Http/Controllers/Admin/Concerns/HandlesStorage.php",
    "app/Http/Controllers/Admin/Concerns/HandlesMedia.php",
    "app/Http/Controllers/Admin/UserController.php",
    "app/Http/Controllers/Admin/UserFilesController.php",
    "app/Http/Controllers/Admin/AdminManagementController.php",
    "app/Http/Controllers/Admin/EventController.php",
    "app/Http/Controllers/Admin/FinanceController.php",
    "app/Http/Controllers/Admin/LegalPageController.php",
    "app/Http/Controllers/Admin/PhotographerController.php",
    "app/Http/Controllers/Photographer/AnalyticsController.php",
    "app/Http/Controllers/Photographer/DashboardController.php",
    "app/Http/Controllers/Public/EventController.php",
    "app/Http/Controllers/Public/LegalController.php",
    "app/Http/Controllers/Public/PhotographerController.php",
    "app/Services/Blog/NewsAggregatorService.php",
    "app/Services/UnitEconomicsService.php"
)
foreach ($f in $modifiedFiles) {
    Test-Step "$f" {
        $output = & php -l $f 2>&1
        return $output -match "No syntax errors"
    }
}

# 2. Composer autoload integrity
Write-Host ""
Write-Host "─── 2. Composer autoload ───"
Test-Step "composer dump-autoload" {
    $output = & composer dump-autoload --optimize 2>&1
    return $LASTEXITCODE -eq 0
}

# 3. Laravel can boot (config + routes parse)
Write-Host ""
Write-Host "─── 3. Laravel artisan boot ───"
Test-Step "artisan --version" {
    $output = & php artisan --version 2>&1
    return $output -match "Laravel Framework"
}
Test-Step "artisan route:list (no DB needed)" {
    $output = & php artisan route:list --path=admin 2>&1 | Out-String
    return $LASTEXITCODE -eq 0
}
Test-Step "config:cache (validates all config files)" {
    $output = & php artisan config:clear 2>&1
    $output = & php artisan config:cache 2>&1
    return $LASTEXITCODE -eq 0
}

# 4. DB connectivity (only if DB_CONNECTION=pgsql configured)
Write-Host ""
Write-Host "─── 4. Postgres connection ───"
Test-Step "DB::connection() resolves to pgsql" {
    $output = & php -r "require 'vendor/autoload.php'; `$app = require_once 'bootstrap/app.php'; `$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); echo DB::connection()->getDriverName();" 2>&1
    return $output -match "pgsql"
}
Test-Step "Postgres reachable + version" {
    $output = & php artisan tinker --execute="echo DB::connection()->select('SELECT version()')[0]->version ?? 'fail';" 2>&1 | Out-String
    if ($output -match "PostgreSQL") {
        Write-Host "      → $($matches[0])" -ForegroundColor DarkGray
        return $true
    }
    return $false
}

# 5. Migrations can run (requires reachable DB)
Write-Host ""
Write-Host "─── 5. Migrations ───"
Test-Step "migrate --pretend (dry run)" {
    $output = & php artisan migrate --pretend 2>&1 | Out-String
    return $LASTEXITCODE -eq 0
}

# Summary
Write-Host ""
Write-Host "══════════════════════════════════════════════════" -ForegroundColor Cyan
Write-Host "  RESULT: $pass passed · $fail failed" -ForegroundColor $(if ($fail -eq 0) { "Green" } else { "Yellow" })
Write-Host "══════════════════════════════════════════════════" -ForegroundColor Cyan

if ($fail -gt 0) { exit 1 } else { exit 0 }

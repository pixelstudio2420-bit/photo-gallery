# CLAUDE.md — Project guide for AI agents & developers

> Photo marketplace + photographer SaaS (loadroop.com). Laravel 12 / PHP 8.2 /
> PostgreSQL. This file is the fast-onboarding map: stack, how to run things,
> the architecture, and — most importantly — the **gotchas that have actually
> bitten us**. Read the Gotchas section before touching DB code.

---

## 1. What this is

A two-sided marketplace where **photographers** upload event photos (weddings,
graduations, runs, concerts) and **customers** buy them (per-photo, bundles, or
face-search "find all photos of me"). Layered on top: photographer
subscriptions, consumer cloud storage, bookings, blog/SEO, marketing
automation, and a large admin back office.

Production: **loadroop.com** on Laravel Cloud. Primary DB: **PostgreSQL**.
Object storage: **Cloudflare R2** ($0.015/GB/mo, no egress fee).

---

## 2. Stack & environments

| | Production | Tests |
|---|---|---|
| DB | PostgreSQL | **SQLite `:memory:`** (see `phpunit.xml`) |
| Cache | (configured) | `array` |
| Queue | (configured) | `sync` |
| Object storage | Cloudflare R2 | faked |

**The prod/test DB split is the #1 source of test failures.** Any raw SQL that
uses Postgres-only features breaks the SQLite test suite. See Gotchas §6.

Key deps: `aws/aws-sdk-php` (R2/S3/Rekognition), `stripe/stripe-php`,
`sentry/sentry-laravel`, `barryvdh/laravel-dompdf`, `endroid/qr-code`,
`laravel/socialite`.

---

## 3. Running it

```bash
# Tests (uses sqlite :memory:, runs all migrations via RefreshDatabase)
php artisan test                       # everything
php artisan test --testsuite=Unit      # ~292 tests, ~40s
php artisan test --testsuite=Feature
php artisan test tests/Unit/Foo/BarTest.php   # one file
php artisan test --filter='method name fragment'

# Migrations
php artisan migrate
php artisan migrate:fresh --env=testing    # sanity-check a migration on sqlite

# Lint a PHP file before committing
php -l path/to/File.php
```

PHPUnit colorizes output; when grepping, strip ANSI: `... | sed 's/\x1b\[[0-9;]*m//g'`.

---

## 4. Architecture map

Big codebase (~139k LOC in `app/`). Rough shape:

| Layer | Count | Notes |
|---|---|---|
| Migrations | ~185 | 37% are patches (`add_/fix_/enhance_`). Timestamp-ordered. |
| Controllers | ~174 | `Admin\`, `Photographer\`, `Public\`, `Api\`. Some are huge. |
| Services | ~195 | Business logic lives here. Prefer adding here over fat controllers. |
| Models | ~121 | Eloquent. |
| Console commands | ~73 | Most are scheduled (see `routes/console.php`). |
| Jobs | ~28 | Queued work (purge, mirror, LINE push, payouts). |
| Blade views | ~495 | Some 2000+ lines (`events/show`, `face-search`). |

**God objects to be careful editing** (split via `Concerns` traits where you can):
`SubscriptionService` (1.9k), `PaymentWebhookController` (1.7k),
`Admin\Concerns\HandlesStorage` (1.6k), `Admin\SettingsController` (1.5k),
`PaymentController` (1.4k), `SeoService` (1.3k).

`SettingsController` composes domain traits from `app/Http/Controllers/Admin/Concerns/*`
(`HandlesStorage`, `HandlesMedia`, `HandlesIntegrations`, `HandlesQueueManagement`,
`HandlesTwoFactor`). Routed methods still resolve through the trait.

Routes: `routes/web.php` (~2.2k lines), plus `api.php`, `console.php` (scheduler),
`blog.php`, `festivals.php`, `announcements.php`.

---

## 5. Key subsystems

- **Subscriptions** (`SubscriptionService`, `photographer_subscriptions`):
  active → grace → expired lifecycle. Cron renews + enforces (see
  `routes/console.php` "Subscription billing"). `photographer_profiles`
  carries a denormalised `subscription_plan_code` + `tier`
  (`PhotographerProfile::TIER_CREATOR|TIER_SELLER|TIER_PRO`).
- **Storage quota** (`StorageQuotaService`): per-tier byte caps, enforced on
  upload. `photographer_profiles.storage_used_bytes` reconciled nightly.
- **Retention engine** (`events:purge-expired`, `Event.php`, `PurgeEventJob`,
  `PurgeEventOriginalsJob`): auto-archives/deletes old events to reclaim R2.
  Two modes — `portfolio` (wipe originals, keep cover+preview+watermark; ~96%
  recovery) vs `full` (wipe everything). **Per-tier + lifecycle-aware**: see
  `Event::effectiveRetentionTier()` (a churned Pro downgrades to Creator
  retention), `Event::tierRetentionDays()`, `Event::tierRetentionMode()`.
  Admin UI at `/admin/settings/retention`. R2 cost widget on `/admin`
  (`R2CostEstimatorService`).
- **Face search** (`FaceSearchService`, AWS Rekognition): cost-capped via
  `FaceSearchBudget` + admin caps. `detectFaces()` returns a STRUCTURED array
  (`faces`, `error`, `error_code`) — not a bare list.
- **Payments** (`Payment\*Gateway` behind `PaymentGatewayInterface`): PromptPay,
  bank transfer, Stripe, 2C2P, LINE Pay, TrueMoney. Slip verification via SlipOK.
- **Payouts** (`Payout\*` behind `PayoutProviderInterface`): Omise + mock.
  Ledger is immutable (status-flip only).
- **Usage metering** (`Usage\*`): per-resource quotas, circuit breaker, spike
  detection. Caps from `config/usage.php` `plan_caps`, with DB-driven AI credits
  override (`subscription_plans.monthly_ai_credits`).

---

## 6. ⚠️ Gotchas (read before writing DB code)

### 6.1 PostgreSQL in prod, SQLite in tests
Raw SQL using Postgres-only features **passes in prod but crashes the test
suite**. Always driver-guard raw SQL:

```php
$driver = \Schema::getConnection()->getDriverName();   // 'pgsql' | 'sqlite' | 'mysql'
if ($driver === 'pgsql') { /* pgsql path */ }
else { /* portable Schema-builder path for sqlite/mysql */ }
```

Postgres-only things that DON'T exist on SQLite:
- `information_schema.columns` / `.statistics` — use `Schema::hasColumn()`,
  `Schema::hasTable()`, or driver-guard. (This exact bug broke the whole suite —
  see migration `2026_05_01_052445_make_photo_count_nullable_on_pricing_packages`.)
- `STRING_AGG`, `ILIKE`, JSONB operators, `ALTER COLUMN ... DROP NOT NULL`.
- For nullability changes, prefer the native `->nullable()->change()` Schema
  builder (Laravel 11+, no doctrine/dbal) on non-pgsql drivers.

### 6.2 JSONB `?` operator collides with PDO placeholders
PHP's PDO treats `?` as a positional bind placeholder. The JSONB key-exists
operators (`?`, `?|`, `?&`) get eaten → syntax error → 500. **Use `@>` (contains)
instead**, binding a JSON-encoded value:

```php
// ❌ ->whereRaw("specialties::jsonb ? ?", [$tag])     // PDO mangles both ?
// ✅
->whereRaw('pp.specialties::jsonb @> ?::jsonb', [json_encode([$tag])]);
```
(Real incident: `/photographers?specialty=X` → 500. Fixed in commit `6441a0f`.)

### 6.3 AppSetting config store (~339 keys)
Key-value settings in `app_settings`, cached. API: `AppSetting::get($key, $default)`,
`::set()`, `::setMany()`, `::getAll()`, `::flushCache()`. **There is no `forget()`.**
After writing settings that feed a cached service, flush that service's cache too
(e.g. `R2CostEstimatorService::flushCache()`, `StorageQuotaService::flushAdminCache()`).

Beware **dual sources of truth**: the USD→THB rate exists both as AppSetting
`usd_thb_rate` AND `config('usage.usd_to_thb_rate')`. R2 cost rate exists as
AppSetting `r2_cost_per_gb_month_usd` but is still hard-coded `0.015` in a couple
of older services. When you touch a rate, grep for both forms.

### 6.4 Migrations that SEED can pollute tests
Some migrations seed rows/settings (e.g.
`2026_05_19_000015_seed_how_to_landing_pages` seeds ~12 landing pages AND sets
`marketing_enabled='1'`). Tests asserting "empty table" or "off by default" must
establish their own precondition (truncate / set), not assume a pristine DB.
RefreshDatabase replays ALL migrations including seeds.

### 6.5 Hand-rolled test fixtures drift from schema
Some Unit tests build their own tables in `setUp()` instead of using migrations
(e.g. `SlipFingerprintServiceTest`, `QuotaServiceTest`). When the real schema
gains a column/table the service now needs, those fixtures must be updated in
lockstep or you get "no such table/column" in tests only.

### 6.6 Idempotent settings migrations use a marker row
The retention/cost migrations record what they inserted in a
`__..._inserted__` marker key so `down()` can roll back precisely without
nuking admin-tuned values. Follow that pattern for new settings migrations.

---

## 7. Conventions

- **Business logic in Services**, not controllers. Controllers stay thin
  (though many legacy ones don't — don't make them worse).
- **Commit messages**: conventional prefix (`feat(scope):`, `fix(scope):`),
  detailed body explaining WHY. End with:
  `Co-Authored-By: Claude <noreply@anthropic.com>`.
- **Never commit/push unless asked.** When asked, branch off `main` if needed.
- **Test for real**: the project owner's standing rule is *don't guess, verify*.
  Back claims with an actually-run test (transaction-isolated tinker scripts in
  `storage/app/__*.php`, deleted after — see git history for examples).
- **Defensive widgets**: dashboard partials wrap their data source in
  try/catch and render an empty state if the service throws, so one widget
  can't break a whole dashboard.

---

## 8. Scheduler (`routes/console.php`)

Nightly retention/cost cluster:
- `02:00` warn photographers (`WarnUpcomingCleanupJob`)
- `02:30` `events:purge-expired` (auto-archive/delete)
- `02:45` `photos:compress-originals` (recompress aged JPEGs)
- `03:00` `backup:database`
- `03:45` `photographers:recalc-storage`
- weekly Sun `05:30` `accounts:sweep-inactive` (OFF by default)

Plus hourly subscription renew/charge/expire, LINE health, usage rollups,
security scan, SEO audit, payout triggers. Most commands no-op when their
master AppSetting is off, so they're safe to keep scheduled.

---

## 9. Known weaknesses (as of 2026-06)

Honest assessment for context, not self-flagellation:
- **Over-built for current scale** (~139k LOC, ~7 active photographers).
- **Bus factor 1** — single author, ~8 commits/day for a month.
- **God objects** (see §4).
- **Test suite**: Unit is green (292) after the 2026-06 portability fixes;
  Feature suite may still have environment-sensitive cases.
- **Schema churn** (37% patch migrations) → some dead columns.
- **Raw SQL surface** (~227 `DB::raw`/`selectRaw`/`whereRaw`) — driver-audit
  when editing.
- **Config sprawl** (~339 AppSetting keys), some duplicated in `config/*`.

When adding features, prefer hardening/maintaining what exists over expanding
surface until the product has more traction.

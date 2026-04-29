# 🐘 Photo Gallery — Postgres Fork

This is the **PostgreSQL** variant of the photo gallery / photographer marketplace.
The original MySQL/MariaDB version lives at `../photo-gallery-tailwind/` and stays
intact — this fork is for deploying to **Laravel Cloud Serverless Postgres**,
**Neon**, **Supabase**, or any vanilla PG 14+ server.

---

## 📋 Why a separate fork?

The codebase had **30+ files** with MySQL-specific SQL (`DATE_ADD INTERVAL`,
`MATCH AGAINST`, `FIELD()`, `CURDATE()`, etc.). Maintaining one codebase that
runs on both is more invasive than keeping two parallel forks. This fork has
all those MySQL idioms swapped for Postgres-native equivalents.

| Original (MySQL) | This fork (Postgres) |
|---|---|
| `DATE_ADD(col, INTERVAL n MINUTE)` | `col + (n \|\| ' minutes')::interval` |
| `DATE_SUB(NOW(), INTERVAL 7 DAY)` | `NOW() - INTERVAL '7 days'` |
| `DATE_FORMAT(c, '%Y-%m')` | `to_char(c, 'YYYY-MM')` |
| `FIELD(col, 'a','b','c')` | `CASE col WHEN 'a' THEN 1 WHEN 'b' THEN 2 …` |
| `MATCH(c) AGAINST(?)` | `c ILIKE '%term%'` (or `tsvector` for production) |
| `GROUP_CONCAT(c)` | `STRING_AGG(c::text, ',')` |
| `SUBSTRING_INDEX(c,'_',1)` | `split_part(c, '_', 1)` |
| `SUM(boolean_expr)` | `COUNT(*) FILTER (WHERE expr)` |
| `CURDATE()` | `CURRENT_DATE` |
| `YEAR(c)=YEAR(NOW()) AND MONTH(c)=MONTH(NOW())` | `date_trunc('month', c) = date_trunc('month', NOW())` |
| `SET FOREIGN_KEY_CHECKS=0` | (not needed — `TRUNCATE … CASCADE`) |
| `mysqldump` / `MYSQL_PWD` | `pg_dump` / `PGPASSWORD` |
| `is_active = 1` (TINYINT) | `is_active = true` (BOOLEAN) |

---

## 🚀 Quick Start (Local Dev)

### 1. Install PostgreSQL 16 (Windows)

Either:
- **Native installer:** https://www.postgresql.org/download/windows/ → install + start service
- **Docker:** `docker run -d --name pg -e POSTGRES_PASSWORD=secret -p 5432:5432 postgres:16`

### 2. Create database

```bash
psql -U postgres -c "CREATE DATABASE jabphap WITH ENCODING='UTF8' LC_COLLATE='C' LC_CTYPE='C' TEMPLATE template0;"
```

### 3. Configure `.env`

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env`:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=jabphap
DB_USERNAME=postgres
DB_PASSWORD=secret
```

### 4. Install dependencies + run migrations

```bash
composer install
npm install && npm run build
php artisan migrate --seed
```

### 5. Start dev server

```bash
php artisan serve
```

Open http://localhost:8000

---

## ☁️ Deploy to Laravel Cloud (Serverless Postgres)

This fork is purpose-built for Laravel Cloud's **Serverless Postgres** offering
(Neon-powered, scale-to-zero). Follow the existing [`docs/deployment/laravel-cloud.html`](docs/deployment/laravel-cloud.html)
guide but at **Step 5.2** choose:

- **Database type:** Serverless Postgres (instead of MySQL)
- **Region:** Singapore

All ENV variables are auto-wired by Cloud — including `DB_HOST`, `DB_PORT`,
`DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `DB_SSLMODE=require`.

### Migration from existing MySQL data → Postgres

If you have data in the MySQL version that you want to bring over:

```bash
# 1. On the MySQL side — export tables (data only, no DDL):
mysqldump --no-create-info --complete-insert --skip-extended-insert \
          --hex-blob --default-character-set=utf8mb4 \
          -u USER -p photo_gallery_v2 > mysql-data.sql

# 2. Convert MySQL INSERT syntax → Postgres-compatible
#    (backticks → double-quotes, escape sequences, boolean values)
python convert-mysql-to-pgsql.py mysql-data.sql > pgsql-data.sql

# 3. Load into Postgres (after running migrations on PG side):
psql -h HOST -U USER -d jabphap -f pgsql-data.sql
```

**Tools we recommend:**
- [pgloader](https://pgloader.io/) — automatic MySQL → PG migration including DDL
- [mysql2postgresql](https://github.com/dimitri/pgloader) — alternative
- Manual approach: dump data only, let PG migrations create schema, then INSERT data

---

## 🔍 Files Modified vs Original

| File | What changed |
|---|---|
| `config/database.php` | Default driver `sqlite` → `pgsql` |
| `.env.example` | DB_CONNECTION + port + driver-specific config |
| `app/Models/Booking.php` | overlap detection — `(col \|\| ' minutes')::interval` |
| `app/Models/BlogNewsSource.php` | `INTERVAL` syntax for due-source check |
| `app/Http/Controllers/Admin/AdminManagementController.php` | `FIELD()` → CASE |
| `app/Http/Controllers/Admin/DashboardController.php` | All raw aggregate queries (CURDATE, YEAR/MONTH, SUM-boolean, INTERVAL) |
| `app/Http/Controllers/Admin/EventController.php` | `MATCH AGAINST` → `ILIKE` |
| `app/Http/Controllers/Admin/FinanceController.php` | `DATE_FORMAT` → `to_char`, `CONCAT` → `\|\|` |
| `app/Http/Controllers/Admin/LegalPageController.php` | `FIELD()` → CASE |
| `app/Http/Controllers/Admin/PhotographerController.php` | `FIELD()` → CASE |
| `app/Http/Controllers/Admin/SettingsController.php` | `SET FOREIGN_KEY_CHECKS` removed (CASCADE handles it) |
| `app/Http/Controllers/Admin/UserController.php` | SUM(boolean) → COUNT FILTER + CURRENT_DATE |
| `app/Http/Controllers/Admin/UserFilesController.php` | `SUBSTRING_INDEX` → regex |
| `app/Http/Controllers/Admin/Concerns/HandlesMedia.php` | `SUBSTRING_INDEX` → `split_part` |
| `app/Http/Controllers/Admin/Concerns/HandlesStorage.php` | `mysqldump`/PHP fallback → `pg_dump`/PHP fallback |
| `app/Http/Controllers/Photographer/AnalyticsController.php` | `DATE_FORMAT`, `CONCAT` |
| `app/Http/Controllers/Photographer/DashboardController.php` | `YEAR()/MONTH()` → `EXTRACT()` |
| `app/Http/Controllers/Public/EventController.php` | `MATCH AGAINST` → `ILIKE`, SUM(boolean) → COUNT FILTER |
| `app/Http/Controllers/Public/LegalController.php` | `FIELD()` → CASE |
| `app/Http/Controllers/Public/PhotographerController.php` | `GROUP_CONCAT` → `STRING_AGG` |
| `app/Services/Blog/NewsAggregatorService.php` | `INTERVAL` syntax |
| `app/Services/UnitEconomicsService.php` | `DATE_FORMAT` → `to_char` |

---

## ⚠️ Known Limitations

### 1. **Search performance**
`MATCH AGAINST` (MySQL fulltext index) was replaced with `ILIKE '%term%'`. This
works correctly but performs full table scans on `event_events`. For production
deployments with > 50k events, switch to a tsvector GIN index:

```sql
ALTER TABLE event_events ADD COLUMN search_tsv tsvector;

CREATE INDEX event_events_search_idx ON event_events USING GIN(search_tsv);

CREATE TRIGGER event_events_tsv_update BEFORE INSERT OR UPDATE ON event_events
FOR EACH ROW EXECUTE FUNCTION
  tsvector_update_trigger(search_tsv, 'pg_catalog.simple', name, description, location);

UPDATE event_events SET search_tsv = to_tsvector('simple',
  coalesce(name,'') || ' ' || coalesce(description,'') || ' ' || coalesce(location,'')
);
```

Then change controller queries to:
```php
$q->whereRaw("search_tsv @@ plainto_tsquery('simple', ?)", [$search])
```

### 2. **LIKE case-sensitivity**
On MySQL, `WHERE name LIKE '%foo%'` is case-insensitive by default. On Postgres
it's case-sensitive — we changed the high-traffic event search queries to `ILIKE`,
but other admin search inputs (~80 places) still use `like`. They work, but match
"Foo" only when typed exactly. To globally make them case-insensitive, do a
project-wide replace `'like',` → `'ilike',`.

### 3. **Backup tooling**
The `/admin/settings → Backup` button now tries `pg_dump`. On Windows it auto-discovers:
- `C:\Program Files\PostgreSQL\17\bin\pg_dump.exe` (and 16, 15, 14, 13, 12)

If pg_dump isn't found → falls back to a PHP-native data-only dumper. Restore
this dump only **after** running `php artisan migrate` to create the schema.

### 4. **Char encoding**
Postgres uses `UTF8` (no `utf8mb4` distinction). Created the DB with
`ENCODING='UTF8'` — Thai + emoji storage works the same as MariaDB
`utf8mb4_unicode_ci`.

### 5. **Migrations**
All Laravel migrations in `database/migrations/` work as-is on Postgres
because they use Laravel's schema builder (driver-agnostic). The `enum()` calls
become PG `CHECK` constraints automatically.

---

## ✅ Smoke test checklist

After install + migrate, hit these endpoints to confirm everything renders:

- [ ] `GET /` — public home
- [ ] `GET /events` — event browse + ILIKE search works
- [ ] `GET /admin/dashboard` — stats render (today/month/year aggregates)
- [ ] `GET /admin/users` — user filter with date ranges
- [ ] `GET /admin/finance/reports` — revenue chart with daily/weekly/monthly periods
- [ ] `GET /admin/admins` — admins ordered superadmin → admin → editor
- [ ] `GET /photographer/dashboard` — 6-month revenue sparkline
- [ ] `GET /photographer/analytics` — monthly trend chart
- [ ] `GET /photographer/bookings` — booking calendar (Phase 1+2 features)
- [ ] Booking conflict detection — try to confirm overlapping bookings
- [ ] `GET /admin/legal/pages` — Privacy/Terms/Refund priority order

---

## 🔗 Related

- Original MySQL fork: `../photo-gallery-tailwind/`
- Laravel Cloud deploy guide: [`docs/deployment/laravel-cloud.html`](docs/deployment/laravel-cloud.html)
- Hostinger deploy guide (MySQL only): `../photo-gallery-tailwind/docs/deployment/hostinger.html`

---

**Created:** 2026-04-27  
**Branch:** `pgsql-fork`  
**Compatibility:** PostgreSQL 14+ · Laravel 12 · PHP 8.2+

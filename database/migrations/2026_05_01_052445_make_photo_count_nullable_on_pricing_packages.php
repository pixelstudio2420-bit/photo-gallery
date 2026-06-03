<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Make pricing_packages.photo_count nullable.
 *
 * Why: face_match bundles ("buy all photos of you") cannot pre-declare a
 * photo count — the count is whatever face-search returns for the buyer,
 * and that's resolved at view/checkout time, not at row-insertion time.
 *
 * The original schema (from 2026-04 era when only fixed-count bundles
 * existed) made photo_count NOT NULL. With the 2026-05-01 bundle redesign
 * adding face_match + event_all (where the count is dynamic / informational
 * only), every face_match seed insert hit a 23502 not-null violation.
 *
 * Fix: drop NOT NULL on the column. Existing count-bundle rows are
 * unaffected (their photo_count values stay populated).
 *
 * Postgres-specific: the doctrine/dbal change() helper would also work,
 * but going through DB::statement() is dependency-free and the SQL is
 * trivially auditable.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('pricing_packages')
            || !Schema::hasColumn('pricing_packages', 'photo_count')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            // Postgres: idempotent check via information_schema, then ALTER.
            // information_schema.columns only exists on pgsql/mysql — guarding
            // by driver keeps this migration portable to the SQLite test DB
            // (where this exact query previously threw "no such table:
            // information_schema.columns" and broke the whole suite).
            $info = DB::selectOne(
                "SELECT is_nullable FROM information_schema.columns
                  WHERE table_name = 'pricing_packages' AND column_name = 'photo_count'"
            );
            if ($info && strtoupper((string) $info->is_nullable) === 'NO') {
                DB::statement('ALTER TABLE pricing_packages ALTER COLUMN photo_count DROP NOT NULL');
            }
            return;
        }

        // MySQL / MariaDB / SQLite (incl. the in-memory test DB) — use
        // Laravel's portable schema builder. change() is native in
        // Laravel 11+ (no doctrine/dbal needed). Matches the original
        // unsignedInteger definition from create_events_tables, now nullable.
        Schema::table('pricing_packages', function (Blueprint $table) {
            $table->unsignedInteger('photo_count')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('pricing_packages')
            || !Schema::hasColumn('pricing_packages', 'photo_count')) {
            return;
        }

        // Re-imposing NOT NULL would fail if any face_match rows exist
        // with NULL photo_count. So down() backfills 0 first to avoid
        // breaking the rollback.
        DB::table('pricing_packages')->whereNull('photo_count')->update(['photo_count' => 0]);

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE pricing_packages ALTER COLUMN photo_count SET NOT NULL');
            return;
        }
        Schema::table('pricing_packages', function (Blueprint $table) {
            $table->unsignedInteger('photo_count')->nullable(false)->change();
        });
    }
};

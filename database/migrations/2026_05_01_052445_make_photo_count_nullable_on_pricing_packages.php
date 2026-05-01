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
        // Idempotent: check current nullability before running. Running
        // ALTER on an already-nullable column is harmless on Postgres,
        // but we skip it for the audit-trail/log clarity.
        $info = DB::selectOne(
            "SELECT is_nullable FROM information_schema.columns
              WHERE table_name = 'pricing_packages' AND column_name = 'photo_count'"
        );

        if ($info && strtoupper((string) $info->is_nullable) === 'NO') {
            DB::statement('ALTER TABLE pricing_packages ALTER COLUMN photo_count DROP NOT NULL');
        }
    }

    public function down(): void
    {
        // Re-imposing NOT NULL would fail if any face_match rows exist
        // with NULL photo_count. So down() backfills 0 first to avoid
        // breaking the rollback.
        DB::table('pricing_packages')->whereNull('photo_count')->update(['photo_count' => 0]);
        DB::statement('ALTER TABLE pricing_packages ALTER COLUMN photo_count SET NOT NULL');
    }
};

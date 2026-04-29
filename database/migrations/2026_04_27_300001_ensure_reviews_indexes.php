<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Idempotent repair for reviews indexes.
 *
 * Why this exists
 * ---------------
 * Migration 2026_04_17_110000_enhance_reviews_system.php adds the
 * `status` and `is_visible` columns AND the
 * `reviews_status_visible_idx` index in the SAME up() block.
 * Production logs show the index creation occasionally aborts with
 * SQLSTATE[25P02] "current transaction is aborted" — usually because
 * a PRIOR migration in the same boot rolled the txn into an error
 * state, and Postgres rejects every subsequent DDL until COMMIT.
 *
 * This migration runs in its OWN transaction (the Laravel default),
 * so any prior poison state is cleared. Re-creating indexes that
 * already exist is a no-op via the existence checks below.
 *
 * Side-effect free on environments where the index is already
 * present.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('reviews')) {
            return;
        }
        // Both columns must exist before the index can be created.
        // If they don't, the upstream migration didn't run yet —
        // bail out and let it run first.
        $hasStatus    = Schema::hasColumn('reviews', 'status');
        $hasIsVisible = Schema::hasColumn('reviews', 'is_visible');
        if (!$hasStatus || !$hasIsVisible) {
            return;
        }

        if (!$this->indexExists('reviews', 'reviews_status_visible_idx')) {
            Schema::table('reviews', function (Blueprint $t) {
                $t->index(['status', 'is_visible'], 'reviews_status_visible_idx');
            });
        }
        if (Schema::hasColumn('reviews', 'event_id')
            && !$this->indexExists('reviews', 'reviews_event_visible_idx')) {
            Schema::table('reviews', function (Blueprint $t) {
                $t->index(['event_id', 'is_visible'], 'reviews_event_visible_idx');
            });
        }
    }

    public function down(): void
    {
        // Intentionally a no-op — the original migration owns these
        // indexes; this one only repairs. Down should NOT remove them
        // because the source migration's down() already drops them.
    }

    /**
     * Driver-portable index-existence check.
     */
    private function indexExists(string $table, string $indexName): bool
    {
        $driver = DB::connection()->getDriverName();
        try {
            return match ($driver) {
                'pgsql'  => (bool) DB::selectOne(
                    "SELECT 1 FROM pg_indexes WHERE tablename = ? AND indexname = ?",
                    [$table, $indexName],
                ),
                'mysql'  => (bool) DB::selectOne(
                    "SELECT 1 FROM information_schema.statistics
                       WHERE table_schema = DATABASE()
                         AND table_name = ?
                         AND index_name = ?",
                    [$table, $indexName],
                ),
                'sqlite' => (bool) DB::selectOne(
                    "SELECT 1 FROM sqlite_master WHERE type='index' AND name = ?",
                    [$indexName],
                ),
                default  => false,
            };
        } catch (\Throwable) {
            return false;
        }
    }
};

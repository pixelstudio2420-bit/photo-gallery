<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Enhance user_notifications to match the admin_notifications baseline.
 *
 * Two improvements:
 *
 *  1. **Add `ref_id` column** — lets us batch-dismiss notifications
 *     keyed to the same business object (e.g. mark all `payment` and
 *     `slip` notifications for order #123 as read in one query when
 *     the order flips to paid). Without this, photographer + customer
 *     bell counters keep showing stale "อัปโหลดสลิปสำเร็จ" rows after
 *     admin verifies the slip.
 *
 *  2. **Add `created_at` and `(type, is_read, created_at)` indexes** —
 *     mirrors the admin-side hot-path optimisations. The existing
 *     `(user_id, is_read)` and `(user_id, is_read, created_at)`
 *     indexes (added in 2026_04_18_000000) cover the per-user
 *     bell query, but the unreadCount endpoint and any
 *     "all-events-of-this-type" queries still filesort.
 *
 * Idempotent — checks before adding so re-running is safe.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('user_notifications')) {
            return;
        }

        Schema::table('user_notifications', function (Blueprint $table) {
            if (!Schema::hasColumn('user_notifications', 'ref_id')) {
                $table->string('ref_id', 50)->nullable()->after('action_url');
            }
        });

        // Indexes — separate Schema::table call so the column exists
        // before we add the index on it. Names mirror admin's pattern.
        Schema::table('user_notifications', function (Blueprint $table) {
            if (!$this->indexExists('user_notifications', 'user_notifications_ref_id_index')) {
                $table->index('ref_id');
            }
            if (!$this->indexExists('user_notifications', 'user_notifications_created_at_index')) {
                $table->index('created_at');
            }
            // Composite for the type-filtered admin reports + photographer
            // dashboard widgets that filter by type and order by recency.
            if (!$this->indexExists('user_notifications', 'user_notifications_type_is_read_created_at_index')) {
                $table->index(['type', 'is_read', 'created_at']);
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('user_notifications')) {
            return;
        }

        Schema::table('user_notifications', function (Blueprint $table) {
            if ($this->indexExists('user_notifications', 'user_notifications_type_is_read_created_at_index')) {
                $table->dropIndex('user_notifications_type_is_read_created_at_index');
            }
            if ($this->indexExists('user_notifications', 'user_notifications_created_at_index')) {
                $table->dropIndex('user_notifications_created_at_index');
            }
            if ($this->indexExists('user_notifications', 'user_notifications_ref_id_index')) {
                $table->dropIndex('user_notifications_ref_id_index');
            }
            if (Schema::hasColumn('user_notifications', 'ref_id')) {
                $table->dropColumn('ref_id');
            }
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        $driver = DB::connection()->getDriverName();
        if ($driver === 'pgsql') {
            $result = DB::selectOne(
                "SELECT COUNT(*) AS cnt FROM pg_indexes
                 WHERE schemaname = current_schema() AND tablename = ? AND indexname = ?",
                [$table, $index]
            );
            return (int) ($result->cnt ?? 0) > 0;
        }
        if ($driver === 'sqlite') {
            $result = DB::selectOne(
                "SELECT COUNT(*) AS cnt FROM sqlite_master
                 WHERE type = 'index' AND tbl_name = ? AND name = ?",
                [$table, $index]
            );
            return (int) ($result->cnt ?? 0) > 0;
        }
        $database = DB::connection()->getDatabaseName();
        $result = DB::selectOne(
            "SELECT COUNT(*) AS cnt
             FROM information_schema.statistics
             WHERE table_schema = ? AND table_name = ? AND index_name = ?",
            [$database, $table, $index]
        );
        return (int) ($result->cnt ?? 0) > 0;
    }
};

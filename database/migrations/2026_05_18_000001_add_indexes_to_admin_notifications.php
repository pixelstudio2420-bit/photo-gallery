<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add performance indexes to admin_notifications.
 *
 * The original migration only indexed `type`, `ref_id`, and `is_read` —
 * none of those help the two hot queries the system runs every 15-30s
 * per logged-in admin tab:
 *
 *   1.  /admin/notifications/api          (poll cursor)
 *       SELECT * FROM admin_notifications
 *       [WHERE id > ?]
 *       ORDER BY created_at DESC
 *       LIMIT 50
 *
 *   2.  /admin/notifications              (full page)
 *       SELECT COUNT(*) FROM ... WHERE is_read = 0
 *       SELECT COUNT(*) FROM ... WHERE created_at >= ...
 *
 * Without a created_at index the first query does a filesort once the
 * table grows past a few thousand rows. The composite (is_read,
 * created_at) covers the common "unread, ordered by recency" path used
 * by the bell-icon dropdown.
 *
 * Idempotent — checks before adding so re-running on a partially-applied
 * schema is safe.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('admin_notifications')) {
            return;
        }

        Schema::table('admin_notifications', function (Blueprint $table) {
            // Plain `created_at` index — speeds up `ORDER BY created_at DESC LIMIT N`.
            if (!$this->indexExists('admin_notifications', 'admin_notifications_created_at_index')) {
                $table->index('created_at');
            }
            // Composite index for "unread, newest first" — the bell dropdown.
            if (!$this->indexExists('admin_notifications', 'admin_notifications_is_read_created_at_index')) {
                $table->index(['is_read', 'created_at']);
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('admin_notifications')) {
            return;
        }

        Schema::table('admin_notifications', function (Blueprint $table) {
            if ($this->indexExists('admin_notifications', 'admin_notifications_is_read_created_at_index')) {
                $table->dropIndex('admin_notifications_is_read_created_at_index');
            }
            if ($this->indexExists('admin_notifications', 'admin_notifications_created_at_index')) {
                $table->dropIndex('admin_notifications_created_at_index');
            }
        });
    }

    /**
     * Cross-driver index existence check.
     * Schema::hasIndex was added in Laravel 11, but defaulting to a raw
     * INFORMATION_SCHEMA lookup keeps this migration runnable on older
     * MySQL/MariaDB even if the framework changes shape.
     */
    private function indexExists(string $table, string $index): bool
    {
        $driver = DB::connection()->getDriverName();
        if ($driver === 'pgsql') {
            // Postgres: pg_indexes is the canonical source.
            $result = DB::selectOne(
                "SELECT COUNT(*) AS cnt FROM pg_indexes
                 WHERE schemaname = current_schema() AND tablename = ? AND indexname = ?",
                [$table, $index]
            );
            return (int) ($result->cnt ?? 0) > 0;
        }
        if ($driver === 'sqlite') {
            // SQLite: sqlite_master holds index metadata. Used by the test
            // suite (in-memory sqlite) so the migration is portable.
            $result = DB::selectOne(
                "SELECT COUNT(*) AS cnt FROM sqlite_master
                 WHERE type = 'index' AND tbl_name = ? AND name = ?",
                [$table, $index]
            );
            return (int) ($result->cnt ?? 0) > 0;
        }
        // MySQL/MariaDB
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

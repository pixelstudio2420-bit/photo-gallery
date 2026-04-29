<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Create the `user_sessions` table used by:
 *   - App\Services\UserPresenceService  (recordActivity, online count, last seen)
 *   - App\Http\Middleware\TrackUserPresence
 *   - App\Http\Controllers\Admin\UserController  (show: recent sessions tab)
 *   - App\Console\Commands\CleanupPresenceData
 *
 * Schema tracks a SINGLE row per user (user_id is UNIQUE — UserPresenceService
 * does UPSERT on it). last_activity is indexed for online-count queries and
 * also co-indexed with is_online for the "get online users" filter.
 *
 * The code already guards with Schema::hasTable() so this migration is safe
 * to run on existing installations where presence features were previously
 * silently disabled.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('user_sessions')) {
            // Table already created manually — ensure indexes exist but don't recreate.
            $this->ensureIndexes();
            return;
        }

        Schema::create('user_sessions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('user_id')->unique(); // one row per user (upsert key)
            $table->string('ip_address', 45)->nullable(); // IPv6-compatible
            $table->string('user_agent', 500)->nullable();
            $table->string('device_type', 20)->nullable(); // desktop|tablet|mobile
            $table->string('browser', 30)->nullable();
            $table->string('os', 30)->nullable();
            $table->timestamp('last_activity')->useCurrent();
            $table->boolean('is_online')->default(true);
            $table->timestamp('created_at')->useCurrent();

            // Indexes — match what 2026_04_18_000000_add_scalability_indexes.php expects
            $table->index('last_activity',                 'user_sessions_last_activity_idx');
            $table->index(['is_online', 'last_activity'],  'user_sessions_online_activity_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_sessions');
    }

    /**
     * If table pre-exists (e.g. was created by hand), make sure the indexes
     * the scalability migration expects are in place.
     */
    private function ensureIndexes(): void
    {
        if (!$this->indexExists('user_sessions', 'user_sessions_last_activity_idx')) {
            try {
                DB::statement("CREATE INDEX user_sessions_last_activity_idx ON user_sessions (last_activity)");
            } catch (\Throwable $e) {
                // index exists under a different name — ignore
            }
        }
        if (!$this->indexExists('user_sessions', 'user_sessions_online_activity_idx')) {
            try {
                DB::statement("CREATE INDEX user_sessions_online_activity_idx ON user_sessions (is_online, last_activity)");
            } catch (\Throwable $e) {
                // ignore
            }
        }
    }

    /**
     * Check if an index exists on a table — driver-agnostic.
     * Works on MySQL, MariaDB, and PostgreSQL.
     */
    private function indexExists(string $table, string $index): bool
    {
        try {
            $driver = \DB::connection()->getDriverName();
            if ($driver === 'pgsql') {
                $result = \DB::select(
                    "SELECT 1 FROM pg_indexes WHERE tablename = ? AND indexname = ?",
                    [$table, $index]
                );
                return !empty($result);
            }
            // MySQL / MariaDB
            $result = \DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$index]);
            return !empty($result);
        } catch (\Throwable $e) {
            return false;
        }
    }
};

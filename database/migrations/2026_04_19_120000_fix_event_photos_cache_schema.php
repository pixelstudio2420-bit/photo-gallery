<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Align `event_photos_cache` schema with the code that uses it.
 *
 * Original table (2024_01_01_000010): file_id, file_name, mime_type,
 *   thumbnail_link, web_view_link, synced_at.
 *
 * Code that queries it (GoogleDriveService::syncToCacheDetailed,
 *   ImportDrivePhotosJob, EventPhotoCache model) expects:
 *   drive_file_id, filename, mime_type, file_size, width, height,
 *   thumbnail_link, synced_at.
 *
 * This migration is idempotent — it inspects the actual columns and only
 * applies the deltas needed, so it's safe to run on fresh installs,
 * partially-migrated DBs, or DBs that were manually patched.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('event_photos_cache')) {
            // Fresh install path — create the correct schema directly.
            Schema::create('event_photos_cache', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('event_id')->index();
                $table->string('drive_file_id', 200);
                $table->string('filename', 500)->nullable();
                $table->string('mime_type', 100)->nullable();
                $table->unsignedBigInteger('file_size')->default(0);
                $table->unsignedInteger('width')->default(0);
                $table->unsignedInteger('height')->default(0);
                $table->string('thumbnail_link', 500)->nullable();
                $table->timestamp('synced_at')->useCurrent();
                $table->unique(['event_id', 'drive_file_id']);
            });
            return;
        }

        // Existing install — patch deltas column by column.
        Schema::table('event_photos_cache', function (Blueprint $table) {
            // Rename file_id → drive_file_id
            if (Schema::hasColumn('event_photos_cache', 'file_id')
                && !Schema::hasColumn('event_photos_cache', 'drive_file_id')) {
                $table->renameColumn('file_id', 'drive_file_id');
            }
        });

        Schema::table('event_photos_cache', function (Blueprint $table) {
            // If neither column exists, add drive_file_id from scratch
            if (!Schema::hasColumn('event_photos_cache', 'drive_file_id')) {
                $table->string('drive_file_id', 200)->after('event_id');
            }

            // Rename file_name → filename
            if (Schema::hasColumn('event_photos_cache', 'file_name')
                && !Schema::hasColumn('event_photos_cache', 'filename')) {
                $table->renameColumn('file_name', 'filename');
            }
        });

        Schema::table('event_photos_cache', function (Blueprint $table) {
            if (!Schema::hasColumn('event_photos_cache', 'filename')) {
                $table->string('filename', 500)->nullable()->after('drive_file_id');
            }
            if (!Schema::hasColumn('event_photos_cache', 'file_size')) {
                $table->unsignedBigInteger('file_size')->default(0)->after('mime_type');
            }
            if (!Schema::hasColumn('event_photos_cache', 'width')) {
                $table->unsignedInteger('width')->default(0)->after('file_size');
            }
            if (!Schema::hasColumn('event_photos_cache', 'height')) {
                $table->unsignedInteger('height')->default(0)->after('width');
            }
        });

        // Drop legacy column web_view_link if present (unused by current code)
        if (Schema::hasColumn('event_photos_cache', 'web_view_link')) {
            Schema::table('event_photos_cache', function (Blueprint $table) {
                $table->dropColumn('web_view_link');
            });
        }

        // Rebuild unique index to use the new column name.
        // Wrapping in try/catch because the index name depends on the original
        // column names and may differ across DBs; we don't want this to block
        // the migration on edge cases.
        try {
            $indexes = $this->listIndexes('event_photos_cache');

            foreach ($indexes as $name) {
                if (str_contains(strtolower((string) $name), 'file_id')
                    && strtolower((string) $name) !== 'primary') {
                    try {
                        $driver = DB::connection()->getDriverName();
                        if ($driver === 'pgsql') {
                            DB::statement("DROP INDEX IF EXISTS \"{$name}\"");
                        } else {
                            DB::statement("ALTER TABLE event_photos_cache DROP INDEX `{$name}`");
                        }
                    } catch (\Throwable $e) {
                        // best-effort, ignore
                    }
                }
            }

            // Add the canonical unique index if it's missing.
            $hasCanonical = in_array(
                'event_photos_cache_event_id_drive_file_id_unique',
                $indexes,
                true
            );
            if (!$hasCanonical) {
                Schema::table('event_photos_cache', function (Blueprint $table) {
                    $table->unique(['event_id', 'drive_file_id']);
                });
            }
        } catch (\Throwable $e) {
            // If index rebuild fails, don't abort the migration — the rename
            // alone is enough to unblock syncToCacheDetailed(). Admins can
            // re-run this migration or recreate the index manually if needed.
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('event_photos_cache')) {
            return;
        }

        Schema::table('event_photos_cache', function (Blueprint $table) {
            foreach (['file_size', 'width', 'height'] as $col) {
                if (Schema::hasColumn('event_photos_cache', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('event_photos_cache', function (Blueprint $table) {
            if (Schema::hasColumn('event_photos_cache', 'drive_file_id')
                && !Schema::hasColumn('event_photos_cache', 'file_id')) {
                $table->renameColumn('drive_file_id', 'file_id');
            }
            if (Schema::hasColumn('event_photos_cache', 'filename')
                && !Schema::hasColumn('event_photos_cache', 'file_name')) {
                $table->renameColumn('filename', 'file_name');
            }
        });
    }

    /**
     * List all index names on a table — driver-agnostic.
     * Works on MySQL, MariaDB, and PostgreSQL.
     *
     * @return array<int,string>
     */
    private function listIndexes(string $table): array
    {
        try {
            $driver = DB::connection()->getDriverName();
            if ($driver === 'pgsql') {
                return collect(DB::select(
                    "SELECT indexname AS key_name FROM pg_indexes WHERE tablename = ?",
                    [$table]
                ))->pluck('key_name')->unique()->values()->toArray();
            }
            // MySQL / MariaDB
            return collect(DB::select("SHOW INDEX FROM `{$table}`"))
                ->pluck('Key_name')
                ->unique()
                ->values()
                ->toArray();
        } catch (\Throwable $e) {
            return [];
        }
    }
};

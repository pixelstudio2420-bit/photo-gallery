<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Image-moderation columns on event_photos.
 *
 *   moderation_status    — lifecycle state of the automated + human review:
 *                          pending  = queued, not yet scanned
 *                          approved = scan passed, safe to publish
 *                          flagged  = scan returned labels between flag/reject thresholds
 *                                     → needs a human admin to decide
 *                          rejected = auto-rejected (label confidence ≥ hard threshold)
 *                                     OR admin-rejected after flag
 *                          skipped  = moderation intentionally bypassed
 *                                     (e.g. verified photographer, disabled setting)
 *
 *   moderation_score     — max confidence (0-100) across all labels Rekognition
 *                          returned. Drives threshold decisions and admin sorting.
 *
 *   moderation_labels    — full JSON array of {Name, ParentName, Confidence} rows
 *                          so we can show exact reasons in the admin UI, audit
 *                          later, and re-evaluate without re-scanning.
 *
 *   moderation_reviewed_* — who approved/rejected after a flag, and when.
 *                           Left null for auto decisions so UI can distinguish
 *                           "AI decided" vs "admin decided".
 *
 *   moderation_reject_reason — free-text from the admin (optional). Photographer-
 *                              facing explanation when a photo is rejected.
 *
 * Defaults matter here: existing rows get `approved` (not `pending`), because
 * they've already been live and rolling them back to 'pending' would hide every
 * uploaded photo until the backfill finishes. The admin runs
 * `photos:remoderate --all` when they want to scan historical photos.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('event_photos')) {
            return;
        }

        Schema::table('event_photos', function (Blueprint $table) {
            if (!Schema::hasColumn('event_photos', 'moderation_status')) {
                $table->enum('moderation_status', ['pending', 'approved', 'flagged', 'rejected', 'skipped'])
                      ->default('approved')
                      ->after('status')
                      ->index();
            }

            if (!Schema::hasColumn('event_photos', 'moderation_score')) {
                $table->decimal('moderation_score', 5, 2)
                      ->nullable()
                      ->after('moderation_status');
            }

            if (!Schema::hasColumn('event_photos', 'moderation_labels')) {
                $table->json('moderation_labels')
                      ->nullable()
                      ->after('moderation_score');
            }

            if (!Schema::hasColumn('event_photos', 'moderation_reviewed_by')) {
                $table->unsignedBigInteger('moderation_reviewed_by')
                      ->nullable()
                      ->after('moderation_labels');
            }

            if (!Schema::hasColumn('event_photos', 'moderation_reviewed_at')) {
                $table->timestamp('moderation_reviewed_at')
                      ->nullable()
                      ->after('moderation_reviewed_by');
            }

            if (!Schema::hasColumn('event_photos', 'moderation_reject_reason')) {
                $table->text('moderation_reject_reason')
                      ->nullable()
                      ->after('moderation_reviewed_at');
            }
        });

        // Composite index for the admin dashboard's two hottest queries:
        //   (a) "list flagged photos sorted by highest risk score"
        //   (b) "list pending photos oldest first"
        if (!$this->indexExists('event_photos', 'event_photos_moderation_lookup_idx')) {
            Schema::table('event_photos', function (Blueprint $table) {
                $table->index(
                    ['moderation_status', 'moderation_score'],
                    'event_photos_moderation_lookup_idx'
                );
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('event_photos')) {
            return;
        }

        Schema::table('event_photos', function (Blueprint $table) {
            if ($this->indexExists('event_photos', 'event_photos_moderation_lookup_idx')) {
                $table->dropIndex('event_photos_moderation_lookup_idx');
            }

            foreach (
                [
                    'moderation_reject_reason',
                    'moderation_reviewed_at',
                    'moderation_reviewed_by',
                    'moderation_labels',
                    'moderation_score',
                    'moderation_status',
                ] as $col
            ) {
                if (Schema::hasColumn('event_photos', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
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

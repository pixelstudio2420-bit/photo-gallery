<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * High-concurrency upload hardening — adds the columns + tables that the
 * resumable / idempotent / batched upload pipeline needs.
 *
 * What this adds
 * ==============
 * 1) event_photos.content_hash       — SHA-256 of the original bytes.
 *    Used to detect cross-session duplicates BEFORE writing a row, so a
 *    flaky-network retry uploading the same file twice doesn't double-bill
 *    the photographer's storage quota.
 *
 * 2) event_photos.idempotency_key    — client-supplied UUID.
 *    Lets the client retry POST /events/{id}/photos safely; second hit
 *    returns the original row instead of creating a sibling.
 *
 * 3) upload_sessions                 — batch upload progress tracking.
 *    The browser uploader handles 100s of files per batch; this table is
 *    the source of truth for "X/Y done" so a page refresh / disconnect
 *    can resume rather than restart.
 *
 * 4) upload_chunks                   — multipart (resumable) upload state.
 *    Mirrors the S3/R2 multipart upload protocol: init → sign-part(s) →
 *    complete | abort. Persisting upload_id + part numbers means a
 *    network failure mid-batch is recoverable instead of catastrophic.
 *
 * 5) Idempotency uniqueness          — partial unique indexes so retries
 *    serialise on the database instead of racing.
 *
 * Driver portability
 * ------------------
 * Postgres is the production DB. Tests run on sqlite. Both support the
 * partial-index syntax we need (`WHERE column IS NOT NULL`). MySQL also
 * supports the columns; the unique constraints there omit the partial
 * predicate (full unique on the pair of cols) which is slightly stricter
 * but still correct.
 */
return new class extends Migration {
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        // ── (1) + (2) Add columns to event_photos ────────────────────────
        Schema::table('event_photos', function (Blueprint $t) {
            if (!Schema::hasColumn('event_photos', 'content_hash')) {
                // SHA-256 hex digest = 64 chars; nullable while the file is
                // still in 'processing' so the hash can be filled by the
                // worker that computes derivatives. Backfill is safe to run
                // at any time.
                $t->string('content_hash', 64)->nullable()->index();
            }
            if (!Schema::hasColumn('event_photos', 'idempotency_key')) {
                $t->string('idempotency_key', 64)->nullable();
            }
        });

        // Partial unique indexes — Postgres + sqlite native syntax. Skipped
        // on MySQL (it doesn't accept WHERE clauses on indexes); see down().
        if ($driver === 'pgsql' || $driver === 'sqlite') {
            // (event_id, idempotency_key) must be unique only when the key
            // is set. NULL idempotency keys mean "client didn't send one"
            // and shouldn't conflict with each other.
            $exists = DB::selectOne(
                $driver === 'pgsql'
                    ? "SELECT 1 FROM pg_indexes WHERE indexname = 'uniq_event_photos_idempotency'"
                    : "SELECT 1 FROM sqlite_master WHERE type='index' AND name='uniq_event_photos_idempotency'"
            );
            if (!$exists) {
                DB::statement('CREATE UNIQUE INDEX uniq_event_photos_idempotency
                    ON event_photos(event_id, idempotency_key)
                    WHERE idempotency_key IS NOT NULL');
            }

            // (event_id, content_hash) is unique only for non-deleted photos.
            // Deleted rows can still exist (history), and we don't want to
            // block a re-upload after delete.
            $exists = DB::selectOne(
                $driver === 'pgsql'
                    ? "SELECT 1 FROM pg_indexes WHERE indexname = 'uniq_event_photos_content_hash_active'"
                    : "SELECT 1 FROM sqlite_master WHERE type='index' AND name='uniq_event_photos_content_hash_active'"
            );
            if (!$exists) {
                DB::statement("CREATE UNIQUE INDEX uniq_event_photos_content_hash_active
                    ON event_photos(event_id, content_hash)
                    WHERE content_hash IS NOT NULL AND status != 'deleted'");
            }
        } elseif ($driver === 'mysql' || $driver === 'mariadb') {
            // No partial-index support pre-MySQL 8.0.13. We accept a
            // slightly stricter constraint: idempotency_key must be unique
            // per event (NULLs are still allowed to repeat under MySQL).
            try {
                DB::statement('CREATE UNIQUE INDEX uniq_event_photos_idempotency
                    ON event_photos(event_id, idempotency_key)');
            } catch (\Throwable) { /* already exists */ }
            try {
                DB::statement('CREATE UNIQUE INDEX uniq_event_photos_content_hash
                    ON event_photos(event_id, content_hash)');
            } catch (\Throwable) { /* already exists */ }
        }

        // ── (3) upload_sessions ─────────────────────────────────────────
        if (!Schema::hasTable('upload_sessions')) {
            Schema::create('upload_sessions', function (Blueprint $t) {
                $t->id();
                $t->uuid('session_token')->unique();      // returned to the client
                $t->unsignedInteger('user_id');           // owner
                $t->unsignedInteger('event_id')->nullable();
                $t->string('category', 64);               // e.g. events.photos
                // Lifecycle: open → finalising → completed | aborted | expired
                $t->string('status', 16)->default('open');
                $t->unsignedInteger('expected_files')->default(0);
                $t->unsignedInteger('completed_files')->default(0);
                $t->unsignedInteger('failed_files')->default(0);
                $t->unsignedBigInteger('total_bytes')->default(0);
                $t->json('meta')->nullable();             // free-form for the UI
                $t->timestamp('expires_at')->nullable();
                $t->timestamps();

                $t->index(['user_id', 'status']);
                $t->index(['event_id', 'status']);
                $t->index('expires_at');                  // for the cleanup sweep
            });
        }

        // ── (4) upload_chunks (multipart upload state) ─────────────────
        if (!Schema::hasTable('upload_chunks')) {
            Schema::create('upload_chunks', function (Blueprint $t) {
                $t->id();
                // Either tied to an upload_session (batch flow) or standalone
                // (single-large-file flow). The session FK is nullable so
                // both flows share this table.
                $t->unsignedInteger('upload_session_id')->nullable();
                $t->unsignedInteger('user_id');
                $t->unsignedInteger('event_id')->nullable();
                $t->string('category', 64);
                $t->string('object_key', 1000);           // R2 key
                $t->string('upload_id', 256);             // S3-issued multipart upload ID
                $t->string('original_filename', 500);
                $t->string('mime_type', 128);
                $t->unsignedBigInteger('total_bytes')->default(0);
                $t->unsignedInteger('total_parts')->default(0);
                $t->unsignedInteger('completed_parts')->default(0);
                // Lifecycle: initiated → uploading → completed | aborted | expired
                $t->string('status', 16)->default('initiated');
                $t->string('content_hash', 64)->nullable();   // SHA-256, set on complete
                $t->json('parts')->nullable();                // [{partNumber, etag, sizeBytes}]
                $t->timestamp('expires_at')->nullable();
                $t->timestamps();

                $t->index(['user_id', 'status']);
                $t->index('upload_session_id');
                $t->index('expires_at');
                $t->unique(['user_id', 'upload_id']);      // dedupe per-user
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('upload_chunks');
        Schema::dropIfExists('upload_sessions');

        $driver = DB::connection()->getDriverName();
        try {
            if ($driver === 'pgsql' || $driver === 'sqlite') {
                DB::statement('DROP INDEX IF EXISTS uniq_event_photos_idempotency');
                DB::statement('DROP INDEX IF EXISTS uniq_event_photos_content_hash_active');
            } else {
                DB::statement('DROP INDEX uniq_event_photos_idempotency ON event_photos');
                DB::statement('DROP INDEX uniq_event_photos_content_hash ON event_photos');
            }
        } catch (\Throwable) { /* indexes may already be gone */ }

        Schema::table('event_photos', function (Blueprint $t) {
            if (Schema::hasColumn('event_photos', 'idempotency_key')) {
                $t->dropColumn('idempotency_key');
            }
            if (Schema::hasColumn('event_photos', 'content_hash')) {
                $t->dropColumn('content_hash');
            }
        });
    }
};

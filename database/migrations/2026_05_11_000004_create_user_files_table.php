<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-user uploaded files — the leaf content of the consumer file manager.
 *
 * A file lives in either a folder (`folder_id` set) or the root
 * (`folder_id = NULL`). `storage_path` is the object key on the configured
 * disk (default: R2) — pattern: `user-files/{user_id}/{yyyy}/{mm}/{uuid}_{name}`.
 *
 * Soft-deleted files remain in storage until the trash is purged (a nightly
 * cron or explicit user action reclaims the GB).
 *
 * `share_token` (when set) allows unauthenticated downloads at a public URL;
 * `share_expires_at` and `share_password_hash` gate access.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('user_files')) {
            return;
        }

        Schema::create('user_files', function (Blueprint $t) {
            $t->id();

            $t->unsignedBigInteger('user_id')->index();
            $t->unsignedBigInteger('folder_id')->nullable()->index();

            // Filename as stored on disk (sanitised) + the user's original name
            $t->string('filename', 255);
            $t->string('original_name', 255);
            $t->string('extension', 16)->nullable()->index();
            $t->string('mime_type', 128)->nullable()->index();
            $t->unsignedBigInteger('size_bytes');

            // Storage pointer
            $t->string('storage_path', 1024);
            $t->string('storage_disk', 32)->default('r2');

            // Integrity / dedupe
            $t->string('checksum_sha256', 64)->nullable()->index();

            // Sharing
            $t->boolean('is_public')->default(false);
            $t->string('share_token', 64)->nullable()->unique();
            $t->timestamp('share_expires_at')->nullable();
            $t->string('share_password_hash', 255)->nullable();

            // Usage stats
            $t->unsignedInteger('downloads')->default(0);
            $t->timestamp('last_accessed_at')->nullable();

            // Preview assets (generated later)
            $t->string('thumbnail_path', 1024)->nullable();
            $t->boolean('preview_generated')->default(false);

            $t->json('meta')->nullable();

            $t->timestamps();
            $t->softDeletes();

            $t->index(['user_id', 'folder_id']);
            $t->index(['user_id', 'deleted_at']);
            $t->index(['user_id', 'created_at']);

            $t->foreign('folder_id')
                ->references('id')->on('user_folders')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_files');
    }
};

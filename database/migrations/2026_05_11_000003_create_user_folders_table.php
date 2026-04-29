<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * User folder tree for the consumer file manager.
 *
 * Each user has their own folder hierarchy, rooted at `parent_id = NULL`
 * (root). `path` is a denormalised slash-separated breadcrumb that makes
 * breadcrumb rendering and search cheap — kept in sync by FileManagerService
 * on create/rename/move.
 *
 * `files_count` and `size_bytes` are aggregate caches so the UI can show
 * folder sizes without walking the tree on every render. They're updated
 * on file create/delete/move.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('user_folders')) {
            return;
        }

        Schema::create('user_folders', function (Blueprint $t) {
            $t->id();

            $t->unsignedBigInteger('user_id')->index();
            $t->unsignedBigInteger('parent_id')->nullable()->index();

            $t->string('name', 255);
            // Denormalised breadcrumb path — e.g. "/Photos/2025/Trip".
            // Kept in sync by FileManagerService; don't write directly.
            $t->string('path', 1024)->nullable()->index();

            // Aggregate caches
            $t->unsignedInteger('files_count')->default(0);
            $t->unsignedBigInteger('size_bytes')->default(0);

            $t->timestamps();
            $t->softDeletes();

            $t->index(['user_id', 'parent_id']);
            $t->index(['user_id', 'deleted_at']);

            $t->foreign('parent_id')
                ->references('id')->on('user_folders')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_folders');
    }
};

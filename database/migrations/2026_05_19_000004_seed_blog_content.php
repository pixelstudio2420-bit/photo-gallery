<?php

use Database\Seeders\BlogContentSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Auto-seed 10 high-quality Thai SEO articles on first deploy.
 *
 * Articles seeded as `status='draft'` so admin reviews + tweaks
 * before flipping to published. Idempotent: BlogContentSeeder
 * skips articles whose slug already exists in DB.
 *
 * Categories created (4): photographer-tips, customer-help,
 * event-photography, marketplace-guide.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('blog_posts') || !Schema::hasTable('blog_categories')) {
            Log::warning('Blog tables missing — skipping content seed migration');
            return;
        }

        try {
            (new BlogContentSeeder())->run();
        } catch (\Throwable $e) {
            // Don't block the migration — admin can re-seed manually.
            // Public /blog still renders fine when posts table is empty.
            Log::error('BlogContentSeeder failed during migration: ' . $e->getMessage());
        }
    }

    public function down(): void
    {
        // No-op — leaving the seeded articles is harmless on rollback,
        // and we don't want to destroy any admin customisation.
    }
};

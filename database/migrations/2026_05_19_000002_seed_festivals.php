<?php

use Database\Seeders\FestivalsSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Auto-seed the festivals table on first deploy. Wraps
 * FestivalsSeeder::run() so production gets the major Thai + global
 * festivals without admin needing to remember to artisan db:seed.
 *
 * Idempotent: the seeder upserts by slug, so re-running just bumps
 * dates on the already-seeded festivals.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('festivals')) {
            Log::warning('Festivals seed migration: table missing — skipping');
            return;
        }

        try {
            (new FestivalsSeeder())->run();
        } catch (\Throwable $e) {
            // Don't block the migration — admin can re-seed manually.
            // The popup partial gracefully renders nothing when no
            // festivals exist, so a failed seed isn't user-visible.
            Log::error('FestivalsSeeder failed during migration: ' . $e->getMessage());
        }
    }

    public function down(): void
    {
        // No-op — leaving the seed data is harmless on rollback,
        // and we don't want to destroy any admin customisation.
    }
};

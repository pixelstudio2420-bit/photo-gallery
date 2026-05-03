<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Auto-run DigitalProductsSeeder on deploy.
 *
 * Why a migration instead of `php artisan db:seed`?
 * ─────────────────────────────────────────────────
 * Laravel Cloud (and most Laravel hosts) run migrations automatically
 * on every deploy but do NOT auto-run seeders — those are explicit
 * commands. After commit f674177 shipped DigitalProductsSeeder, local
 * had 6 products but production /products still rendered "ไม่พบสินค้า"
 * because the seeder never executed there.
 *
 * Wrapping the seeder in a migration solves that without requiring
 * admin to manually run an Artisan command on the production console
 * — the products land alongside any other schema change.
 *
 * Idempotency:
 *   The seeder itself looks up existing rows by slug and updates them
 *   in place rather than duplicating, so running this migration on a
 *   DB that already has the products is a safe no-op (just refreshes
 *   description/features text from the seeder file).
 *
 * Re-running the seeder with `php artisan db:seed --class=...` after
 * this migration runs is also safe for the same reason.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('digital_products')) {
            // Table doesn't exist yet — earlier digital_products migration
            // must have been skipped. Bail out to avoid a hard failure;
            // a future migration can backfill once the table exists.
            return;
        }

        // The seeder's $this->command is null when invoked outside the
        // db:seed Artisan command, but the seeder uses `?->info()` so
        // null is handled gracefully — no admin-console output, just
        // silent execution as part of the migration log.
        (new \Database\Seeders\DigitalProductsSeeder())->run();
    }

    public function down(): void
    {
        // Soft rollback: deactivate seeded products by their predictable
        // slug rather than hard-delete. If admin had edited any of these
        // (changed price, added cover image, etc.) those edits are
        // preserved — only the active flag flips off so they disappear
        // from the public listing.
        if (!Schema::hasTable('digital_products')) return;

        $slugs = [
            'free-lightroom-presets-starter',
            'wedding-watermark-pack-pro-47',
            'wedding-contract-bundle-12docs',
            'photographer-pricing-calculator',
            'wedding-master-checklist-153',
            'photography-business-starter-kit',
        ];
        \DB::table('digital_products')
            ->whereIn('slug', $slugs)
            ->update(['status' => 'inactive']);
    }
};

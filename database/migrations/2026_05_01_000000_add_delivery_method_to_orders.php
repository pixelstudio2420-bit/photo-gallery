<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add photo delivery preferences to orders.
 *
 * Columns:
 *  - delivery_method ENUM : how the buyer wants their photos delivered.
 *       'web'   — download on the site (classic download tokens)
 *       'line'  — pushed via LINE with a link (requires LINE OAuth linked)
 *       'email' — emailed with a download link (best for bulk orders)
 *       'auto'  — site picks the best method based on photo count + user setup
 *  - delivery_status ENUM : delivery state after payment approval.
 *       pending → queued, sent → dispatched (email/LINE), delivered → confirmed,
 *       failed → dispatch error, partial → some photos delivered some not.
 *  - delivered_at  : timestamp of last successful dispatch (informational).
 *  - delivery_meta : JSON blob with per-method details (link used, photo count,
 *                     error message on failure, etc.). Free-form to avoid
 *                     schema churn every time we add a delivery channel.
 *
 * Also seeds the delivery-related app_settings keys so the admin UI has
 * defaults on first load.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('orders')) return;

        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'delivery_method')) {
                $table->enum('delivery_method', ['web', 'line', 'email', 'auto'])
                      ->default('auto')
                      ->after('status');
            }
            if (!Schema::hasColumn('orders', 'delivery_status')) {
                $table->enum('delivery_status', ['pending', 'sent', 'delivered', 'failed', 'partial'])
                      ->nullable()
                      ->after('delivery_method');
            }
            if (!Schema::hasColumn('orders', 'delivered_at')) {
                $table->timestamp('delivered_at')->nullable()->after('delivery_status');
            }
            if (!Schema::hasColumn('orders', 'delivery_meta')) {
                $table->json('delivery_meta')->nullable()->after('delivered_at');
            }
        });

        // Seed defaults — idempotent, only inserts if the key doesn't exist.
        $defaults = [
            'delivery_methods_enabled'  => '["web","line","email"]',  // JSON array of allowed methods
            'delivery_default_method'   => 'auto',                     // default for new orders
            'delivery_auto_switch'      => '1',                         // enable auto-switch rules
            'delivery_line_max_photos'  => '9',                         // LINE images per push (max 10 by API)
            'delivery_email_threshold'  => '30',                        // switch to email above N photos
            'delivery_web_always'       => '1',                         // also generate web tokens as fallback
        ];

        foreach ($defaults as $key => $value) {
            try {
                DB::table('app_settings')->updateOrInsert(
                    ['key' => $key],
                    ['value' => $value, 'updated_at' => now()]
                );
            } catch (\Throwable $e) {
                // app_settings may not exist on a bare install — skip silently
            }
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('orders')) return;

        Schema::table('orders', function (Blueprint $table) {
            foreach (['delivery_meta', 'delivered_at', 'delivery_status', 'delivery_method'] as $col) {
                if (Schema::hasColumn('orders', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        try {
            DB::table('app_settings')->whereIn('key', [
                'delivery_methods_enabled',
                'delivery_default_method',
                'delivery_auto_switch',
                'delivery_line_max_photos',
                'delivery_email_threshold',
                'delivery_web_always',
            ])->delete();
        } catch (\Throwable $e) {}
    }
};

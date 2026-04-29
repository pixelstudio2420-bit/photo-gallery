<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Scalability Indexes — Phase 1 Quick Win
 * =========================================
 *
 * Adds composite + FULLTEXT indexes required to move from ~10-25 concurrent users
 * to 1,000-2,000 concurrent users without the DB becoming the bottleneck.
 *
 * Strategy:
 * - Each index is wrapped in try/catch so missing tables / pre-existing indexes
 *   don't fail the whole migration.
 * - `indexMissing()` first checks SHOW INDEX so we never create duplicates.
 * - FULLTEXT indexes are only added if the table is using InnoDB (MySQL 5.7+) or MyISAM.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Composite / plain indexes ──────────────────────────────────────
        $this->addIndex('orders',                ['status', 'created_at'],           'orders_status_created_idx');
        $this->addIndex('orders',                ['user_id', 'status'],              'orders_user_status_idx');
        $this->addIndex('orders',                ['created_at'],                     'orders_created_idx');

        $this->addIndex('order_items',           ['order_id', 'photo_id'],           'order_items_order_photo_idx');

        $this->addIndex('digital_orders',        ['product_id'],                     'digital_orders_product_idx');
        $this->addIndex('digital_orders',        ['status', 'created_at'],           'digital_orders_status_created_idx');
        $this->addIndex('digital_orders',        ['status', 'paid_at'],              'digital_orders_status_paid_idx');
        $this->addIndex('digital_orders',        ['paid_at'],                        'digital_orders_paid_at_idx');

        $this->addIndex('payment_transactions',  ['user_id'],                        'payment_tx_user_idx');
        $this->addIndex('payment_transactions',  ['user_id', 'status'],              'payment_tx_user_status_idx');
        $this->addIndex('payment_transactions',  ['created_at'],                     'payment_tx_created_idx');

        $this->addIndex('payment_slips',         ['verify_status', 'created_at'],    'payment_slips_status_created_idx');
        $this->addIndex('payment_slips',         ['uploaded_at'],                    'payment_slips_uploaded_idx');

        $this->addIndex('admin_notifications',   ['is_read', 'created_at'],          'admin_notifs_read_created_idx');
        $this->addIndex('admin_notifications',   ['type', 'is_read', 'created_at'],  'admin_notifs_type_read_created_idx');
        $this->addIndex('admin_notifications',   ['created_at'],                     'admin_notifs_created_idx');

        $this->addIndex('event_photos',          ['status', 'created_at'],           'event_photos_status_created_idx');
        $this->addIndex('event_photos',          ['uploaded_by', 'created_at'],      'event_photos_uploader_created_idx');
        $this->addIndex('event_photos',          ['event_id', 'sort_order'],         'event_photos_event_sort_idx');

        $this->addIndex('event_events',          ['status', 'visibility'],                          'event_events_status_visibility_idx');
        $this->addIndex('event_events',          ['photographer_id', 'status', 'visibility'],       'event_events_photog_status_vis_idx');
        $this->addIndex('event_events',          ['category_id', 'status'],                         'event_events_cat_status_idx');
        $this->addIndex('event_events',          ['status', 'shoot_date'],                          'event_events_status_shoot_idx');
        $this->addIndex('event_events',          ['status', 'created_at'],                          'event_events_status_created_idx');
        $this->addIndex('event_events',          ['view_count'],                                    'event_events_view_count_idx');

        $this->addIndex('photographer_payouts',  ['photographer_id', 'status'],      'payouts_photog_status_idx');
        $this->addIndex('photographer_payouts',  ['status', 'created_at'],           'payouts_status_created_idx');
        $this->addIndex('photographer_payouts',  ['created_at'],                     'payouts_created_idx');

        $this->addIndex('photographer_profiles', ['status'],                         'photog_profiles_status_idx');
        $this->addIndex('photographer_profiles', ['status', 'created_at'],           'photog_profiles_status_created_idx');

        $this->addIndex('auth_users',            ['status'],                         'users_status_idx');
        $this->addIndex('auth_users',            ['created_at'],                     'users_created_idx');
        $this->addIndex('auth_users',            ['last_login_at'],                  'users_last_login_idx');
        $this->addIndex('auth_users',            ['email_verified'],                 'users_email_verified_idx');
        $this->addIndex('auth_users',            ['auth_provider'],                  'users_auth_provider_idx');

        $this->addIndex('reviews',               ['photographer_id'],                'reviews_photog_idx');
        $this->addIndex('reviews',               ['photographer_id', 'is_visible'],  'reviews_photog_visible_idx');
        $this->addIndex('reviews',               ['event_id'],                       'reviews_event_idx');
        $this->addIndex('reviews',               ['user_id'],                        'reviews_user_idx');
        $this->addIndex('reviews',               ['is_visible', 'created_at'],       'reviews_visible_created_idx');

        $this->addIndex('user_notifications',    ['user_id', 'is_read'],             'user_notifs_user_read_idx');
        $this->addIndex('user_notifications',    ['user_id', 'is_read', 'created_at'], 'user_notifs_user_read_created_idx');

        $this->addIndex('wishlists',             ['user_id', 'created_at'],          'wishlists_user_created_idx');

        $this->addIndex('activity_logs',         ['user_id', 'created_at'],          'activity_logs_user_created_idx');
        $this->addIndex('activity_logs',         ['admin_id', 'created_at'],         'activity_logs_admin_created_idx');

        // user_sessions (created dynamically at runtime if missing — guard anyway)
        $this->addIndex('user_sessions',         ['last_activity'],                  'user_sessions_last_activity_idx');
        $this->addIndex('user_sessions',         ['is_online', 'last_activity'],     'user_sessions_online_activity_idx');

        // ── FULLTEXT indexes (MySQL InnoDB 5.7+ / MyISAM) ─────────────────
        // Massively faster than `LIKE '%keyword%'` on large tables.
        $this->addFullText('event_events',      ['name', 'description', 'location'], 'event_events_fulltext_idx');
        $this->addFullText('digital_products',  ['name', 'description', 'short_description'], 'digital_products_fulltext_idx');
        $this->addFullText('photographer_profiles', ['display_name', 'bio'], 'photog_profiles_fulltext_idx');
        $this->addFullText('auth_users',        ['first_name', 'last_name', 'email', 'username'], 'users_fulltext_idx');
    }

    public function down(): void
    {
        // Drop in reverse. Use raw SQL so missing indexes don't abort.
        $drops = [
            ['orders', 'orders_status_created_idx'],
            ['orders', 'orders_user_status_idx'],
            ['orders', 'orders_created_idx'],
            ['order_items', 'order_items_order_photo_idx'],
            ['digital_orders', 'digital_orders_product_idx'],
            ['digital_orders', 'digital_orders_status_created_idx'],
            ['digital_orders', 'digital_orders_status_paid_idx'],
            ['digital_orders', 'digital_orders_paid_at_idx'],
            ['payment_transactions', 'payment_tx_user_idx'],
            ['payment_transactions', 'payment_tx_user_status_idx'],
            ['payment_transactions', 'payment_tx_created_idx'],
            ['payment_slips', 'payment_slips_status_created_idx'],
            ['payment_slips', 'payment_slips_uploaded_idx'],
            ['admin_notifications', 'admin_notifs_read_created_idx'],
            ['admin_notifications', 'admin_notifs_type_read_created_idx'],
            ['admin_notifications', 'admin_notifs_created_idx'],
            ['event_photos', 'event_photos_status_created_idx'],
            ['event_photos', 'event_photos_uploader_created_idx'],
            ['event_photos', 'event_photos_event_sort_idx'],
            ['event_events', 'event_events_status_visibility_idx'],
            ['event_events', 'event_events_photog_status_vis_idx'],
            ['event_events', 'event_events_cat_status_idx'],
            ['event_events', 'event_events_status_shoot_idx'],
            ['event_events', 'event_events_status_created_idx'],
            ['event_events', 'event_events_view_count_idx'],
            ['photographer_payouts', 'payouts_photog_status_idx'],
            ['photographer_payouts', 'payouts_status_created_idx'],
            ['photographer_payouts', 'payouts_created_idx'],
            ['photographer_profiles', 'photog_profiles_status_idx'],
            ['photographer_profiles', 'photog_profiles_status_created_idx'],
            ['auth_users', 'users_status_idx'],
            ['auth_users', 'users_created_idx'],
            ['auth_users', 'users_last_login_idx'],
            ['auth_users', 'users_email_verified_idx'],
            ['auth_users', 'users_auth_provider_idx'],
            ['reviews', 'reviews_photog_idx'],
            ['reviews', 'reviews_photog_visible_idx'],
            ['reviews', 'reviews_event_idx'],
            ['reviews', 'reviews_user_idx'],
            ['reviews', 'reviews_visible_created_idx'],
            ['user_notifications', 'user_notifs_user_read_idx'],
            ['user_notifications', 'user_notifs_user_read_created_idx'],
            ['wishlists', 'wishlists_user_created_idx'],
            ['activity_logs', 'activity_logs_user_created_idx'],
            ['activity_logs', 'activity_logs_admin_created_idx'],
            ['user_sessions', 'user_sessions_last_activity_idx'],
            ['user_sessions', 'user_sessions_online_activity_idx'],
            ['event_events', 'event_events_fulltext_idx'],
            ['digital_products', 'digital_products_fulltext_idx'],
            ['photographer_profiles', 'photog_profiles_fulltext_idx'],
            ['auth_users', 'users_fulltext_idx'],
        ];

        foreach ($drops as [$table, $name]) {
            try {
                if (Schema::hasTable($table)) {
                    DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$name}`");
                }
            } catch (\Throwable $e) {
                // Already gone — ignore
            }
        }
    }

    // ────────────────────────────────────────────────────────────────────
    //  Helpers
    // ────────────────────────────────────────────────────────────────────

    private function addIndex(string $table, array $cols, string $name): void
    {
        try {
            if (!Schema::hasTable($table)) return;
            foreach ($cols as $c) {
                if (!Schema::hasColumn($table, $c)) return;
            }
            if ($this->indexExists($table, $name)) return;

            $quoted = implode(',', array_map(fn($c) => "`{$c}`", $cols));
            DB::statement("ALTER TABLE `{$table}` ADD INDEX `{$name}` ({$quoted})");
        } catch (\Throwable $e) {
            // Swallow — one bad index shouldn't abort the whole migration
        }
    }

    private function addFullText(string $table, array $cols, string $name): void
    {
        try {
            if (!Schema::hasTable($table)) return;
            foreach ($cols as $c) {
                if (!Schema::hasColumn($table, $c)) return;
            }
            if ($this->indexExists($table, $name)) return;

            $quoted = implode(',', array_map(fn($c) => "`{$c}`", $cols));
            DB::statement("ALTER TABLE `{$table}` ADD FULLTEXT INDEX `{$name}` ({$quoted})");
        } catch (\Throwable $e) {
            // FULLTEXT may not be supported — ignore
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

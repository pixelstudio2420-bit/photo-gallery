<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Event Retention Policy — auto-delete old events to reclaim storage.
 *
 * Two knobs per event:
 *   retention_days_override   — per-event TTL (beats global default)
 *   auto_delete_at            — explicit delete date (beats everything)
 *   auto_delete_exempt        — "pin" this event so it never auto-deletes
 *   auto_delete_warned_at     — bookkeeping: when we sent the heads-up email
 *
 * Global defaults live in app_settings (see seeder below).
 *
 * The actual deletion runs via:
 *   php artisan events:purge-expired   (scheduled daily at 02:30)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_events', function (Blueprint $table) {
            $table->unsignedSmallInteger('retention_days_override')
                ->nullable()
                ->after('view_count')
                ->comment('Per-event TTL in days; NULL = use global setting');

            $table->timestamp('auto_delete_at')
                ->nullable()
                ->after('retention_days_override')
                ->comment('Explicit delete timestamp; takes precedence over retention_days_override');

            $table->boolean('auto_delete_exempt')
                ->default(false)
                ->after('auto_delete_at')
                ->comment('If true, this event is NEVER auto-deleted (admin pin)');

            $table->timestamp('auto_delete_warned_at')
                ->nullable()
                ->after('auto_delete_exempt')
                ->comment('When we emailed the photographer a heads-up');

            // Partial index: fast "what's due for deletion?" scan
            $table->index(['auto_delete_exempt', 'auto_delete_at'], 'idx_event_auto_delete');
        });

        // Seed defaults into app_settings (idempotent).
        $defaults = [
            'event_auto_delete_enabled'          => '0',   // OFF by default — admin must opt in
            'event_default_retention_days'       => '90',  // 3 months
            'event_auto_delete_warn_days'        => '7',   // email photographer 7 days before
            'event_auto_delete_skip_if_orders'   => '1',   // NEVER delete events with paid orders
            'event_auto_delete_from_field'       => 'shoot_date', // shoot_date | created_at
            'event_auto_delete_purge_drive'      => '0',   // also delete Google Drive folder?
            'event_auto_delete_batch_limit'      => '50',  // safety: max deletions per run
        ];

        foreach ($defaults as $key => $value) {
            DB::table('app_settings')->updateOrInsert(
                ['key' => $key],
                ['value' => $value, 'updated_at' => now()]
            );
        }
    }

    public function down(): void
    {
        Schema::table('event_events', function (Blueprint $table) {
            $table->dropIndex('idx_event_auto_delete');
            $table->dropColumn([
                'retention_days_override',
                'auto_delete_at',
                'auto_delete_exempt',
                'auto_delete_warned_at',
            ]);
        });

        DB::table('app_settings')->whereIn('key', [
            'event_auto_delete_enabled',
            'event_default_retention_days',
            'event_auto_delete_warn_days',
            'event_auto_delete_skip_if_orders',
            'event_auto_delete_from_field',
            'event_auto_delete_purge_drive',
            'event_auto_delete_batch_limit',
        ])->delete();
    }
};

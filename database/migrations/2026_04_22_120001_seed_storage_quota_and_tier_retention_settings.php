<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seed default settings for:
 *   • Storage quota per tier (Part A)
 *   • Retention period per tier (Part B — extends existing retention system)
 *   • Upgrade nudge thresholds + savings calculator
 *
 * Tier defaults are chosen to be "generous enough to feel unlimited for new
 * users" but limited enough that heavy users hit the ceiling and see
 * real value in upgrading. Numbers derived from R2 pricing ($0.015/GB/mo)
 * and typical event-photographer usage patterns (see business doc).
 *
 * Cost model under the defaults (100 photographers × tier mix):
 *   • 80× creator at 5 GB avg 3 GB used = 240 GB × $0.015 = $3.60
 *   • 15× seller  at 50 GB avg 20 GB     = 300 GB × $0.015 = $4.50
 *   • 5 × pro     at 500 GB avg 100 GB   = 500 GB × $0.015 = $7.50
 *                                                           ─────
 *                                                           ~$16/mo storage
 *   → recouped by ONE seller subscription (฿299 ≈ $8.50 at current FX).
 *
 * Retention is TIGHT on free tier (7 days) to cap worst-case cost exposure
 * from a fire-and-forget spam account. Paid tiers have longer windows because
 * their revenue covers their storage several times over.
 *
 * Idempotent — skips keys that already exist so re-running doesn't overwrite
 * admin customizations.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('app_settings')) {
            return;
        }

        $defaults = [
            // ── Storage quota per tier (GB) ─────────────────────────────
            'photographer_quota_creator_gb' => '5',
            'photographer_quota_seller_gb'  => '50',
            'photographer_quota_pro_gb'     => '500',

            // Master toggle — OFF means the middleware allows everything.
            // Ship as ON by default because a silent runaway is worse than
            // a clear "quota exceeded" error during initial rollout.
            'photographer_quota_enforcement_enabled' => '1',

            // Warn the photographer when used ≥ this % of quota (0 = off).
            // The banner in their dashboard nudges them toward upgrade.
            'photographer_quota_warn_threshold_pct' => '80',

            // ── Retention defaults per tier (days) ──────────────────────
            // Overrides the single `event_default_retention_days` (used as
            // fallback if tier is somehow unknown). Shorter free = fair to
            // cloud bill; longer paid = higher-perceived value.
            'retention_days_creator' => '7',
            'retention_days_seller'  => '30',
            'retention_days_pro'     => '90',

            // Send a heads-up email this many days before auto-delete.
            // Uses existing Event.auto_delete_warned_at column (empty until now)
            // so we don't double-send even if the scheduler runs many times.
            'retention_warning_enabled'   => '1',
            'retention_warning_days_ahead' => '1',

            // ── Upgrade-savings calculator (displayed in photographer UI) ─
            // Commission % charged per tier — used by the "you'd save ฿X"
            // ROI widget. These are separate from the quota; the actual
            // commission deduction lives in the order/payout logic and
            // should be kept in sync with these when that change lands.
            'commission_pct_creator' => '30',
            'commission_pct_seller'  => '15',
            'commission_pct_pro'     => '8',

            // Flat platform fee per photo sold (THB). Same tiering rationale.
            'platform_fee_per_photo_creator' => '10',
            'platform_fee_per_photo_seller'  => '7',
            'platform_fee_per_photo_pro'     => '5',

            // Subscription prices (THB/month) — used for ROI math in the UI.
            'subscription_price_seller' => '299',
            'subscription_price_pro'    => '999',
        ];

        foreach ($defaults as $key => $value) {
            $exists = DB::table('app_settings')->where('key', $key)->exists();
            if ($exists) {
                continue;
            }
            DB::table('app_settings')->insert([
                'key'   => $key,
                'value' => $value,
            ]);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('app_settings')) {
            return;
        }

        DB::table('app_settings')->whereIn('key', [
            'photographer_quota_creator_gb',
            'photographer_quota_seller_gb',
            'photographer_quota_pro_gb',
            'photographer_quota_enforcement_enabled',
            'photographer_quota_warn_threshold_pct',
            'retention_days_creator',
            'retention_days_seller',
            'retention_days_pro',
            'retention_warning_enabled',
            'retention_warning_days_ahead',
            'commission_pct_creator',
            'commission_pct_seller',
            'commission_pct_pro',
            'platform_fee_per_photo_creator',
            'platform_fee_per_photo_seller',
            'platform_fee_per_photo_pro',
            'subscription_price_seller',
            'subscription_price_pro',
        ])->delete();
    }
};

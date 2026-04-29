<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seed 4 starter packages + credit-system AppSettings.
 *
 * Prices were tuned so photographers feel the savings vs a 15% commission
 * model (see business strategy note, 2026-04-22). At ฿1.50/credit average,
 * the platform keeps ~94% margin after R2 storage cost, and the photographer
 * saves ฿3k+ per 2k-photo event vs the legacy commission.
 *
 * Idempotent: re-running inserts only missing rows so admin tweaks survive.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('upload_credit_packages') || !Schema::hasTable('app_settings')) {
            return;
        }

        $now = now();

        $packages = [
            [
                'code'           => 'starter',
                'name'           => 'Starter — 100 ภาพ',
                'description'    => 'เหมาะกับงานเล็ก เช่น portrait session ทดลองใช้ระบบ',
                'credits'        => 100,
                'price_thb'      => 290,
                'validity_days'  => 365,
                'badge'          => null,
                'color_hex'      => '#6366f1',  // indigo
                'sort_order'     => 10,
                'is_active'      => 1,
            ],
            [
                'code'           => 'wedding',
                'name'           => 'Wedding — 500 ภาพ',
                'description'    => 'งานแต่งงานขนาดกลาง หรือ photo session ครึ่งวัน',
                'credits'        => 500,
                'price_thb'      => 990,
                'validity_days'  => 365,
                'badge'          => 'Popular',
                'color_hex'      => '#ec4899',  // pink
                'sort_order'     => 20,
                'is_active'      => 1,
            ],
            [
                'code'           => 'event',
                'name'           => 'Event — 2,000 ภาพ',
                'description'    => 'งานวิ่ง รับปริญญา งานบริษัท งาน marathon ขนาดกลาง',
                'credits'        => 2000,
                'price_thb'      => 2990,
                'validity_days'  => 365,
                'badge'          => 'Best value',
                'color_hex'      => '#10b981',  // emerald
                'sort_order'     => 30,
                'is_active'      => 1,
            ],
            [
                'code'           => 'concert',
                'name'           => 'Concert — 10,000 ภาพ',
                'description'    => 'คอนเสิร์ตใหญ่ กีฬาระดับชาติ หรือเหมาจ่ายทั้งปี',
                'credits'        => 10000,
                'price_thb'      => 9990,
                'validity_days'  => 365,
                'badge'          => null,
                'color_hex'      => '#f59e0b',  // amber
                'sort_order'     => 40,
                'is_active'      => 1,
            ],
        ];

        foreach ($packages as $p) {
            $exists = DB::table('upload_credit_packages')->where('code', $p['code'])->exists();
            if ($exists) continue;
            DB::table('upload_credit_packages')->insert(array_merge($p, [
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }

        // AppSettings for the credits system
        $settings = [
            // Master toggle — when off, every photographer is treated as commission mode
            // regardless of their billing_mode column (emergency kill switch).
            'credits_system_enabled' => '1',

            // Default billing_mode for NEWLY-approved photographers.
            // 'credits' = new model (this project), 'commission' = legacy.
            'default_billing_mode' => 'credits',

            // Monthly free credits granted to active Pro-tier photographers.
            // 0 = disabled. Expire after 30 days (use-it-or-lose-it nudge).
            'monthly_free_credits_pro'    => '100',
            'monthly_free_credits_seller' => '30',
            'monthly_free_credits_creator' => '10',

            // How many days BEFORE expiry to send a heads-up email.
            'credits_expiry_warn_days_ahead' => '7',

            // Grace: we allow uploading even at 0 balance for this many photos
            // (soft ceiling before the middleware blocks them). Lets a photographer
            // finish the last few shots of a batch if they hit zero mid-upload.
            'credits_overdraft_grace' => '3',

            // Referral bonus: when a photographer invites another photographer who
            // makes their first purchase, both receive this many credits.
            'credits_referral_bonus' => '100',
        ];

        foreach ($settings as $key => $value) {
            $exists = DB::table('app_settings')->where('key', $key)->exists();
            if ($exists) continue;
            DB::table('app_settings')->insert([
                'key'   => $key,
                'value' => $value,
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('upload_credit_packages')) {
            DB::table('upload_credit_packages')->whereIn('code', ['starter', 'wedding', 'event', 'concert'])->delete();
        }

        if (Schema::hasTable('app_settings')) {
            DB::table('app_settings')->whereIn('key', [
                'credits_system_enabled',
                'default_billing_mode',
                'monthly_free_credits_pro',
                'monthly_free_credits_seller',
                'monthly_free_credits_creator',
                'credits_expiry_warn_days_ahead',
                'credits_overdraft_grace',
                'credits_referral_bonus',
            ])->delete();
        }
    }
};

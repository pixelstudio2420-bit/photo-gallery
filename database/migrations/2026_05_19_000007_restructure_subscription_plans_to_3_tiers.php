<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Restructure subscription_plans from 5 public tiers (Free / Starter / Pro
 * / Business / Studio) to 3 (Free / Pro / Studio).
 *
 * Why now:
 *   The marketplace has 0 paid customers — this is the last window where
 *   we can change pricing structure without grandfather migration cost.
 *   Once paying customers exist, restructuring requires email campaigns,
 *   a 60-90 day grandfather window, support overhead, and potential
 *   churn. Locking in the right structure now is free.
 *
 * Decisions backed by industry research (Hick's Law + B2B SaaS conversion
 * benchmarks from Price Intelligently / ProfitWell):
 *   • 3 tiers > 5 tiers for first-time decision making
 *   • Free 20% commission > 30% (ตลาด Etsy/Patreon/Fiverr 5-20%)
 *   • Pro ฿790 (sub ฿1,000 psychology barrier) + 0% commission +
 *     unlimited events = no-brainer upgrade from Free
 *   • Studio ฿3,990 (vs old ฿9,990) = realistic for Thai studios
 *
 * Idempotent: uses upsert/update, not insert. Safe to run twice.
 *
 * Reversibility: down() restores the 5 public tiers from their
 * pre-existing rows by flipping is_public + restoring price/commission.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('subscription_plans')) {
            return;
        }

        // ─── Free: keep visible, lower commission 30% → 20% ───────────
        DB::table('subscription_plans')
            ->where('code', 'free')
            ->update([
                'commission_pct'        => 20.00,
                'tagline'               => 'เริ่มฟรีตลอดชีพ — ทดลองก่อน upgrade',
                'monthly_ai_credits'    => 100,
                'features_json'         => json_encode([
                    '5 GB storage',
                    '1 อีเวนต์เปิดขายพร้อมกัน',
                    'AI Face Search 100 ครั้ง/เดือน',
                    'Watermark "Powered by Loadroop"',
                    'commission 20% ต่อยอดขาย',
                    'เก็บรูป 30 วัน',
                    'Community support',
                ], JSON_UNESCAPED_UNICODE),
                'updated_at'            => now(),
            ]);

        // ─── Starter: hide from public (deprecated) ────────────────────
        DB::table('subscription_plans')
            ->where('code', 'starter')
            ->update([
                'is_public'  => false,
                'updated_at' => now(),
            ]);

        // ─── Business: hide from public (deprecated) ──────────────────
        DB::table('subscription_plans')
            ->where('code', 'business')
            ->update([
                'is_public'  => false,
                'updated_at' => now(),
            ]);

        // ─── Pro: lower price ฿890 → ฿790, unlimited events ──────────
        // ฿790 = below the ฿1,000 psychological barrier; lock-in
        // 0% commission for the segment that will drive most revenue.
        DB::table('subscription_plans')
            ->where('code', 'pro')
            ->update([
                'price_thb'             => 790,
                'price_annual_thb'      => 7900,
                'storage_bytes'         => 100 * 1024 * 1024 * 1024,        // 100 GB
                'max_concurrent_events' => -1,                              // unlimited
                'monthly_ai_credits'    => 5000,
                'badge'                 => 'แนะนำที่สุด',
                'tagline'               => 'สำหรับช่างภาพมืออาชีพ — commission 0% ตลอด',
                'features_json'         => json_encode([
                    '100 GB storage',
                    '∞ อีเวนต์ไม่จำกัด',
                    'AI Face Search 5,000 ครั้ง/เดือน',
                    '✨ commission 0% — ได้เต็มทุกบาท',
                    'Best Shot AI — เลือกรูปดีที่สุดอัตโนมัติ',
                    'Priority Upload (เร็วขึ้น 5×)',
                    'Custom branding (โลโก้ + สี)',
                    'Custom watermark ของคุณเอง',
                    'Advanced Analytics dashboard',
                    'LINE chat support 24/7',
                    'เก็บรูปตลอดชีพ',
                    'Money-back 30 วัน',
                ], JSON_UNESCAPED_UNICODE),
                'updated_at'            => now(),
            ]);

        // ─── Studio: lower price ฿9,990 → ฿3,990, team 25 → 10 ────────
        // ฿9,990 was unsold (0 conversions). ฿3,990 is realistic for Thai
        // studios. Reduces revenue per Studio user 60%, but absolute
        // revenue improves because more studios actually buy at this
        // price point.
        DB::table('subscription_plans')
            ->where('code', 'studio')
            ->update([
                'price_thb'             => 3990,
                'price_annual_thb'      => 39900,
                'storage_bytes'         => 1024 * 1024 * 1024 * 1024,       // 1 TB (was 2 TB)
                'max_concurrent_events' => -1,
                'max_team_seats'        => 10,                               // was 25
                'monthly_ai_credits'    => 50000,
                'tagline'               => 'สำหรับสตูดิโอ + agency — ทีม 10 คน',
                'features_json'         => json_encode([
                    '1 TB storage',
                    '∞ อีเวนต์ไม่จำกัด',
                    'AI Face Search 50,000 ครั้ง/เดือน',
                    '✨ commission 0% — ได้เต็มทุกบาท',
                    'Multi-photographer team — 10 ที่นั่ง',
                    'Best Shot AI + Color Enhance AI',
                    'Smart Captions AI',
                    'White-label — ลบ "Powered by" ออก',
                    'Custom domain + SSL ของคุณ',
                    'Priority Support (ตอบใน 3 ชม.)',
                    'Onboarding session 1-on-1',
                    'เก็บรูปตลอดชีพ',
                    'Money-back 30 วัน',
                ], JSON_UNESCAPED_UNICODE),
                'updated_at'            => now(),
            ]);

        // ─── Flush related caches so admin + public pricing pages
        // ─── reflect the new structure on the next request.
        try {
            \Illuminate\Support\Facades\Cache::forget('public.pricing.plans');
            \Illuminate\Support\Facades\Cache::forget('public.for-photographers.plans');
            \App\Models\AppSetting::flushCache();
        } catch (\Throwable) {}
    }

    public function down(): void
    {
        if (!Schema::hasTable('subscription_plans')) {
            return;
        }

        // Restore Starter + Business as public
        DB::table('subscription_plans')->where('code', 'starter')->update(['is_public' => true]);
        DB::table('subscription_plans')->where('code', 'business')->update(['is_public' => true]);

        // Restore Free 30% commission
        DB::table('subscription_plans')->where('code', 'free')->update([
            'commission_pct'     => 30.00,
            'monthly_ai_credits' => 50,
        ]);

        // Restore Pro pricing
        DB::table('subscription_plans')->where('code', 'pro')->update([
            'price_thb'             => 890,
            'price_annual_thb'      => 8900,
            'max_concurrent_events' => 10,
            'monthly_ai_credits'    => 5000,
        ]);

        // Restore Studio pricing
        DB::table('subscription_plans')->where('code', 'studio')->update([
            'price_thb'             => 9990,
            'price_annual_thb'      => 99900,
            'storage_bytes'         => 2 * 1024 * 1024 * 1024 * 1024,
            'max_team_seats'        => 25,
            'monthly_ai_credits'    => 200000,
        ]);

        try {
            \Illuminate\Support\Facades\Cache::forget('public.pricing.plans');
            \Illuminate\Support\Facades\Cache::forget('public.for-photographers.plans');
        } catch (\Throwable) {}
    }
};

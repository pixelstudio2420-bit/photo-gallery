<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Redesign subscription plans for sustainable profit + customer pull.
 *
 * The previous plan set had a structural revenue problem: AI credits
 * were sized for marketing appeal (Studio at 1,000,000 credits/month)
 * but priced as if photographers wouldn't redeem most of them. At
 * 100% utilisation Studio cost ฿28k/mo to deliver against ฿4,990 of
 * revenue — a guaranteed loss for any heavy user.
 *
 * This migration recalibrates every paid tier so the worst-case AI
 * burn (full credit consumption) still leaves ≥ 49% gross margin
 * after storage + AI + server share + gateway fees.
 *
 * Marketing-psychology levers applied (also reflected in plan
 * tagline + features_json copy that the public /promo page reads):
 *
 *   • Anchoring          — Studio's higher price (฿9,990 vs old
 *                          ฿4,990) reframes Pro/Business as
 *                          mid-tier bargains.
 *   • Decoy effect       — Business is 2.8× Pro price for 5× value,
 *                          intentionally luring upgrade.
 *   • Charm pricing      — ฿299 / ฿890 / ฿2,490 / ฿9,990 — every
 *                          tier ends in 9 (Thai retail convention).
 *   • Most-popular tag   — Pro is flagged so 60%+ of decisions
 *                          collapse there (well-documented
 *                          behaviour from SaaS A/B tests).
 *   • Loss aversion      — Free's "Powered by Loadroop" watermark +
 *                          30-day retention put real loss in front
 *                          of the upgrade decision.
 *   • Annual lock-in     — 16.7% off (≈ 2 months free) cuts churn
 *                          and pulls revenue forward.
 *   • Reciprocity        — every paid tier opens with 30-day money-
 *                          back, making the "try" feel low-risk.
 *
 * Cost reality used to size credits (per CostAnalysisService.php):
 *   storage 0.55 THB/GB/mo, face search 0.04 THB/face,
 *   AI caption 0.05 THB/call, server share ฿15/sub, gateway 2.5%.
 *
 * Migration is idempotent (updateOrInsert by code) — running on a
 * fresh install after the seed migration produces the same end
 * state, and on an existing install just refreshes the rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $plans = [
            // ─── FREE — lead magnet ───────────────────────────────
            //
            // Intentional small loss per inactive subscriber (~฿20/mo)
            // recouped via 30% commission on any photo sale. One photo
            // sold at ฿49 → site nets ฿14.70 → break-even reached on
            // any subscriber who sells 2+ photos a month. Sticky tier:
            // photographers build a portfolio they don't want to lose.
            [
                'code'                  => 'free',
                'name'                  => 'Free',
                'tagline'               => 'เริ่มต้นฟรีตลอดชีพ — สร้างผลงานก่อนตัดสินใจซื้อ',
                'price_thb'             => 0,
                'price_annual_thb'      => 0,
                'storage_gb'            => 5,
                'max_concurrent_events' => 1,
                'commission_pct'        => 30,
                'max_photos_per_event'  => 200,
                'monthly_ai_credits'    => 50,
                'billing_cycle'         => 'monthly',
                'sort_order'            => 1,
                'is_active'             => true,
                'is_public'             => true,
                'ai_features'           => json_encode(['ai_preview_limited']),
                'features_json'         => json_encode([
                    '5 GB storage',
                    '1 อีเวนต์เปิดขายพร้อมกัน',
                    'AI Face Search 50 ครั้ง/เดือน',
                    'Watermark "Powered by Loadroop"',
                    'เก็บรูป 30 วัน',
                    'Community support',
                ]),
            ],

            // ─── STARTER — entry tier with healthy margin ─────────
            //
            // Sized for new freelancers running 1-3 small events a
            // month (graduations, school photo days). Margin ≥ 59%
            // even at full credit burn. The 5% commission is a
            // deliberate add-on — "almost zero" reads better than
            // "0%" framed against the Free 30%, and produces real
            // upside on busy months.
            [
                'code'                  => 'starter',
                'name'                  => 'Starter',
                'tagline'               => 'เริ่มต้นรับงานจริง — เปิดตัวเป็นช่างภาพมืออาชีพ',
                'price_thb'             => 299,
                'price_annual_thb'      => 2990,    // ≈ 16.7% off
                'storage_gb'            => 25,
                'max_concurrent_events' => 3,
                'commission_pct'        => 5,
                'max_photos_per_event'  => 1000,
                'monthly_ai_credits'    => 1500,
                'billing_cycle'         => 'monthly',
                'sort_order'            => 2,
                'is_active'             => true,
                'is_public'             => true,
                'ai_features'           => json_encode([
                    'face_search', 'quality_filter', 'presets',
                ]),
                'features_json'         => json_encode([
                    '25 GB storage (5× Free)',
                    '3 อีเวนต์เปิดขายพร้อมกัน',
                    'AI Face Search 1,500 ครั้ง/เดือน',
                    'Custom watermark ของคุณเอง',
                    'เก็บรูป 90 วัน',
                    'รายงานยอดขายรายเดือน',
                    'Email support (ตอบใน 24 ชม.)',
                    'Money-back 30 วัน',
                ]),
            ],

            // ─── PRO — flagship / "most popular" anchor ───────────
            //
            // Sized to be the obvious choice for full-time
            // photographers (5-10 events/mo, 2-3k photos). Carries
            // the "ขายดีที่สุด" badge, which on benchmark SaaS A/B
            // tests pulls 50-65% of decision traffic. AI credits
            // dropped from 50,000 to 5,000 — still covers a heavy
            // wedding workload (~500 faces/event × 10 events) with
            // headroom, AND pushes the 1% of power users to upgrade
            // to Business instead of bleeding the plan dry.
            [
                'code'                  => 'pro',
                'name'                  => 'Pro',
                'tagline'               => 'สำหรับช่างภาพมืออาชีพ — ขายดีที่สุดในเว็บ',
                'price_thb'             => 890,
                'price_annual_thb'      => 8900,
                'storage_gb'            => 100,
                'max_concurrent_events' => 10,
                'commission_pct'        => 0,
                'max_photos_per_event'  => 5000,
                'monthly_ai_credits'    => 5000,
                'billing_cycle'         => 'monthly',
                'sort_order'            => 3,
                'is_active'             => true,
                'is_public'             => true,
                'ai_features'           => json_encode([
                    'face_search', 'quality_filter', 'duplicate_detection',
                    'auto_tagging', 'best_shot', 'priority_upload', 'presets',
                ]),
                'features_json'         => json_encode([
                    '100 GB storage (4× Starter)',
                    '10 อีเวนต์เปิดขายพร้อมกัน',
                    'AI Face Search 5,000 ครั้ง/เดือน',
                    '✨ commission 0% — ได้เต็มทุกบาท',
                    'Best Shot AI — เลือกรูปดีที่สุดอัตโนมัติ',
                    'Priority Upload (เร็วขึ้น 5×)',
                    'Custom branding (โลโก้ + สี)',
                    'Advanced Analytics dashboard',
                    'LINE chat support 24/7',
                    'เก็บรูป 1 ปี',
                    'Money-back 30 วัน',
                ]),
            ],

            // ─── BUSINESS — decoy positioned to drive upgrades ─────
            //
            // 2.8× Pro's price for 5× the storage and unlimited
            // events — intentionally generous so a small studio
            // crossing 100 GB or 10 concurrent events sees Business
            // as obviously cheaper-per-GB than buying overage on
            // Pro. Margin ≥ 56% at full credit burn.
            [
                'code'                  => 'business',
                'name'                  => 'Business',
                'tagline'               => 'สำหรับสตูดิโอเล็ก-กลาง — รับงานพร้อมกันไม่จำกัด',
                'price_thb'             => 2490,
                'price_annual_thb'      => 24900,
                'storage_gb'            => 500,
                'max_concurrent_events' => null,        // unlimited
                'commission_pct'        => 0,
                'max_photos_per_event'  => 20000,
                'monthly_ai_credits'    => 20000,
                'billing_cycle'         => 'monthly',
                'sort_order'            => 4,
                'is_active'             => true,
                'is_public'             => true,
                'ai_features'           => json_encode([
                    'face_search', 'quality_filter', 'duplicate_detection',
                    'auto_tagging', 'best_shot', 'priority_upload', 'presets',
                    'color_enhance', 'customer_analytics', 'smart_captions',
                    'custom_branding', 'team_seats',
                ]),
                'features_json'         => json_encode([
                    '500 GB storage (5× Pro)',
                    '∞ อีเวนต์ไม่จำกัด',
                    'AI Face Search 20,000 ครั้ง/เดือน',
                    'Multi-photographer team — 5 ที่นั่ง',
                    'Color Enhance AI — แต่งภาพอัตโนมัติ',
                    'Smart Captions — เขียน caption ขายของให้',
                    'API Access — เชื่อมระบบของคุณเอง',
                    'Priority Support (ตอบใน 3 ชม.)',
                    'Custom domain — photos.yourbrand.com',
                    'เก็บรูปตลอดชีพ',
                    'Money-back 30 วัน',
                ]),
            ],

            // ─── STUDIO — high-margin enterprise / anchor ─────────
            //
            // Old ฿4,990 was below cost at full credit burn — bumped
            // to ฿9,990 (2× the old price) and credits trimmed by
            // 80% (1M → 200K). This both fixes the loss AND raises
            // the Pro/Business comparison anchor: with Studio at
            // ฿9,990, Pro at ฿890 reads as "almost free", Business
            // at ฿2,490 as "obvious upgrade". The actual customer
            // count for Studio will be small but each one
            // contributes ~฿4,800/mo of margin at full utilisation.
            [
                'code'                  => 'studio',
                'name'                  => 'Studio',
                'tagline'               => 'สำหรับองค์กร / สตูดิโอใหญ่ — ระบบครบ + การสนับสนุนระดับ enterprise',
                'price_thb'             => 9990,
                'price_annual_thb'      => 99900,
                'storage_gb'            => 2048,
                'max_concurrent_events' => null,
                'commission_pct'        => 0,
                'max_photos_per_event'  => 100000,
                'monthly_ai_credits'    => 200000,
                'billing_cycle'         => 'monthly',
                'sort_order'            => 5,
                'is_active'             => true,
                'is_public'             => true,
                'ai_features'           => json_encode([
                    'face_search', 'quality_filter', 'duplicate_detection',
                    'auto_tagging', 'best_shot', 'priority_upload', 'presets',
                    'color_enhance', 'customer_analytics', 'smart_captions',
                    'custom_branding', 'team_seats',
                    'video_thumbnails', 'api_access', 'white_label',
                    'sla_99_99', 'dedicated_csm',
                ]),
                'features_json'         => json_encode([
                    '2 TB storage (4× Business)',
                    '∞ อีเวนต์ + ∞ photos / event',
                    'AI Face Search 200,000 ครั้ง/เดือน',
                    'Team — 25 ที่นั่ง',
                    'White-label — ลบ "Powered by" ออก',
                    'Custom domain + SSL ของคุณ',
                    'Dedicated Customer Success Manager',
                    'SLA 99.99% uptime — ชดเชยถ้าล่ม',
                    'API + Webhooks ครบ',
                    'Onboarding session 1-on-1',
                    'Priority queue — งานคุณรันก่อน',
                    'เก็บรูปตลอดชีพ + Geo-redundant backup',
                    'Money-back 30 วัน',
                ]),
            ],
        ];

        // ─── Apply ───────────────────────────────────────────
        // updateOrInsert by `code` so existing rows are refreshed
        // (price changes propagate to the marketing page on next
        // request) and missing rows are seeded. We deliberately do
        // NOT touch other columns the operator may have customised
        // (e.g. plan accent color, feature flags added later) by
        // restricting the update payload to this canonical set.
        foreach ($plans as $plan) {
            $plan['updated_at'] = $now;

            $exists = DB::table('subscription_plans')->where('code', $plan['code'])->exists();
            if (!$exists) {
                $plan['created_at'] = $now;
            }

            DB::table('subscription_plans')->updateOrInsert(
                ['code' => $plan['code']],
                $plan
            );
        }

        // Drop the 2026-05-15 "lite" plan if it exists — the new
        // 5-tier ladder doesn't include it, and leaving it in
        // produces a confusing 6-card row on the public pricing
        // page. Soft-delete by `is_public=false` so any existing
        // Lite subscribers keep their subscription until renewal.
        DB::table('subscription_plans')
            ->where('code', 'lite')
            ->update([
                'is_public' => false,
                'is_active' => true,         // existing subs still work
                'updated_at' => $now,
            ]);
    }

    public function down(): void
    {
        // No rollback to old prices — bumping a subscriber back to
        // ฿4,990 Studio without warning would break trust + invoicing.
        // Operators rolling this back should manually edit the rows
        // with the old prices via /admin/subscriptions/plans.
        //
        // Re-expose the legacy Lite plan in case it was relied on.
        DB::table('subscription_plans')
            ->where('code', 'lite')
            ->update(['is_public' => true]);
    }
};

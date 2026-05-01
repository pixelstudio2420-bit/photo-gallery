<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * One-shot refresh of subscription_plans rows to the 2026-04-30
 * profit-engineered + psychology-driven set.
 *
 * Why a seeder instead of relying on the existing migration:
 *   The migration `2026_04_30_155608_redesign_plans_for_profit_and_psychology`
 *   ran cleanly in our local test, but on the operator's Laravel
 *   Cloud Postgres the values stayed stuck at the previous seed
 *   (Studio ฿4,990, Free 20%, Lite still public). Either the
 *   migration row got logged but the underlying SQL didn't execute,
 *   or it never ran at all — every direct `tinker --execute` UPDATE
 *   we tried also silently no-op'd, suggesting an issue with how
 *   the operator's web console relays multi-token argv.
 *
 *   A seeder bypasses that whole pathway. The operator runs ONE
 *   short command — `php artisan db:seed --class=PlanRefreshSeeder
 *   --force` — and the writes happen in-process, not via the shell.
 *
 * Idempotent: every row is written via `update(...)` keyed by `code`,
 * so re-running this seeder produces the same end-state and never
 * inserts duplicates.
 */
class PlanRefreshSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            // [code, price_thb, price_annual_thb, storage_gb, max_concurrent_events, commission_pct, monthly_ai_credits, sort_order, is_public, tagline]
            ['free',     0,    0,     5,    1,    30, 50,     1, 1, 'เริ่มต้นฟรีตลอดชีพ — สร้างผลงานก่อนตัดสินใจซื้อ'],
            ['starter',  299,  2990,  25,   3,    5,  1500,   2, 1, 'เริ่มต้นรับงานจริง — เปิดตัวเป็นช่างภาพมืออาชีพ'],
            ['pro',      890,  8900,  100,  10,   0,  5000,   3, 1, 'สำหรับช่างภาพมืออาชีพ — ขายดีที่สุดในเว็บ'],
            ['business', 2490, 24900, 500,  null, 0,  20000,  4, 1, 'สำหรับสตูดิโอเล็ก-กลาง — รับงานพร้อมกันไม่จำกัด'],
            ['studio',   9990, 99900, 2048, null, 0,  200000, 5, 1, 'สำหรับองค์กร / สตูดิโอใหญ่ — ระบบครบ + การสนับสนุนระดับ enterprise'],
        ];

        foreach ($plans as [$code, $monthly, $annual, $storage, $maxEvents, $commission, $aiCredits, $sortOrder, $isPublic, $tagline]) {
            $affected = DB::table('subscription_plans')
                ->where('code', $code)
                ->update([
                    'price_thb'             => $monthly,
                    'price_annual_thb'      => $annual,
                    'storage_gb'            => $storage,
                    'max_concurrent_events' => $maxEvents,
                    'commission_pct'        => $commission,
                    'monthly_ai_credits'    => $aiCredits,
                    'sort_order'            => $sortOrder,
                    'is_public'             => $isPublic,
                    'tagline'               => $tagline,
                    'updated_at'            => now(),
                ]);

            $this->command?->info(sprintf(
                '  %s %-9s ฿%-5d  /yr ฿%-6d  storage:%4dGB  events:%-3s  ai:%-7d  cmsn:%d%%  (rows: %d)',
                $affected ? '✓' : '⚠',
                $code,
                $monthly,
                $annual,
                $storage,
                $maxEvents === null ? '∞' : $maxEvents,
                $aiCredits,
                $commission,
                $affected
            ));
        }

        // Hide Lite from the public pricing pages — keeps existing
        // Lite subscribers' invoicing alive but takes the 6th card off
        // the marketing grid.
        $hidden = DB::table('subscription_plans')
            ->where('code', 'lite')
            ->update([
                'is_public'  => 0,
                'updated_at' => now(),
            ]);
        $this->command?->info("  ✓ lite      → hidden ($hidden row)");

        $this->command?->info('');
        $this->command?->info('Done. Verify via /promo or /admin/subscriptions/plans.');
    }
}

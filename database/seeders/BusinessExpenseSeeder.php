<?php

namespace Database\Seeders;

use App\Models\BusinessExpense;
use Illuminate\Database\Seeder;

/**
 * Seeds a realistic starter set of business expenses.
 *
 * These are the production-typical costs a Thai photo-gallery SaaS would
 * incur at small-to-medium scale (≈5k-20k photos/month, ≈500 paid orders).
 * Rates are rounded ball-park values from each vendor's public pricing as
 * of April 2026; the admin is expected to adjust them to match actual
 * invoices.
 *
 * Idempotent: keyed on `name + provider` so re-running refreshes amounts
 * instead of duplicating rows.
 */
class BusinessExpenseSeeder extends Seeder
{
    public function run(): void
    {
        $usd = 35.50; // locked USD→THB reference rate for seed values

        $expenses = [
            // ─── Infrastructure ───────────────────────────────────────
            [
                'category'          => 'infrastructure',
                'name'              => 'VPS (Production Server)',
                'provider'          => 'DigitalOcean',
                'description'       => '4 vCPU / 8 GB RAM / 160 GB NVMe — Bangkok region',
                'amount'            => 48 * $usd, // ≈ $48/mo
                'original_amount'   => 48.00,
                'original_currency' => 'USD',
                'exchange_rate'     => $usd,
                'billing_cycle'     => 'monthly',
                'allocated_to'      => ['shared'],
                'is_critical'       => true,
            ],
            [
                'category'          => 'infrastructure',
                'name'              => 'Managed Database (MySQL)',
                'provider'          => 'DigitalOcean',
                'description'       => '2 GB RAM / 30 GB SSD — ให้บริการหลักของแอป',
                'amount'            => 15 * $usd,
                'original_amount'   => 15.00,
                'original_currency' => 'USD',
                'exchange_rate'     => $usd,
                'billing_cycle'     => 'monthly',
                'allocated_to'      => ['shared'],
                'is_critical'       => true,
            ],

            // ─── Storage + CDN ────────────────────────────────────────
            [
                'category'          => 'storage',
                'name'              => 'Cloudflare R2 — Photo Storage',
                'provider'          => 'Cloudflare',
                'description'       => 'ต้นฉบับ + ตัวอย่างรูปภาพ, ไม่มีค่า egress',
                'amount'            => 0.015 * 350 * $usd, // 0.015 $/GB × 350 GB × THB
                'original_amount'   => 0.015 * 350,
                'original_currency' => 'USD',
                'exchange_rate'     => $usd,
                'billing_cycle'     => 'usage_based',
                'unit_cost'         => 0.015 * $usd,
                'usage_unit'        => 'GB',
                'estimated_monthly_usage' => 350,
                'allocated_to'      => ['events', 'photos', 'downloads'],
                'is_critical'       => true,
            ],
            [
                'category'          => 'cdn',
                'name'              => 'Cloudflare Pro Plan',
                'provider'          => 'Cloudflare',
                'description'       => 'WAF, advanced caching, image optimization',
                'amount'            => 20 * $usd,
                'original_amount'   => 20.00,
                'original_currency' => 'USD',
                'exchange_rate'     => $usd,
                'billing_cycle'     => 'monthly',
                'allocated_to'      => ['shared', 'downloads'],
            ],

            // ─── AI / Face Search ─────────────────────────────────────
            [
                'category'          => 'ai',
                'name'              => 'AWS Rekognition — Face Search',
                'provider'          => 'AWS',
                'description'       => 'ประมาณ $1 ต่อ 1,000 faces/month เก็บไว้ในคอลเล็กชัน',
                'amount'            => 0.001 * 60000 * $usd, // 60k faces × $0.001
                'original_amount'   => 0.001 * 60000,
                'original_currency' => 'USD',
                'exchange_rate'     => $usd,
                'billing_cycle'     => 'usage_based',
                'unit_cost'         => 0.001 * $usd,
                'usage_unit'        => 'face',
                'estimated_monthly_usage' => 60000,
                'allocated_to'      => ['face_search'],
                'is_critical'       => false,
            ],
            [
                'category'          => 'ai',
                'name'              => 'AWS Rekognition — Moderation',
                'provider'          => 'AWS',
                'description'       => 'ตรวจภาพอัตโนมัติ ($0.001/image)',
                'amount'            => 0.001 * 15000 * $usd,
                'original_amount'   => 0.001 * 15000,
                'original_currency' => 'USD',
                'exchange_rate'     => $usd,
                'billing_cycle'     => 'usage_based',
                'unit_cost'         => 0.001 * $usd,
                'usage_unit'        => 'image',
                'estimated_monthly_usage' => 15000,
                'allocated_to'      => ['photos'],
            ],

            // ─── SaaS / Tools ─────────────────────────────────────────
            [
                'category'          => 'saas',
                'name'              => 'Sentry — Error Tracking',
                'provider'          => 'Sentry.io',
                'description'       => 'Team plan สำหรับ 50k events/mo',
                'amount'            => 26 * $usd,
                'original_amount'   => 26.00,
                'original_currency' => 'USD',
                'exchange_rate'     => $usd,
                'billing_cycle'     => 'monthly',
                'allocated_to'      => ['admin', 'shared'],
            ],
            [
                'category'          => 'saas',
                'name'              => 'GitHub — Private Repo + Actions',
                'provider'          => 'GitHub',
                'description'       => 'Team plan สำหรับ CI/CD',
                'amount'            => 4 * $usd,
                'original_amount'   => 4.00,
                'original_currency' => 'USD',
                'exchange_rate'     => $usd,
                'billing_cycle'     => 'monthly',
                'allocated_to'      => ['shared'],
            ],
            [
                'category'          => 'saas',
                'name'              => 'Mailgun — Transactional Email',
                'provider'          => 'Mailgun',
                'description'       => 'ส่งใบเสร็จ, แจ้งเตือน, reset password',
                'amount'            => 35 * $usd,
                'original_amount'   => 35.00,
                'original_currency' => 'USD',
                'exchange_rate'     => $usd,
                'billing_cycle'     => 'monthly',
                'allocated_to'      => ['notifications', 'payments'],
            ],

            // ─── Domain ───────────────────────────────────────────────
            [
                'category'      => 'domain',
                'name'          => 'Domain Name (.com)',
                'provider'      => 'Namecheap',
                'description'   => 'ต่ออายุโดเมนปีละครั้ง',
                'amount'        => 550,      // ≈ $15-16
                'billing_cycle' => 'yearly',
                'allocated_to'  => ['shared'],
                'is_critical'   => true,
            ],
            [
                'category'      => 'domain',
                'name'          => 'SSL Certificate (Wildcard)',
                'provider'      => 'Cloudflare',
                'description'   => 'รวมใน Cloudflare Pro — ตั้งเป็น 0 เพื่อบันทึกไว้',
                'amount'        => 0,
                'billing_cycle' => 'yearly',
                'allocated_to'  => ['shared'],
            ],

            // ─── Payment Gateway ──────────────────────────────────────
            [
                'category'    => 'payment',
                'name'        => 'Stripe — Card Processing (3.65%)',
                'provider'    => 'Stripe',
                'description' => 'ต้นทุนตามยอดขาย — ปรับ estimate ได้',
                'amount'      => 0.0365 * 120000, // 3.65% × ยอด 120k/เดือน
                'billing_cycle' => 'usage_based',
                'unit_cost'     => 0.0365,
                'usage_unit'    => 'THB revenue',
                'estimated_monthly_usage' => 120000,
                'allocated_to'  => ['payments'],
            ],
            [
                'category'    => 'payment',
                'name'        => 'PromptPay QR (Bank Fee)',
                'provider'    => 'SCB / K-Bank',
                'description' => 'ค่าธรรมเนียมรับเงินผ่าน QR PromptPay',
                'amount'      => 1 * 500, // 1 THB × 500 orders/month
                'billing_cycle' => 'usage_based',
                'unit_cost'     => 1,
                'usage_unit'    => 'order',
                'estimated_monthly_usage' => 500,
                'allocated_to'  => ['payments'],
            ],

            // ─── Marketing ─────────────────────────────────────────────
            [
                'category'    => 'marketing',
                'name'        => 'Meta Ads (Facebook / Instagram)',
                'provider'    => 'Meta',
                'description' => 'งบโฆษณาเฉลี่ยต่อเดือน',
                'amount'      => 8000,
                'billing_cycle' => 'monthly',
                'allocated_to'  => ['events', 'shared'],
            ],
            [
                'category'    => 'marketing',
                'name'        => 'Google Ads',
                'provider'    => 'Google',
                'description' => 'Search ads สำหรับคีย์เวิร์ด "รูปงานวิ่ง" ฯลฯ',
                'amount'      => 5000,
                'billing_cycle' => 'monthly',
                'allocated_to'  => ['events', 'shared'],
            ],

            // ─── Legal / Accounting ────────────────────────────────────
            [
                'category'    => 'accounting',
                'name'        => 'นักบัญชี (Outsource)',
                'provider'    => 'สำนักงานบัญชี',
                'description' => 'ทำบัญชี + ยื่นภาษีรายเดือน',
                'amount'      => 3500,
                'billing_cycle' => 'monthly',
                'allocated_to'  => ['shared'],
            ],
            [
                'category'    => 'legal',
                'name'        => 'PDPA Compliance Review',
                'provider'    => 'ทนายความ',
                'description' => 'ค่าที่ปรึกษา PDPA + ทบทวนนโยบายรายปี',
                'amount'      => 12000,
                'billing_cycle' => 'yearly',
                'allocated_to'  => ['face_search', 'shared'],
            ],

            // ─── Personnel ─────────────────────────────────────────────
            [
                'category'    => 'personnel',
                'name'        => 'Admin / Customer Support',
                'provider'    => 'In-house',
                'description' => 'พนักงานตอบแชต + ดูแลคำสั่งซื้อ (Part-time)',
                'amount'      => 15000,
                'billing_cycle' => 'monthly',
                'allocated_to'  => ['admin', 'chat'],
                'is_critical'   => true,
            ],
        ];

        foreach ($expenses as $exp) {
            BusinessExpense::updateOrCreate(
                ['name' => $exp['name'], 'provider' => $exp['provider'] ?? null],
                array_merge([
                    'currency'   => 'THB',
                    'is_active'  => true,
                    'is_critical' => false,
                ], $exp)
            );
        }

        $this->command?->info('✓ Business expenses seeded: ' . count($expenses) . ' rows.');
    }
}

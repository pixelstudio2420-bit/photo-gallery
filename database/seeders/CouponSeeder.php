<?php

namespace Database\Seeders;

use App\Models\Coupon;
use Illuminate\Database\Seeder;

/**
 * Seeds production-default coupons that map to real marketing campaigns.
 *
 * Coverage:
 *   • Welcome (first-time discount)          — new customer activation
 *   • Seasonal (Songkran / NewYear)          — Thai festival campaigns
 *   • Tiered % with cap                      — protect margin on big orders
 *   • Flat amount                            — small-ticket incentives
 *   • High-value referral                    — VIP/influencer distribution
 *   • "Always on" student discount           — long-term usage cap
 *
 * Everything is idempotent via `updateOrCreate(['code' => ...], ...)`.
 * Dates are set generously so the seeded coupons stay valid for the current
 * fiscal year; admins can tighten them via the UI.
 */
class CouponSeeder extends Seeder
{
    public function run(): void
    {
        $now       = now();
        $yearStart = $now->copy()->startOfYear();
        $yearEnd   = $now->copy()->endOfYear();
        $songkran  = $now->copy()->setDate($now->year, 4, 20);
        $newYear   = $now->copy()->setDate($now->year, 1, 15);

        $coupons = [
            // Welcome: 10% off first order, small cap
            [
                'code'           => 'WELCOME10',
                'name'           => 'ยินดีต้อนรับลูกค้าใหม่ 10%',
                'description'    => 'ส่วนลด 10% สำหรับการสั่งซื้อครั้งแรก (ลดสูงสุด 300 บาท, ขั้นต่ำ 500 บาท)',
                'type'           => 'percent',
                'value'          => 10,
                'min_order'      => 500,
                'max_discount'   => 300,
                'usage_limit'    => null,    // open to everyone
                'per_user_limit' => 1,
                'start_date'     => null,
                'end_date'       => null,
                'is_active'      => true,
            ],
            // Songkran seasonal
            [
                'code'           => 'SONGKRAN15',
                'name'           => 'ฉลองสงกรานต์ 15%',
                'description'    => 'ลดรับสงกรานต์ 15% สำหรับงานวิ่งและงานเทศกาล (ลดสูงสุด 500 บาท)',
                'type'           => 'percent',
                'value'          => 15,
                'min_order'      => 800,
                'max_discount'   => 500,
                'usage_limit'    => 500,
                'per_user_limit' => 2,
                'start_date'     => $songkran->copy()->subDays(10),
                'end_date'       => $songkran->copy()->addDays(10),
                'is_active'      => true,
            ],
            // New Year
            [
                'code'           => 'NEWYEAR20',
                'name'           => 'ต้อนรับปีใหม่ 20%',
                'description'    => 'ส่วนลดพิเศษต้นปี 20% (ลดสูงสุด 800 บาท, ขั้นต่ำ 1,000 บาท)',
                'type'           => 'percent',
                'value'          => 20,
                'min_order'      => 1000,
                'max_discount'   => 800,
                'usage_limit'    => 300,
                'per_user_limit' => 1,
                'start_date'     => $yearStart,
                'end_date'       => $newYear->copy()->addDays(30),
                'is_active'      => true,
            ],
            // Flat amount
            [
                'code'           => 'SAVE100',
                'name'           => 'ลดทันที 100 บาท',
                'description'    => 'ลด 100 บาท เมื่อสั่งซื้อครบ 600 บาทขึ้นไป',
                'type'           => 'fixed',
                'value'          => 100,
                'min_order'      => 600,
                'max_discount'   => null,
                'usage_limit'    => null,
                'per_user_limit' => 3,
                'start_date'     => null,
                'end_date'       => null,
                'is_active'      => true,
            ],
            [
                'code'           => 'SAVE300',
                'name'           => 'ลดทันที 300 บาท',
                'description'    => 'ลด 300 บาท เมื่อสั่งซื้อครบ 2,000 บาทขึ้นไป',
                'type'           => 'fixed',
                'value'          => 300,
                'min_order'      => 2000,
                'max_discount'   => null,
                'usage_limit'    => null,
                'per_user_limit' => 2,
                'start_date'     => null,
                'end_date'       => null,
                'is_active'      => true,
            ],
            // VIP / referral (single-use high-value)
            [
                'code'           => 'VIP1000',
                'name'           => 'VIP Referral — ลด 1,000 บาท',
                'description'    => 'คูปองสำหรับลูกค้า VIP / การแนะนำ ลด 1,000 บาท (ใช้ครั้งเดียว, ขั้นต่ำ 3,500 บาท)',
                'type'           => 'fixed',
                'value'          => 1000,
                'min_order'      => 3500,
                'max_discount'   => null,
                'usage_limit'    => 100,
                'per_user_limit' => 1,
                'start_date'     => null,
                'end_date'       => $yearEnd,
                'is_active'      => true,
            ],
            // Student
            [
                'code'           => 'STUDENT12',
                'name'           => 'ส่วนลดสำหรับนักเรียน/นักศึกษา 12%',
                'description'    => 'สำหรับงานรับปริญญา งานกีฬา ลด 12% (ลดสูงสุด 400 บาท)',
                'type'           => 'percent',
                'value'          => 12,
                'min_order'      => 500,
                'max_discount'   => 400,
                'usage_limit'    => 1000,
                'per_user_limit' => 2,
                'start_date'     => null,
                'end_date'       => $yearEnd,
                'is_active'      => true,
            ],
            // Wedding / high-ticket
            [
                'code'           => 'WEDDING2000',
                'name'           => 'ลูกค้างานแต่ง ลด 2,000',
                'description'    => 'สำหรับงานแต่งงาน ลด 2,000 บาท เมื่อสั่งซื้อครบ 8,000 บาท',
                'type'           => 'fixed',
                'value'          => 2000,
                'min_order'      => 8000,
                'max_discount'   => null,
                'usage_limit'    => 50,
                'per_user_limit' => 1,
                'start_date'     => null,
                'end_date'       => $yearEnd,
                'is_active'      => true,
            ],
        ];

        foreach ($coupons as $c) {
            Coupon::updateOrCreate(
                ['code' => $c['code']],
                [
                    'name'           => $c['name'],
                    'description'    => $c['description'],
                    'type'           => $c['type'],
                    'value'          => $c['value'],
                    'min_order'      => $c['min_order'],
                    'max_discount'   => $c['max_discount'],
                    'usage_limit'    => $c['usage_limit'],
                    'per_user_limit' => $c['per_user_limit'],
                    'start_date'     => $c['start_date'],
                    'end_date'       => $c['end_date'],
                    'is_active'      => $c['is_active'],
                ]
            );
        }

        $this->command?->info('✓ Coupons seeded: ' . count($coupons) . ' rows.');
    }
}

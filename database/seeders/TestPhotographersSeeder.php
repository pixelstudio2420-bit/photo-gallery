<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Six test photographers — one per niche × geography combo.
 *
 * Why these specific six
 * ----------------------
 * The SEO landing page system already has 6 niches (wedding/graduation/
 * running/concert/corporate/prewedding). Seeding one photographer per
 * niche means QA can test the niche-scoped landings (`/pro/wedding/bangkok`)
 * with real data instead of empty states.
 *
 * Idempotent: rerunning updates rows in place — no duplicate users.
 *
 * Password for ALL accounts: `password123`
 *
 * Niche                 | Email                          | Province (id) | Tier
 * ----------------------|--------------------------------|---------------|--------
 * แต่งงาน                | wedding-bkk@test.local         | กรุงเทพ (10)   | Pro
 * รับปริญญา              | graduation-cmu@test.local      | เชียงใหม่ (50)  | Seller
 * งานวิ่ง                | running-phuket@test.local      | ภูเก็ต (83)    | Creator
 * คอนเสิร์ต              | concert-bkk@test.local         | กรุงเทพ (10)   | Pro
 * อีเวนต์บริษัท            | corporate-bkk@test.local       | กรุงเทพ (10)   | Seller
 * Pre-Wedding          | prewedding-huahin@test.local   | ประจวบฯ (77)   | Seller
 */
class TestPhotographersSeeder extends Seeder
{
    public function run(): void
    {
        $photographers = [
            [
                'email'        => 'wedding-bkk@test.local',
                'first_name'   => 'มะลิ',
                'last_name'    => 'แต่งงานสตูดิโอ',
                'username'     => 'maliweddingstudio',
                'display_name' => 'Mali Wedding Studio',
                'bio'          => 'ช่างภาพงานแต่งงานมืออาชีพ · กรุงเทพและปริมณฑล · ประสบการณ์ 8 ปี · 200+ งานต่อปี · ส่งรูปไม่เกิน 7 วัน',
                'province_id'  => 10,
                'tier'         => 'pro',
                'years'        => 8,
                'specialties'  => ['งานแต่ง', 'pre-wedding', 'engagement'],
                'phone'        => '0812345001',
                'promptpay'    => '0812345001',
            ],
            [
                'email'        => 'graduation-cmu@test.local',
                'first_name'   => 'นพ',
                'last_name'    => 'ปริญญาเชียงใหม่',
                'username'     => 'nopgraduation',
                'display_name' => 'Nop Graduation CMU',
                'bio'          => 'ช่างภาพรับปริญญา ม.เชียงใหม่ · บริการนอก/ในสถานที่ · ราคาเริ่มต้นน่ารัก · จองล่วงหน้า 1 เดือน',
                'province_id'  => 50,
                'tier'         => 'seller',
                'years'        => 4,
                'specialties'  => ['รับปริญญา', 'family portrait'],
                'phone'        => '0812345002',
                'promptpay'    => '0812345002',
            ],
            [
                'email'        => 'running-phuket@test.local',
                'first_name'   => 'เจ',
                'last_name'    => 'ภูเก็ตวิ่งภาพ',
                'username'     => 'jeyphuketrun',
                'display_name' => 'Jey Phuket Run',
                'bio'          => 'ถ่ายงานวิ่ง/มาราธอนทั่วเกาะภูเก็ต · ใช้เลนส์ tele 70-200 + 100-400 · ส่งรูปภายในคืนเดียว',
                'province_id'  => 83,
                'tier'         => 'creator',
                'years'        => 2,
                'specialties'  => ['งานวิ่ง', 'sport', 'mountain bike'],
                'phone'        => '0812345003',
                'promptpay'    => null,
            ],
            [
                'email'        => 'concert-bkk@test.local',
                'first_name'   => 'ติว',
                'last_name'    => 'คอนเสิร์ตเฮาส์',
                'username'     => 'tewconcerthouse',
                'display_name' => 'Tew Concert House',
                'bio'          => 'ช่างภาพคอนเสิร์ต/มีตติ้ง · ประจำหลายค่ายในไทย · ใช้แฟลช high-speed sync · เคยถ่ายศิลปิน K-Pop, Indie',
                'province_id'  => 10,
                'tier'         => 'pro',
                'years'        => 6,
                'specialties'  => ['คอนเสิร์ต', 'meet & greet', 'fan event'],
                'phone'        => '0812345004',
                'promptpay'    => '0812345004',
            ],
            [
                'email'        => 'corporate-bkk@test.local',
                'first_name'   => 'อู๋',
                'last_name'    => 'อีเวนต์ออร์แกไนเซอร์',
                'username'     => 'oocorporateevents',
                'display_name' => 'Oo Corporate Events',
                'bio'          => 'ช่างภาพอีเวนต์บริษัท/ Town Hall / launch event · ออกใบกำกับภาษีบริษัทได้ทันที · มี backup ครบ 2 ตัว',
                'province_id'  => 10,
                'tier'         => 'seller',
                'years'        => 5,
                'specialties'  => ['corporate event', 'product launch', 'team building'],
                'phone'        => '0812345005',
                'promptpay'    => '0812345005',
            ],
            [
                'email'        => 'prewedding-huahin@test.local',
                'first_name'   => 'แอน',
                'last_name'    => 'หัวหินสตูดิโอ',
                'username'     => 'annhuahinstudio',
                'display_name' => 'Ann Hua Hin Studio',
                'bio'          => 'Pre-Wedding หัวหิน/ชะอำ · บริการแต่งหน้า + จัดทรงครบเซ็ต · ทะเลล้วน, สวนสไตล์ยุโรป, สถาปัตย์เก่า',
                'province_id'  => 77,
                'tier'         => 'seller',
                'years'        => 3,
                'specialties'  => ['pre-wedding', 'wedding', 'engagement'],
                'phone'        => '0812345006',
                'promptpay'    => '0812345006',
            ],
        ];

        $i = 0;
        foreach ($photographers as $p) {
            $i++;

            // 1. auth_users (the login row).
            // updateOrInsert sets BOTH match column + values columns. We
            // explicitly include created_at because Postgres has no default
            // for it on this table — without it, fresh inserts get NULL,
            // which then breaks $user->created_at->format() in views.
            $existingUser = DB::table('auth_users')->where('email', $p['email'])->first(['id']);
            DB::table('auth_users')->updateOrInsert(
                ['email' => $p['email']],
                array_filter([
                    'email'             => $p['email'],
                    'password_hash'     => Hash::make('password123'),
                    'first_name'        => $p['first_name'],
                    'last_name'         => $p['last_name'],
                    'username'          => $p['username'],
                    'auth_provider'     => 'local',
                    'status'            => 'active',
                    'email_verified'    => true,
                    'email_verified_at' => now(),
                    'updated_at'        => now(),
                    // Only stamp created_at on FRESH insert — don't bump
                    // the original signup date when re-running the seeder.
                    'created_at'        => $existingUser ? null : now(),
                ], fn($v) => $v !== null)
            );

            $user = DB::table('auth_users')->where('email', $p['email'])->first(['id']);

            // 2. photographer_profiles (status=approved → live on the site)
            $existing = DB::table('photographer_profiles')->where('user_id', $user->id)->first();

            $profileData = [
                'user_id'           => $user->id,
                'photographer_code' => 'PH-T' . str_pad($i, 4, '0', STR_PAD_LEFT),
                'slug'              => Str::slug($p['display_name']) . '-test',
                'display_name'      => $p['display_name'],
                'bio'               => $p['bio'],
                'phone'             => $p['phone'],
                'promptpay_number'  => $p['promptpay'],
                'province_id'       => $p['province_id'],
                'specialties'       => json_encode($p['specialties'], JSON_UNESCAPED_UNICODE),
                'years_experience'  => $p['years'],
                'tier'              => $p['tier'],
                'status'            => 'approved',
                'onboarding_stage'  => 'active',
                'approved_at'       => now(),
                'updated_at'        => now(),
            ];

            // commission_rate: only set on FRESH insert (don't override
            // SubscriptionService-managed value on update — same rule
            // as DefaultAccountsSeeder).
            if (!$existing) {
                $profileData['commission_rate'] = $p['tier'] === 'pro'
                    ? 92.0
                    : ($p['tier'] === 'seller' ? 85.0 : 80.0);
                $profileData['created_at'] = now();
            }

            // PromptPay verification — pretend ITMX has confirmed the bank
            // account holder name. Without this, the photographer dashboard
            // shows a "รอยืนยันกับธนาคาร" badge even after admin approval,
            // which interferes with manual QA of the payout flow.
            // Idempotent: only fill these fields if currently null.
            if ($p['promptpay']) {
                $profileData['promptpay_verified_name'] = $existing && $existing->promptpay_verified_name
                    ? $existing->promptpay_verified_name
                    : 'Mr/Mrs ' . $p['display_name'];
                $profileData['promptpay_verified_at']   = $existing && $existing->promptpay_verified_at
                    ? $existing->promptpay_verified_at
                    : now();
                // Bank account name is required at payout time; back-fill
                // from the display_name when missing so QA doesn't have to
                // re-enter it manually.
                $profileData['bank_account_name']       = $existing && $existing->bank_account_name
                    ? $existing->bank_account_name
                    : $p['display_name'];
            }

            DB::table('photographer_profiles')->updateOrInsert(
                ['user_id' => $user->id],
                $profileData
            );

            // 3. auth_social_logins — pre-link Google + LINE so QA doesn't
            //    need to OAuth through real providers to test the
            //    photographer dashboard. RequireGoogleLinked middleware
            //    accepts EITHER google or line as proof of social
            //    verification, so the dashboard gate opens immediately.
            //
            //    Idempotent: (provider, provider_id) is uniquely indexed,
            //    and we use deterministic provider_ids so reseeding doesn't
            //    create dup rows. We don't use updateOrInsert with the
            //    user_id key alone because a single user can legitimately
            //    have BOTH a google AND a line row.
            $googleProviderId = 'test-google-' . $user->id;
            $lineProviderId   = 'U' . str_pad((string) $user->id, 32, '0', STR_PAD_LEFT);

            foreach ([
                ['provider' => 'google', 'provider_id' => $googleProviderId],
                ['provider' => 'line',   'provider_id' => $lineProviderId],
            ] as $socialRow) {
                DB::table('auth_social_logins')->updateOrInsert(
                    [
                        'provider'    => $socialRow['provider'],
                        'provider_id' => $socialRow['provider_id'],
                    ],
                    [
                        'user_id'    => $user->id,
                        'avatar'     => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                );
            }

            // Note: LineNotifyService::getLineUserId resolves directly from
            // auth_social_logins, so the social-login row above is enough.
            // Some legacy install variants used to mirror onto a separate
            // `users.line_user_id` column — that path no-ops on schemas
            // where the column doesn't exist.
        }

        $this->command?->info('Seeded ' . count($photographers) . ' test photographers');
        $this->command?->info('  Login: <email> / password123');
        $this->command?->info('  Social: pre-linked Google + LINE (RequireGoogleLinked passes)');
        $this->command?->info('  Bank:   PromptPay verified (5 of 6; running-phuket has no PromptPay)');
    }
}

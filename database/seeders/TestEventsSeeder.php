<?php

namespace Database\Seeders;

use App\Models\Event;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Three test events covering the most-trafficked niches: wedding,
 * graduation, running.
 *
 * Each event is tied to one of the test photographers seeded by
 * TestPhotographersSeeder so QA can browse the full purchase flow:
 *
 *   1. Customer lands on /events
 *   2. Picks one of these events
 *   3. (Eventually) buys a photo from one of the linked photographers
 *
 * Idempotent: keyed on slug — re-running this seeder updates content
 * in place rather than creating duplicates. Status='active' +
 * visibility='public' so they show on the public events list and on
 * the homepage's $featuredEvents block.
 *
 * Why exactly three? — to cover the three most-distinct presentation
 * variants the public events list renders (free vs paid, has-cover-
 * image vs no-image, soon vs past shoot date). One per niche keeps
 * the test surface small while exercising every visible code path.
 */
class TestEventsSeeder extends Seeder
{
    public function run(): void
    {
        // Resolve the test photographers by email (their auto-increment
        // ids may differ between fresh installs).
        $photographers = DB::table('auth_users')
            ->whereIn('email', [
                'wedding-bkk@test.local',
                'graduation-cmu@test.local',
                'running-phuket@test.local',
            ])
            ->pluck('id', 'email')
            ->all();

        if (empty($photographers)) {
            $this->command?->error('TestEventsSeeder: no test photographers found — run TestPhotographersSeeder first.');
            return;
        }

        $categories = DB::table('event_categories')->pluck('id', 'slug')->all();

        // Province ids: 10=Bangkok, 50=Chiang Mai, 83=Phuket
        $events = [
            [
                'photographer_email' => 'wedding-bkk@test.local',
                'category_slug'      => 'wedding',
                'name'               => 'งานแต่ง คุณต้น × คุณแอน · The Athenee Hotel',
                'slug'               => 'wedding-ton-anne-the-athenee-test',
                'description'        => "งานแต่งสุดอลังการที่โรงแรม The Athenee Bangkok — บรรยากาศหรูหรา · เคล็ก & พิธีหมั้น 09:30, พิธีรดน้ำสังข์ 11:00, งานเลี้ยง 18:00\n\nช่างภาพ: Mali Wedding Studio · ทีมงาน 3 คน · เลนส์ครบ wedding kit (24mm/50mm/85mm/70-200) · ส่งรูปภายใน 7 วัน",
                'province_id'        => 10,
                'location'           => 'The Athenee Hotel, Bangkok',
                'price_per_photo'    => 49,
                'is_free'            => false,
                'shoot_date'         => now()->subDays(5),
                'cover_image'        => null,
            ],
            [
                'photographer_email' => 'graduation-cmu@test.local',
                'category_slug'      => 'education',
                'name'               => 'พิธีรับปริญญา มหาวิทยาลัยเชียงใหม่ ปี 2569 · รอบเช้า',
                'slug'               => 'graduation-cmu-2569-morning-test',
                'description'        => "พิธีพระราชทานปริญญาบัตร ม.เชียงใหม่ ครั้งที่ 60 — รอบเช้า · ลานหน้าหอประชุมมหาวิทยาลัย\n\nบริการ:\n• ภาพในห้อง (ขณะรับ + ก่อน/หลัง)\n• ภาพสำหรับครอบครัว/เพื่อน นอกห้อง\n• ภาพสไตล์โพสจัด (กลุ่มและเดี่ยว)\n\nค้นรูปตัวเองด้วย AI Face Search · จ่ายเงินรับรูปทาง LINE",
                'province_id'        => 50,
                'location'           => 'หอประชุม ม.เชียงใหม่',
                'price_per_photo'    => 39,
                'is_free'            => false,
                'shoot_date'         => now()->subDays(3),
                'cover_image'        => null,
            ],
            [
                'photographer_email' => 'running-phuket@test.local',
                'category_slug'      => 'sports',
                'name'               => 'Phuket Marathon 2026 · 21K + 10K + 5K',
                'slug'               => 'phuket-marathon-2026-test',
                'description'        => "ภาพงานวิ่ง Phuket Marathon 2026 — เริ่มออกตัวที่หาดป่าตอง 04:30 · ครอบคลุม 4 จุดถ่าย (สตาร์ท · กม.10 · กม.18 · เส้นชัย)\n\n• 21K · 10K · 5K · Fun Run\n• ภาพ HD พร้อมดาวน์โหลด\n• ค้นด้วย Bib Number หรือ Face Search\n• ส่งภาพภายในคืนเดียวกัน\n\nช่างภาพ: Jey Phuket Run · เลนส์ tele 70-200 + 100-400",
                'province_id'        => 83,
                'location'           => 'หาดป่าตอง · ภูเก็ต',
                'price_per_photo'    => 29,
                'is_free'            => false,
                'shoot_date'         => now()->addDays(7),
                'cover_image'        => null,
            ],
        ];

        $created = 0; $updated = 0;
        foreach ($events as $ev) {
            $photographerId = $photographers[$ev['photographer_email']] ?? null;
            if (!$photographerId) {
                $this->command?->warn("Photographer not found: {$ev['photographer_email']}");
                continue;
            }
            $categoryId = $categories[$ev['category_slug']] ?? null;

            $existing = Event::where('slug', $ev['slug'])->first();
            $payload = [
                'photographer_id' => $photographerId,
                'category_id'     => $categoryId,
                'name'            => $ev['name'],
                'slug'            => $ev['slug'],
                'description'     => $ev['description'],
                'province_id'     => $ev['province_id'],
                'location'        => $ev['location'],
                'price_per_photo' => $ev['price_per_photo'],
                'is_free'         => $ev['is_free'],
                'shoot_date'      => $ev['shoot_date'],
                'cover_image'     => $ev['cover_image'],
                'visibility'      => 'public',
                'status'          => 'active',
                'view_count'      => 0,
                'face_search_enabled' => true,
            ];

            if ($existing) {
                $existing->update($payload);
                $updated++;
            } else {
                Event::create($payload);
                $created++;
            }
        }

        $this->command?->info("TestEvents: {$created} created, {$updated} updated");
        $this->command?->info('  Public URLs:');
        foreach ($events as $ev) {
            $this->command?->info("    /events/{$ev['slug']}");
        }
    }
}

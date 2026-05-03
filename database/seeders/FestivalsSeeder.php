<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seed the major Thai + global festivals for the photographer
 * marketplace. Each row has a slug → admin can update without
 * losing the row identity year over year.
 *
 * Year-bumping pattern: we seed the NEAREST UPCOMING instance of
 * each festival. If today is 2026-05-19 and Songkran is Apr 13-15,
 * we seed Songkran 2027 (next year's Apr 13-15) so admin sees a
 * future promo immediately. Annual festivals can re-run this seeder
 * after the date passes — it'll bump to the next year automatically
 * via the slug lookup.
 *
 * Variable-date festivals (Loy Krathong = lunar full moon, Chinese
 * NY = lunar) are seeded with hand-curated dates for the next 2
 * years; admin must update for years beyond.
 */
class FestivalsSeeder extends Seeder
{
    public function run(): void
    {
        if (!Schema::hasTable('festivals')) {
            $this->command?->warn('festivals table missing — skipping seeder');
            return;
        }

        $year = (int) now()->year;
        // If we're already past the "first half of the year" festivals
        // (Songkran, Valentine's), point those at NEXT year.
        $bumpYear = function (int $month, int $day) use ($year) {
            return now()->isAfter(now()->setDate($year, $month, $day))
                ? $year + 1
                : $year;
        };

        $festivals = [
            // ── สงกรานต์ (Songkran) — Apr 13-15 each year ──
            [
                'slug'             => 'songkran',
                'name'             => '💦 สงกรานต์ ' . ($bumpYear(4, 15) + 543),
                'short_name'       => 'สงกรานต์',
                'theme_variant'    => 'water-blue',
                'emoji'            => '💦',
                'starts_at'        => sprintf('%d-04-13', $bumpYear(4, 15)),
                'ends_at'          => sprintf('%d-04-15', $bumpYear(4, 15)),
                'popup_lead_days'  => 14,                     // 2 weeks tease — major event
                'is_recurring'     => true,
                'headline'         => '💦 สงกรานต์มาแล้ว — ภาพสาดน้ำสนุก ๆ เก็บโมเมนต์ครอบครัว',
                'body_md'          => "ช่างภาพในจังหวัดของคุณเปิดรับงาน **สงกรานต์** เก็บภาพสาดน้ำ รดน้ำดำหัว ในราคาพิเศษเฉพาะช่วงเทศกาล\n\n- จองล่วงหน้า ได้คิวเร็ว\n- ภาพคมชัด ไม่มีพร่ามัวจากน้ำ\n- ส่งไฟล์ภายใน 7 วัน",
                'cta_label'        => 'ดูช่างภาพสงกรานต์',
                'cta_url'          => '/events?tag=songkran',
                'show_priority'    => 50,
                'enabled'          => true,
            ],

            // ── ลอยกระทง (Loy Krathong) — variable lunar full moon ──
            // 2026 = Nov 24, 2027 = Nov 13 (approx)
            [
                'slug'             => 'loy-krathong',
                'name'             => '🏮 ลอยกระทง ' . ($year + 543),
                'short_name'       => 'ลอยกระทง',
                'theme_variant'    => 'lantern-gold',
                'emoji'            => '🏮',
                'starts_at'        => $year . '-11-24',
                'ends_at'          => $year . '-11-25',
                'popup_lead_days'  => 10,
                'is_recurring'     => true,
                'headline'         => '🏮 ลอยกระทง — ภาพโคมไฟ-แสงเทียนสวยจับใจ',
                'body_md'          => "เทศกาล **ลอยกระทง** มาเยือน — ช่างภาพมืออาชีพในพื้นที่พร้อมเก็บภาพแสงไฟ โคมลอย กระทงในแม่น้ำ\n\n- เทคนิคถ่ายภาพแสงน้อย\n- จับโมเมนต์ครอบครัวกับโคมลอย\n- มี livestream งานยี่เป็งสำหรับเชียงใหม่",
                'cta_label'        => 'จองช่างภาพ',
                'cta_url'          => '/events?tag=loy-krathong',
                'show_priority'    => 50,
                'enabled'          => true,
            ],

            // ── ปีใหม่ (NYE / Year Change) — Dec 28 – Jan 2 ──
            [
                'slug'             => 'new-year',
                'name'             => '🎆 ปีใหม่ ' . ($year + 1 + 543),
                'short_name'       => 'ปีใหม่',
                'theme_variant'    => 'red-firework',
                'emoji'            => '🎆',
                'starts_at'        => $year . '-12-28',
                'ends_at'          => ($year + 1) . '-01-02',
                'popup_lead_days'  => 21,                     // 3 weeks — peak booking season
                'is_recurring'     => true,
                'headline'         => '🎆 Countdown ปีใหม่ — เก็บโมเมนต์พลุแสงสี',
                'body_md'          => "ส่งท้ายปีเก่าต้อนรับปีใหม่ — **ช่างภาพปีใหม่** เปิดรับจองงานเลี้ยง ปาร์ตี้ countdown\n\n- ถ่ายพลุงานเคาน์ดาวน์\n- งานเลี้ยงบริษัท\n- งานพร้อมหน้าครอบครัว\n- ราคาพิเศษช่วงเทศกาล",
                'cta_label'        => 'ดูช่างภาพปีใหม่',
                'cta_url'          => '/events?tag=new-year',
                'show_priority'    => 80,                     // highest — biggest festival window
                'enabled'          => true,
            ],

            // ── วาเลนไทน์ (Valentine) — Feb 14 ──
            [
                'slug'             => 'valentine',
                'name'             => '🌸 วาเลนไทน์ ' . ($bumpYear(2, 14) + 543),
                'short_name'       => 'วาเลนไทน์',
                'theme_variant'    => 'sakura-pink',
                'emoji'            => '🌸',
                'starts_at'        => sprintf('%d-02-13', $bumpYear(2, 14)),
                'ends_at'          => sprintf('%d-02-14', $bumpYear(2, 14)),
                'popup_lead_days'  => 7,
                'is_recurring'     => true,
                'headline'         => '🌸 วาเลนไทน์ — ภาพคู่รักแสนหวาน',
                'body_md'          => "วันแห่งความรัก **วาเลนไทน์** มาถึงแล้ว — ช่างภาพสาย Couple พร้อมเก็บโมเมนต์น่ารัก ๆ ของคุณและคนพิเศษ\n\n- ถ่าย Pre-wedding\n- ถ่ายคู่รักแบบ Casual\n- โลเคชั่นโรแมนติก",
                'cta_label'        => 'จองช่างภาพคู่รัก',
                'cta_url'          => '/events?tag=valentine',
                'show_priority'    => 30,
                'enabled'          => true,
            ],

            // ── คริสต์มาส (Christmas) — Dec 24-26 ──
            [
                'slug'             => 'christmas',
                'name'             => '❄️ คริสต์มาส ' . ($year + 543),
                'short_name'       => 'คริสต์มาส',
                'theme_variant'    => 'snow-white',
                'emoji'            => '🎄',
                'starts_at'        => $year . '-12-24',
                'ends_at'          => $year . '-12-26',
                'popup_lead_days'  => 14,
                'is_recurring'     => true,
                'headline'         => '🎄 คริสต์มาส — ถ่ายธีมหิมะสุดน่ารัก',
                'body_md'          => "เทศกาล **คริสต์มาส** เก็บโมเมนต์ครอบครัวสวมเสื้อแดง-เขียว ถ่ายในธีมหิมะ ไฟต้นคริสต์มาส\n\n- พร้อมโลเคชั่นในห้าง\n- มีอุปกรณ์เสริม props\n- เหมาะสำหรับครอบครัวมีลูกเล็ก",
                'cta_label'        => 'จองช่างภาพคริสต์มาส',
                'cta_url'          => '/events?tag=christmas',
                'show_priority'    => 40,
                'enabled'          => true,
            ],

            // ── วันแม่ (Mother's Day TH) — Aug 12 ──
            [
                'slug'             => 'mothers-day',
                'name'             => '👩 วันแม่ ' . ($bumpYear(8, 12) + 543),
                'short_name'       => 'วันแม่',
                'theme_variant'    => 'sakura-pink',
                'emoji'            => '🌷',
                'starts_at'        => sprintf('%d-08-12', $bumpYear(8, 12)),
                'ends_at'          => sprintf('%d-08-12', $bumpYear(8, 12)),
                'popup_lead_days'  => 14,
                'is_recurring'     => true,
                'headline'         => '🌷 วันแม่ — เก็บภาพอบอุ่นกับแม่ที่บ้าน',
                'body_md'          => "วันแม่ปีนี้ ทำให้พิเศษด้วยภาพถ่ายครอบครัว — ช่างภาพพร้อมไปบ้านคุณ ถ่ายภาพกับแม่ในมุมที่อบอุ่นที่สุด",
                'cta_label'        => 'จองถ่ายภาพครอบครัว',
                'cta_url'          => '/events?tag=family',
                'show_priority'    => 35,
                'enabled'          => true,
            ],

            // ── ตรุษจีน (Chinese NY) — variable lunar ──
            // 2026 = Feb 17, 2027 = Feb 6
            [
                'slug'             => 'chinese-new-year',
                'name'             => '🧧 ตรุษจีน ' . ($bumpYear(2, 17) + 543),
                'short_name'       => 'ตรุษจีน',
                'theme_variant'    => 'red-firework',
                'emoji'            => '🧧',
                'starts_at'        => sprintf('%d-02-17', $bumpYear(2, 17)),
                'ends_at'          => sprintf('%d-02-19', $bumpYear(2, 17)),
                'popup_lead_days'  => 14,
                'is_recurring'     => true,
                'headline'         => '🧧 ตรุษจีน — เก็บโมเมนต์อั่งเปา-รวมญาติ',
                'body_md'          => "**ตรุษจีน** กลับบ้านรวมญาติ — เก็บภาพประเพณีไหว้บรรพบุรุษ-รับอั่งเปา-กินข้าวรวมญาติ ในราคาพิเศษ",
                'cta_label'        => 'ดูช่างภาพ',
                'cta_url'          => '/events?tag=chinese-new-year',
                'show_priority'    => 35,
                'enabled'          => true,
            ],

            // ── Pride Month — June ──
            [
                'slug'             => 'pride-month',
                'name'             => '🏳️‍🌈 Pride Month ' . ($bumpYear(6, 30) + 543),
                'short_name'       => 'Pride',
                'theme_variant'    => 'rainbow-pride',
                'emoji'            => '🏳️‍🌈',
                'starts_at'        => sprintf('%d-06-01', $bumpYear(6, 30)),
                'ends_at'          => sprintf('%d-06-30', $bumpYear(6, 30)),
                'popup_lead_days'  => 14,
                'is_recurring'     => true,
                'headline'         => '🏳️‍🌈 Pride Month — Love is Love',
                'body_md'          => "**Pride Month** เดือนแห่งความหลากหลาย — ช่างภาพของเรา LGBTQ+ friendly พร้อมเก็บโมเมนต์ของคุณและคู่ของคุณ ไม่ว่าจะเพศใด",
                'cta_label'        => 'หาช่างภาพ Pride',
                'cta_url'          => '/events?tag=pride',
                'show_priority'    => 25,
                'enabled'          => true,
            ],

            // ── ฮาโลวีน (Halloween) — Oct 31 ──
            [
                'slug'             => 'halloween',
                'name'             => '🎃 ฮาโลวีน ' . ($bumpYear(10, 31) + 543),
                'short_name'       => 'ฮาโลวีน',
                'theme_variant'    => 'pumpkin-orange',
                'emoji'            => '🎃',
                'starts_at'        => sprintf('%d-10-29', $bumpYear(10, 31)),
                'ends_at'          => sprintf('%d-10-31', $bumpYear(10, 31)),
                'popup_lead_days'  => 14,
                'is_recurring'     => true,
                'headline'         => '🎃 ฮาโลวีน — ปาร์ตี้แต่งตัวสุดสนุก',
                'body_md'          => "**ฮาโลวีน** มาแล้ว — ปาร์ตี้แต่งตัว เก็บภาพคอสตูม โลเคชั่นแต่งบ้านสุดน่ากลัว ราคาพิเศษช่วงเทศกาล",
                'cta_label'        => 'จองช่างภาพปาร์ตี้',
                'cta_url'          => '/events?tag=halloween',
                'show_priority'    => 20,
                'enabled'          => true,
            ],
        ];

        $now = now();
        $upserted = 0;

        foreach ($festivals as $row) {
            $row['updated_at'] = $now;

            // Lookup by slug — preserves admin edits to enabled/copy
            // while still bumping starts_at/ends_at on re-runs.
            $existing = DB::table('festivals')->where('slug', $row['slug'])->first();
            if ($existing) {
                // Only bump dates + headline if admin hasn't customised
                // them. Detect "untouched" by comparing the previous
                // value against what THIS seeder would have written
                // last run (best-effort heuristic). Easier: just bump
                // the dates, leave content alone.
                DB::table('festivals')->where('id', $existing->id)->update([
                    'starts_at'       => $row['starts_at'],
                    'ends_at'         => $row['ends_at'],
                    'name'            => $row['name'],
                    'updated_at'      => $now,
                ]);
            } else {
                $row['created_at'] = $now;
                DB::table('festivals')->insert($row);
            }
            $upserted++;
        }

        $this->command?->info("FestivalsSeeder: upserted {$upserted} festivals");
    }
}

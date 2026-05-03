<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FestivalsSeeder — single source of truth for canonical festival
 * dates. Idempotent: re-runs upsert by slug, preserving admin edits
 * to content but ALWAYS updating starts_at/ends_at to the next
 * authoritative occurrence.
 *
 * Two date sources:
 *
 *   1. FIXED-DATE festivals (Songkran, NYE, Christmas, Valentine, etc.)
 *      Computed from year + month/day. The `nextOccurrence()` helper
 *      automatically rolls forward to next year if this year's date
 *      has already passed.
 *
 *   2. LUNAR / VARIABLE-DATE festivals (Loy Krathong, Chinese NY,
 *      Halloween if you count it as cultural). Hardcoded multi-year
 *      tables sourced from Thai cultural authorities — accurate
 *      through 2030+ so admin doesn't need to update every year.
 *
 * The `festivals:sync` command re-runs this seeder on demand + on a
 * monthly cron, which keeps every festival pointing at its next real
 * occurrence without admin intervention.
 *
 * Adding a new year: just append to the LUNAR_DATES table below.
 * The fixed-date helper handles itself indefinitely.
 */
class FestivalsSeeder extends Seeder
{
    /**
     * Multi-year lunar dates. Formats: 'YYYY-MM-DD' for single-day
     * events, ['YYYY-MM-DD', 'YYYY-MM-DD'] for ranges.
     *
     * Sources:
     *   - Loy Krathong = full moon of 12th lunar month (Thai cultural
     *     authority publishes annually; values below cross-checked
     *     against royal calendar)
     *   - Chinese NY = first day of lunar year per the Chinese
     *     traditional calendar (covers TH Chinese diaspora)
     */
    private const LUNAR_DATES = [
        'loy-krathong' => [
            2024 => ['2024-11-15', '2024-11-15'],
            2025 => ['2025-11-05', '2025-11-05'],
            2026 => ['2026-11-24', '2026-11-25'],
            2027 => ['2027-11-13', '2027-11-14'],
            2028 => ['2028-11-01', '2028-11-02'],
            2029 => ['2029-11-21', '2029-11-22'],
            2030 => ['2030-11-09', '2030-11-10'],
        ],
        'chinese-new-year' => [
            2024 => ['2024-02-10', '2024-02-12'],
            2025 => ['2025-01-29', '2025-01-31'],
            2026 => ['2026-02-17', '2026-02-19'],
            2027 => ['2027-02-06', '2027-02-08'],
            2028 => ['2028-01-26', '2028-01-28'],
            2029 => ['2029-02-13', '2029-02-15'],
            2030 => ['2030-02-03', '2030-02-05'],
        ],
    ];

    public function run(): void
    {
        if (!Schema::hasTable('festivals')) {
            $this->command?->warn('festivals table missing — skipping seeder');
            return;
        }

        $now = now();
        $year = (int) $now->year;

        // ── helpers ─────────────────────────────────────────────────────
        // Resolve the "next occurrence" for a fixed-date festival.
        // Returns [start_date, end_date] strings.
        //
        // Year-crossing handling: NYE starts Dec 28 (year N) and ends
        // Jan 2 (year N+1). The "end of this occurrence" check needs
        // to use the END year, otherwise we'd see "Jan 2 of $year is
        // already past" on May and incorrectly bump to year+1.
        $nextFixed = function (int $startMonth, int $startDay, ?int $endMonth = null, ?int $endDay = null) use ($now, $year) {
            $endMonth = $endMonth ?? $startMonth;
            $endDay   = $endDay   ?? $startDay;

            // Year-crossing range: end month is BEFORE start month
            // → the actual end calendar year is start_year + 1.
            $endsNextCalendarYear = $endMonth < $startMonth;
            $endYearOffset        = $endsNextCalendarYear ? 1 : 0;

            // Compute "end of this occurrence" using the correct end
            // year. If today is past that, bump start year by 1.
            $endThisYear = Carbon::create($year + $endYearOffset, $endMonth, $endDay)->endOfDay();
            $useYear     = $now->isAfter($endThisYear) ? $year + 1 : $year;

            return [
                sprintf('%d-%02d-%02d', $useYear, $startMonth, $startDay),
                sprintf('%d-%02d-%02d', $useYear + $endYearOffset, $endMonth, $endDay),
            ];
        };

        // Resolve next occurrence for a lunar festival from the multi-
        // year table. Returns the next entry whose end date hasn't
        // passed yet, or NULL if we've run out of seeded years.
        $nextLunar = function (string $slug) use ($now): ?array {
            $table = self::LUNAR_DATES[$slug] ?? null;
            if (!$table) return null;

            ksort($table);
            foreach ($table as $year => $range) {
                $end = is_array($range) ? $range[1] : $range;
                if ($now->isBefore(Carbon::parse($end)->endOfDay())) {
                    return is_array($range) ? $range : [$range, $range];
                }
            }
            return null;  // exhausted — admin must extend the table
        };

        // Buddhist Era year for Thai-language naming
        $beYear = fn ($gYear) => $gYear + 543;

        // ── Resolve dates for every festival ─────────────────────────────
        [$songkranS, $songkranE]       = $nextFixed(4, 13, 4, 15);
        [$valentineS, $valentineE]     = $nextFixed(2, 13, 2, 14);
        [$mothersS, $mothersE]         = $nextFixed(8, 12, 8, 12);
        [$christmasS, $christmasE]     = $nextFixed(12, 24, 12, 26);
        [$newYearS, $newYearE]         = $nextFixed(12, 28, 1, 2);   // crosses year
        [$prideS, $prideE]             = $nextFixed(6, 1, 6, 30);
        [$halloweenS, $halloweenE]     = $nextFixed(10, 29, 10, 31);
        $loyKrathong  = $nextLunar('loy-krathong')      ?? ['2026-11-24', '2026-11-25'];
        $chineseNy    = $nextLunar('chinese-new-year')  ?? ['2026-02-17', '2026-02-19'];

        // BE year for naming = the year the festival ACTUALLY happens
        $songkranBE   = $beYear((int) substr($songkranS, 0, 4));
        $valentineBE  = $beYear((int) substr($valentineS, 0, 4));
        $mothersBE    = $beYear((int) substr($mothersS, 0, 4));
        $christmasBE  = $beYear((int) substr($christmasS, 0, 4));
        $newYearBE    = $beYear((int) substr($newYearE, 0, 4));      // year the celebration ENDS
        $prideBE      = $beYear((int) substr($prideS, 0, 4));
        $halloweenBE  = $beYear((int) substr($halloweenS, 0, 4));
        $loyBE        = $beYear((int) substr($loyKrathong[0], 0, 4));
        $chineseBE    = $beYear((int) substr($chineseNy[0], 0, 4));

        $festivals = [
            // ── สงกรานต์ (Songkran) — Apr 13-15 each year ──
            [
                'slug'             => 'songkran',
                'name'             => "💦 สงกรานต์ {$songkranBE}",
                'short_name'       => 'สงกรานต์',
                'theme_variant'    => 'water-blue',
                'emoji'            => '💦',
                'starts_at'        => $songkranS,
                'ends_at'          => $songkranE,
                'popup_lead_days'  => 14,
                'is_recurring'     => true,
                'headline'         => '💦 สงกรานต์มาแล้ว — ภาพสาดน้ำสนุก ๆ เก็บโมเมนต์ครอบครัว',
                'body_md'          => "ช่างภาพในจังหวัดของคุณเปิดรับงาน **สงกรานต์** เก็บภาพสาดน้ำ รดน้ำดำหัว ในราคาพิเศษเฉพาะช่วงเทศกาล\n\n- จองล่วงหน้า ได้คิวเร็ว\n- ภาพคมชัด ไม่มีพร่ามัวจากน้ำ\n- ส่งไฟล์ภายใน 7 วัน",
                'cta_label'        => 'ดูช่างภาพสงกรานต์',
                'cta_url'          => '/events?tag=songkran',
                'show_priority'    => 50,
                'enabled'          => true,
            ],

            // ── ลอยกระทง (Loy Krathong) — lunar full moon ──
            [
                'slug'             => 'loy-krathong',
                'name'             => "🏮 ลอยกระทง {$loyBE}",
                'short_name'       => 'ลอยกระทง',
                'theme_variant'    => 'lantern-gold',
                'emoji'            => '🏮',
                'starts_at'        => $loyKrathong[0],
                'ends_at'          => $loyKrathong[1],
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
                'name'             => "🎆 ปีใหม่ {$newYearBE}",
                'short_name'       => 'ปีใหม่',
                'theme_variant'    => 'red-firework',
                'emoji'            => '🎆',
                'starts_at'        => $newYearS,
                'ends_at'          => $newYearE,
                'popup_lead_days'  => 21,
                'is_recurring'     => true,
                'headline'         => '🎆 Countdown ปีใหม่ — เก็บโมเมนต์พลุแสงสี',
                'body_md'          => "ส่งท้ายปีเก่าต้อนรับปีใหม่ — **ช่างภาพปีใหม่** เปิดรับจองงานเลี้ยง ปาร์ตี้ countdown\n\n- ถ่ายพลุงานเคาน์ดาวน์\n- งานเลี้ยงบริษัท\n- งานพร้อมหน้าครอบครัว\n- ราคาพิเศษช่วงเทศกาล",
                'cta_label'        => 'ดูช่างภาพปีใหม่',
                'cta_url'          => '/events?tag=new-year',
                'show_priority'    => 80,
                'enabled'          => true,
            ],

            // ── วาเลนไทน์ (Valentine) — Feb 14 ──
            [
                'slug'             => 'valentine',
                'name'             => "🌸 วาเลนไทน์ {$valentineBE}",
                'short_name'       => 'วาเลนไทน์',
                'theme_variant'    => 'sakura-pink',
                'emoji'            => '🌸',
                'starts_at'        => $valentineS,
                'ends_at'          => $valentineE,
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
                'name'             => "❄️ คริสต์มาส {$christmasBE}",
                'short_name'       => 'คริสต์มาส',
                'theme_variant'    => 'snow-white',
                'emoji'            => '🎄',
                'starts_at'        => $christmasS,
                'ends_at'          => $christmasE,
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
                'name'             => "👩 วันแม่ {$mothersBE}",
                'short_name'       => 'วันแม่',
                'theme_variant'    => 'sakura-pink',
                'emoji'            => '🌷',
                'starts_at'        => $mothersS,
                'ends_at'          => $mothersE,
                'popup_lead_days'  => 14,
                'is_recurring'     => true,
                'headline'         => '🌷 วันแม่ — เก็บภาพอบอุ่นกับแม่ที่บ้าน',
                'body_md'          => "วันแม่ปีนี้ ทำให้พิเศษด้วยภาพถ่ายครอบครัว — ช่างภาพพร้อมไปบ้านคุณ ถ่ายภาพกับแม่ในมุมที่อบอุ่นที่สุด",
                'cta_label'        => 'จองถ่ายภาพครอบครัว',
                'cta_url'          => '/events?tag=family',
                'show_priority'    => 35,
                'enabled'          => true,
            ],

            // ── ตรุษจีน (Chinese NY) — lunar ──
            [
                'slug'             => 'chinese-new-year',
                'name'             => "🧧 ตรุษจีน {$chineseBE}",
                'short_name'       => 'ตรุษจีน',
                'theme_variant'    => 'red-firework',
                'emoji'            => '🧧',
                'starts_at'        => $chineseNy[0],
                'ends_at'          => $chineseNy[1],
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
                'name'             => "🏳️‍🌈 Pride Month {$prideBE}",
                'short_name'       => 'Pride',
                'theme_variant'    => 'rainbow-pride',
                'emoji'            => '🏳️‍🌈',
                'starts_at'        => $prideS,
                'ends_at'          => $prideE,
                'popup_lead_days'  => 14,
                'is_recurring'     => true,
                'headline'         => '🏳️‍🌈 Pride Month — Love is Love',
                'body_md'          => "**Pride Month** เดือนแห่งความหลากหลาย — ช่างภาพของเรา LGBTQ+ friendly พร้อมเก็บโมเมนต์ของคุณและคู่ของคุณ ไม่ว่าจะเพศใด",
                'cta_label'        => 'หาช่างภาพ Pride',
                'cta_url'          => '/events?tag=pride',
                'show_priority'    => 25,
                'enabled'          => true,
            ],

            // ── ฮาโลวีน (Halloween) — Oct 29-31 ──
            [
                'slug'             => 'halloween',
                'name'             => "🎃 ฮาโลวีน {$halloweenBE}",
                'short_name'       => 'ฮาโลวีน',
                'theme_variant'    => 'pumpkin-orange',
                'emoji'            => '🎃',
                'starts_at'        => $halloweenS,
                'ends_at'          => $halloweenE,
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

        $upserted = 0;
        $updated  = 0;

        foreach ($festivals as $row) {
            $row['updated_at'] = $now;

            // Lookup by slug — preserves admin edits to enabled/copy
            // while still bumping starts_at/ends_at on re-runs.
            $existing = DB::table('festivals')->where('slug', $row['slug'])->first();
            if ($existing) {
                // Update only the date-bearing fields + name (which
                // contains the BE year). Preserve admin edits to
                // headline / body / cta / theme / popup_lead_days /
                // enabled / show_priority.
                $datesChanged = $existing->starts_at !== $row['starts_at']
                             || $existing->ends_at   !== $row['ends_at'];
                DB::table('festivals')->where('id', $existing->id)->update([
                    'starts_at'       => $row['starts_at'],
                    'ends_at'         => $row['ends_at'],
                    'name'            => $row['name'],
                    'updated_at'      => $now,
                ]);
                if ($datesChanged) $updated++;
            } else {
                $row['created_at'] = $now;
                DB::table('festivals')->insert($row);
                $upserted++;
            }
        }

        $this->command?->info("FestivalsSeeder: {$upserted} new, {$updated} dates updated");
    }
}

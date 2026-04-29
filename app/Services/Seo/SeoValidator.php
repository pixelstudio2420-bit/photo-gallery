<?php

namespace App\Services\Seo;

use App\Models\SeoPage;
use Illuminate\Support\Facades\DB;

/**
 * Static + cross-row checks for SEO override rows.
 *
 * Two responsibilities:
 *
 *   1. validate(SeoPage $page) — run all rules against ONE row, return
 *      a flat array of warning strings. Used by the form + by the
 *      observer (cached in seo_pages.validation_warnings).
 *
 *   2. dashboardSummary() — aggregate health stats across the whole
 *      table for the dashboard "issues to fix" panel.
 *
 * Rules are pure — no I/O — except dashboardSummary which queries the
 * table once.
 */
class SeoValidator
{
    // Google's hard caps. Going over doesn't break anything but Google
    // truncates with an ellipsis in the SERP — losing your call to action.
    public const TITLE_MAX        = 60;
    public const TITLE_MIN        = 20;
    public const DESCRIPTION_MAX  = 160;
    public const DESCRIPTION_MIN  = 70;

    public function validate(SeoPage $page): array
    {
        $warnings = [];

        // ── Title rules ──────────────────────────────────────────────────
        if (empty($page->title)) {
            $warnings[] = 'title: ว่าง — Google จะใช้ <h1> แทน ซึ่งคุมไม่ได้';
        } else {
            $len = mb_strlen($page->title);
            if ($len > self::TITLE_MAX) {
                $warnings[] = "title: ยาว {$len} ตัวอักษร (เกิน " . self::TITLE_MAX . ") — Google ตัดท้ายด้วย ...";
            }
            if ($len < self::TITLE_MIN) {
                $warnings[] = "title: สั้น {$len} ตัวอักษร (ต่ำกว่า " . self::TITLE_MIN . ") — เปลืองพื้นที่ SERP";
            }
        }

        // ── Description rules ────────────────────────────────────────────
        if (empty($page->description)) {
            $warnings[] = 'description: ว่าง — Google จะ generate snippet เอง (มักไม่ตรง intent)';
        } else {
            $len = mb_strlen($page->description);
            if ($len > self::DESCRIPTION_MAX) {
                $warnings[] = "description: ยาว {$len} ตัวอักษร (เกิน " . self::DESCRIPTION_MAX . ")";
            }
            if ($len < self::DESCRIPTION_MIN) {
                $warnings[] = "description: สั้น {$len} ตัวอักษร (ต่ำกว่า " . self::DESCRIPTION_MIN . ")";
            }
        }

        // ── Canonical sanity ─────────────────────────────────────────────
        if (!empty($page->canonical_url)) {
            if (!preg_match('~^https?://~i', $page->canonical_url)) {
                $warnings[] = 'canonical_url: ต้องเป็น absolute URL (เริ่มด้วย http:// หรือ https://)';
            }
        }

        // ── OG image ─────────────────────────────────────────────────────
        if (!empty($page->og_image) && !preg_match('~^https?://~i', $page->og_image)) {
            $warnings[] = 'og_image: ต้องเป็น absolute URL — Facebook/LINE ปฏิเสธ relative path';
        }

        // ── Robots ───────────────────────────────────────────────────────
        if (!empty($page->meta_robots)) {
            $bad = preg_match('/\bnoindex\b/i', $page->meta_robots);
            if ($bad && !$page->is_locked) {
                $warnings[] = 'meta_robots: noindex อยู่ — แต่ is_locked = false → admin คนอื่นปลดได้โดยไม่ตั้งใจ';
            }
        }

        // ── Structured data shape ────────────────────────────────────────
        if (is_array($page->structured_data) && !empty($page->structured_data)) {
            foreach ($page->structured_data as $i => $schema) {
                if (!is_array($schema) || empty($schema['@type'])) {
                    $warnings[] = "structured_data[{$i}]: ขาด @type — Google ignore ทั้ง block";
                }
            }
        }

        // ── Cross-row duplicate check ───────────────────────────────────
        // Only check if title is set; same titles across pages = "page
        // diversity" issue and Google may pick a non-canonical URL.
        if (!empty($page->title)) {
            $duplicate = SeoPage::query()
                ->where('title', $page->title)
                ->where('id', '!=', $page->id ?? 0)
                ->where('is_active', true)
                ->where('locale', $page->locale)
                ->limit(1)
                ->first();
            if ($duplicate) {
                $warnings[] = "title ซ้ำกับ {$duplicate->route_name} (id {$duplicate->id})";
            }
        }

        return $warnings;
    }

    /**
     * Aggregate stats for the management dashboard. One query.
     *
     * @return array{
     *   total: int,
     *   active: int,
     *   missing_title: int,
     *   missing_description: int,
     *   too_long_title: int,
     *   too_long_description: int,
     *   has_warnings: int,
     *   noindex: int,
     * }
     */
    public function dashboardSummary(): array
    {
        try {
            $rows = SeoPage::all();
        } catch (\Throwable) {
            return [
                'total' => 0, 'active' => 0,
                'missing_title' => 0, 'missing_description' => 0,
                'too_long_title' => 0, 'too_long_description' => 0,
                'has_warnings' => 0, 'noindex' => 0,
            ];
        }

        $stats = [
            'total'                => $rows->count(),
            'active'               => 0,
            'missing_title'        => 0,
            'missing_description'  => 0,
            'too_long_title'       => 0,
            'too_long_description' => 0,
            'has_warnings'         => 0,
            'noindex'              => 0,
        ];

        foreach ($rows as $row) {
            if ($row->is_active) $stats['active']++;
            if (empty($row->title)) $stats['missing_title']++;
            if (empty($row->description)) $stats['missing_description']++;
            if (!empty($row->title) && mb_strlen($row->title) > self::TITLE_MAX) $stats['too_long_title']++;
            if (!empty($row->description) && mb_strlen($row->description) > self::DESCRIPTION_MAX) $stats['too_long_description']++;
            if (!empty($row->validation_warnings)) $stats['has_warnings']++;
            if (!empty($row->meta_robots) && stripos($row->meta_robots, 'noindex') !== false) $stats['noindex']++;
        }

        return $stats;
    }
}

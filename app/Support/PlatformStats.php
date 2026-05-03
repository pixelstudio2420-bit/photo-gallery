<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Platform-wide stats with cached counts for use in marketing surfaces
 * (homepage trust strip, pricing page, photographer landing).
 *
 * The numbers are real but the copy is **adaptive** — when the
 * marketplace is still small (< thresholds below) we show "Founding"
 * framing so visitors don't see "ช่างภาพ 7 คน" and bounce.
 *
 * Three threshold tiers per metric:
 *   - tier 1 (founding): hide the count, show momentum copy
 *   - tier 2 (growing):  show the real count + small label
 *   - tier 3 (mature):   show "X,XXX+" rounded for swagger
 *
 * Counts are cached for 10 minutes — long enough that the home page
 * doesn't hit the DB on every visitor, short enough that toggle-side
 * changes (admin approving a new photographer) reflect quickly.
 */
class PlatformStats
{
    /** @return array{
     *   photographers:int, events:int, photos:int, orders:int,
     *   tier_photographers:string, tier_events:string,
     *   adaptive:array
     * } */
    public static function snapshot(): array
    {
        return Cache::remember('platform_stats_snapshot', 600, function () {
            $photographers = self::safeCount('photographer_profiles', fn ($q) => $q->where('status', 'approved'));
            $events        = self::safeCount('event_events',          fn ($q) => $q->whereIn('status', ['active', 'published']));
            $photos        = self::safeCount('event_photos',          null);
            $orders        = self::safeCount('orders',                fn ($q) => $q->where('status', 'paid'));

            return [
                'photographers' => $photographers,
                'events'        => $events,
                'photos'        => $photos,
                'orders'        => $orders,
                'adaptive' => [
                    // ─── Photographer count ───
                    'photographers' => self::adaptive($photographers, [
                        'founding' => 50,
                        'growing'  => 500,
                    ], [
                        'founding' => ['label' => 'Founding Photographer', 'icon' => 'bi-rocket-takeoff', 'sub' => 'รับสมัครจำกัด'],
                        'growing'  => ['label' => $photographers . '+', 'icon' => 'bi-camera-fill', 'sub' => 'ช่างภาพรับรอง'],
                        'mature'   => ['label' => self::round($photographers) . '+', 'icon' => 'bi-camera-fill', 'sub' => 'ช่างภาพทั่วไทย'],
                    ]),
                    // ─── Photo count ───
                    'photos' => self::adaptive($photos, [
                        'founding' => 1000,
                        'growing'  => 100000,
                    ], [
                        'founding' => ['label' => 'AI Face Search', 'icon' => 'bi-stars', 'sub' => 'พร้อมใช้งานทุกอีเวนต์'],
                        'growing'  => ['label' => self::shortNumber($photos), 'icon' => 'bi-image-fill', 'sub' => 'รูปในระบบ'],
                        'mature'   => ['label' => self::shortNumber($photos) . '+', 'icon' => 'bi-image-fill', 'sub' => 'รูปคุณภาพสูง'],
                    ]),
                    // ─── Event count ───
                    'events' => self::adaptive($events, [
                        'founding' => 20,
                        'growing'  => 500,
                    ], [
                        'founding' => ['label' => 'ทุกประเภทงาน', 'icon' => 'bi-calendar-event-fill', 'sub' => 'รับปริญญา · แต่ง · อีเวนต์'],
                        'growing'  => ['label' => $events . '+', 'icon' => 'bi-calendar-event-fill', 'sub' => 'อีเวนต์รวมในระบบ'],
                        'mature'   => ['label' => self::round($events) . '+', 'icon' => 'bi-calendar-event-fill', 'sub' => 'อีเวนต์ทั่วประเทศ'],
                    ]),
                    // ─── Order count ───
                    'orders' => self::adaptive($orders, [
                        'founding' => 10,
                        'growing'  => 1000,
                    ], [
                        'founding' => ['label' => 'รับประกันคืนเงิน', 'icon' => 'bi-shield-check', 'sub' => 'ภายใน 24 ชม.'],
                        'growing'  => ['label' => $orders . '+', 'icon' => 'bi-bag-check-fill', 'sub' => 'การจองสำเร็จ'],
                        'mature'   => ['label' => self::shortNumber($orders) . '+', 'icon' => 'bi-bag-check-fill', 'sub' => 'การซื้อรูปสำเร็จ'],
                    ]),
                ],
            ];
        });
    }

    /** Flush cache when admin approves a photographer or content moderator runs cleanup. */
    public static function flush(): void
    {
        Cache::forget('platform_stats_snapshot');
    }

    /* ─── Internal helpers ─── */

    protected static function safeCount(string $table, ?\Closure $constraint): int
    {
        try {
            if (!Schema::hasTable($table)) return 0;
            $q = DB::table($table);
            if ($constraint) $constraint($q);
            return (int) $q->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * Pick the adaptive copy bucket based on raw count + thresholds.
     * Returns ['tier' => 'founding|growing|mature', 'label' => '...', 'icon' => '...', 'sub' => '...']
     */
    protected static function adaptive(int $count, array $thresholds, array $copyByTier): array
    {
        if ($count < $thresholds['founding']) {
            return array_merge(['tier' => 'founding', 'count' => $count], $copyByTier['founding']);
        }
        if ($count < $thresholds['growing']) {
            return array_merge(['tier' => 'growing', 'count' => $count], $copyByTier['growing']);
        }
        return array_merge(['tier' => 'mature', 'count' => $count], $copyByTier['mature']);
    }

    /** "23,400" → "23K"; "1,200,000" → "1.2M" */
    protected static function shortNumber(int $n): string
    {
        if ($n >= 1_000_000) return number_format($n / 1_000_000, 1) . 'M';
        if ($n >= 10_000)    return floor($n / 1000) . 'K';
        if ($n >= 1_000)     return number_format($n / 1000, 1) . 'K';
        return (string) $n;
    }

    /** Round down to nearest 50/100/500 for display swagger. */
    protected static function round(int $n): string
    {
        if ($n >= 10_000) return number_format(floor($n / 1000) * 1000);
        if ($n >= 1_000)  return number_format(floor($n / 100) * 100);
        if ($n >= 100)    return (string) (floor($n / 50) * 50);
        return (string) $n;
    }
}

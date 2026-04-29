<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

/**
 * One sellable item in the photographer self-serve store.
 *
 * SKUs are immutable contracts — purchase rows reference them by
 * string, and the activation handler dispatches by category. Renaming a
 * SKU after a purchase exists would orphan that purchase from its
 * activation handler. The admin UI warns about this.
 *
 * Categories + their meta keys
 *   promotion    — { kind: boost|featured|highlight, cycle: daily|monthly|yearly, boost_score: 1-25 }
 *   storage      — { storage_gb: 50|200|1024 }
 *   ai_credits   — { credits: 5000|20000|100000 }
 *   branding     — { one_time: true } or { cycle: monthly|yearly }
 *   priority     — { cycle: monthly }
 *
 * Cached for 5 minutes — admin edits explicitly invalidate via
 * static::clearCache() on save/delete (see booted()).
 */
class AddonItem extends Model
{
    use SoftDeletes;

    protected $table = 'addon_items';

    public const CACHE_KEY = 'addon_items.catalog';
    public const CACHE_TTL = 300; // 5 min

    protected $fillable = [
        'sku', 'category', 'label', 'tagline', 'price_thb',
        'badge', 'meta', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'price_thb'  => 'decimal:2',
        'meta'       => 'array',
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];

    /* ─────────────────── Categories ─────────────────── */

    public const CATEGORY_PROMOTION  = 'promotion';
    public const CATEGORY_STORAGE    = 'storage';
    public const CATEGORY_AI_CREDITS = 'ai_credits';
    public const CATEGORY_BRANDING   = 'branding';
    public const CATEGORY_PRIORITY   = 'priority';

    public static function categories(): array
    {
        return [
            self::CATEGORY_PROMOTION  => 'โปรโมท',
            self::CATEGORY_STORAGE    => 'พื้นที่เก็บงาน',
            self::CATEGORY_AI_CREDITS => 'AI Credits',
            self::CATEGORY_BRANDING   => 'Branding',
            self::CATEGORY_PRIORITY   => 'Priority',
        ];
    }

    /* ─────────────────── Scopes ─────────────────── */

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function scopeOrdered(Builder $q): Builder
    {
        return $q->orderBy('category')->orderBy('sort_order')->orderBy('id');
    }

    /* ─────────────────── Cache invalidation ─────────────────── */

    protected static function booted(): void
    {
        // Any write to addon_items invalidates the cached catalog so the
        // public store + AddonService::catalog() see the change on the
        // next request. We don't do this in the controller because
        // model-event-driven invalidation also catches background jobs
        // and tinker writes.
        $clear = function () {
            Cache::forget(self::CACHE_KEY);
        };
        static::saved($clear);
        static::deleted($clear);
        static::restored($clear);
    }

    public static function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /* ─────────────────── Catalog rendering ─────────────────── */

    /**
     * Build the same nested structure that `config('addon_catalog')` had,
     * so AddonService doesn't need to change its array contract:
     *
     *   ['promotion' => ['title' => …, 'items' => [...]], …]
     *
     * The category title/description/icon/accent come from a static
     * map below — those don't vary by SKU.
     */
    public static function catalogStructure(bool $activeOnly = true): array
    {
        $query = static::query();
        if ($activeOnly) $query->active();
        $rows = $query->ordered()->get();

        $shape = self::categoryShape();
        $out = [];
        foreach ($rows as $row) {
            $cat = $row->category;
            if (!isset($out[$cat])) {
                $out[$cat] = $shape[$cat] ?? [
                    'title'       => self::categories()[$cat] ?? ucfirst($cat),
                    'description' => '',
                    'icon'        => 'bi-stars',
                    'accent'      => '#6366f1',
                    'items'       => [],
                ];
                $out[$cat]['items'] = [];
            }
            $out[$cat]['items'][] = array_merge([
                'sku'       => $row->sku,
                'label'     => $row->label,
                'tagline'   => $row->tagline,
                'price_thb' => (float) $row->price_thb,
                'badge'     => $row->badge,
            ], $row->meta ?? []);
        }
        return $out;
    }

    /**
     * Per-category presentation metadata (title, blurb, icon, accent).
     * Mirrors what was hard-coded in config/addon_catalog.php so the
     * Store UI keeps its visual identity.
     */
    private static function categoryShape(): array
    {
        return [
            'promotion' => [
                'title'       => 'โปรโมทช่างภาพ · ขึ้นอันดับสูง',
                'description' => 'จ่ายเพื่อให้โปรไฟล์คุณแสดงก่อนคู่แข่งในผลการค้นหา · เพิ่ม impressions + booking 30-60%',
                'icon'        => 'bi-rocket-takeoff',
                'accent'      => '#6366f1',
                'items'       => [],
            ],
            'storage' => [
                'title'       => 'พื้นที่เก็บงานเสริม',
                'description' => 'ครบ quota แล้ว? ซื้อเพิ่มได้โดยไม่ต้อง upgrade plan · 1 ครั้ง · ใช้ได้ตลอดอายุ subscription',
                'icon'        => 'bi-cloud-arrow-up-fill',
                'accent'      => '#0ea5e9',
                'items'       => [],
            ],
            'ai_credits' => [
                'title'       => 'AI Credits เสริม',
                'description' => 'ใช้ AI Face Search / คัดรูป / Best Shot · 1 credit = 1 ภาพประมวลผล · ใช้ได้ตลอดเดือนเดียวกัน',
                'icon'        => 'bi-cpu-fill',
                'accent'      => '#a855f7',
                'items'       => [],
            ],
            'branding' => [
                'title'       => 'Branding & Priority',
                'description' => 'จ่ายครั้งเดียว · ใช้ตลอดอายุ subscription · ตัดเหนือคู่แข่งในด้าน UX',
                'icon'        => 'bi-palette-fill',
                'accent'      => '#10b981',
                'items'       => [],
            ],
            'priority' => [
                'title'       => 'Priority Lane',
                'description' => 'อัปโหลดข้ามคิว ใช้ในช่วง peak event',
                'icon'        => 'bi-lightning-charge-fill',
                'accent'      => '#f97316',
                'items'       => [],
            ],
        ];
    }
}

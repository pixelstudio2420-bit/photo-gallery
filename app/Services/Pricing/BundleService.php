<?php

namespace App\Services\Pricing;

use App\Models\Event;
use App\Models\EventPhoto;
use App\Models\PricingPackage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * BundleService — central decisioning layer for everything bundle-related.
 *
 * Three things this owns:
 *   1. Templates  : preset bundle configurations photographers can apply with
 *                   one click. Tuned per event category (Sport vs Wedding vs
 *                   Concert benefit from different bundle sizes).
 *   2. Seeding    : `seedDefaultsForEvent()` runs from the Event observer when
 *                   a new event is created so every event ships with sane
 *                   bundles instead of an empty cart-prompt.
 *   3. Pricing    : the live math for face_match + event_all bundles, plus
 *                   the upsell finder ("user has 4 in cart, suggest the
 *                   6-photo bundle"). Centralized here so the public event
 *                   page, cart API, and face-search controller all agree.
 *
 * Why a service instead of static methods on the model:
 *   The pricing math depends on the event's per-photo price, photo count,
 *   and current package list — three different model lookups. Wrapping that
 *   into a single service surface keeps callers from having to wire all
 *   three together themselves.
 */
class BundleService
{
    public function __construct(
        private readonly \App\Services\EventPriceResolver $priceResolver,
    ) {}

    /* ═══════════════════════════════════════════════════════════════
     * Templates — preset bundle configurations by event category
     * ═══════════════════════════════════════════════════════════════ */

    /**
     * Return the recommended template for a given event category slug.
     *
     * Each template returns an array of bundle definitions; the caller
     * (`seedDefaultsForEvent` or admin "apply template" action) substitutes
     * in the event-specific per-photo price to compute final amounts.
     *
     * Discount curves are tuned per category:
     *   • Sport:    bigger bundles dominate (athletes want all their finish-line shots)
     *   • Wedding:  emphasizes high-tier event-all bundle
     *   • Concert:  small bundles only (most fans want 2-3 favorite shots)
     *   • Default:  the balanced 3/6/10 ladder
     */
    public function templates(): array
    {
        return [
            'standard' => [
                'label'   => 'มาตรฐาน (3/6/10/20 รูป)',
                'icon'    => 'bi-stack',
                'desc'    => 'แพ็กเกจสมดุลสำหรับงานทั่วไป — มี bundle 4 ระดับ + เหมารูปตัวเอง',
                'bundles' => [
                    ['count' => 3,  'discount' => 10, 'badge' => 'เริ่มประหยัด',  'featured' => false],
                    ['count' => 6,  'discount' => 20, 'badge' => 'ขายดีที่สุด',   'featured' => true ],
                    ['count' => 10, 'discount' => 30, 'badge' => 'คุ้มค่า',        'featured' => false],
                    ['count' => 20, 'discount' => 40, 'badge' => 'ประหยัดสูงสุด', 'featured' => false],
                    ['type'  => 'face_match', 'discount' => 50, 'max' => 1500, 'badge' => 'ฮิต! เหมาตัวเอง', 'featured' => false],
                ],
            ],
            'sports' => [
                'label'   => 'กีฬา / Marathon (เน้น face bundle)',
                'icon'    => 'bi-trophy',
                'desc'    => 'นักกีฬาส่วนใหญ่อยากได้รูปตัวเองครบทุกรูป — bundle 10 + face bundle เป็น core',
                'bundles' => [
                    ['count' => 3,  'discount' => 10, 'badge' => 'ลองชิม',         'featured' => false],
                    ['count' => 10, 'discount' => 30, 'badge' => 'ขายดีที่สุด',    'featured' => true ],
                    ['type'  => 'face_match', 'discount' => 55, 'max' => 1900, 'badge' => '⭐ เหมาทุกรูปของคุณ', 'featured' => false],
                ],
            ],
            'wedding' => [
                'label'   => 'งานแต่ง / Pre-wedding (เน้นเหมาทั้งงาน)',
                'icon'    => 'bi-heart',
                'desc'    => 'คู่บ่าวสาวและครอบครัวอยากได้ทั้งงาน — เน้น event_all + bundle 20',
                'bundles' => [
                    ['count' => 10, 'discount' => 25, 'badge' => 'เก็บไฮไลท์',      'featured' => false],
                    ['count' => 20, 'discount' => 35, 'badge' => 'ขายดีที่สุด',     'featured' => true ],
                    ['type'  => 'event_all', 'price_multiplier' => 0.30, 'badge' => '💎 เหมาทั้งงาน', 'featured' => false],
                ],
            ],
            'concert' => [
                'label'   => 'คอนเสิร์ต / บันเทิง (bundle เล็ก)',
                'icon'    => 'bi-music-note',
                'desc'    => 'แฟนเพลงส่วนใหญ่ซื้อแค่ 2-3 รูปโปรด — bundle เล็กพอ',
                'bundles' => [
                    ['count' => 3, 'discount' => 15, 'badge' => 'ขายดีที่สุด',  'featured' => true ],
                    ['count' => 6, 'discount' => 25, 'badge' => 'คุ้มค่า',       'featured' => false],
                ],
            ],
            'corporate' => [
                'label'   => 'งานองค์กร / Education (เน้น event_all)',
                'icon'    => 'bi-building',
                'desc'    => 'HR / ผู้จัดงานอยากดาวน์โหลดทั้งงาน — เน้น event_all',
                'bundles' => [
                    ['count' => 10, 'discount' => 25, 'badge' => 'มาตรฐาน',         'featured' => false],
                    ['type'  => 'event_all', 'price_multiplier' => 0.20, 'badge' => '⭐ เหมาทั้งงาน', 'featured' => true],
                    ['type'  => 'face_match', 'discount' => 50, 'max' => 1500, 'badge' => 'เหมารูปตัวเอง', 'featured' => false],
                ],
            ],
        ];
    }

    /** Map an event_categories.slug to a template key, defaulting to 'standard'. */
    public function templateKeyForCategory(?string $slug): string
    {
        return match ($slug) {
            'sports'                                  => 'sports',
            'wedding'                                 => 'wedding',
            'entertainment'                           => 'concert',
            'corporate', 'education'                  => 'corporate',
            default                                   => 'standard',
        };
    }

    /* ═══════════════════════════════════════════════════════════════
     * Seeding — auto-create bundles when an event is created
     * ═══════════════════════════════════════════════════════════════ */

    /**
     * Build PricingPackage rows for `$event` based on its category template.
     *
     * Idempotent: returns immediately if the event already has any packages,
     * to avoid stomping on a photographer who's already customized them.
     * That guard is what lets us safely call this from the Event observer
     * even after the photographer has manually edited their bundles.
     *
     * @return int Number of bundles created.
     */
    public function seedDefaultsForEvent(Event $event): int
    {
        // Don't seed if the event already has any packages — assumes the
        // photographer (or admin) has set things up the way they want.
        if (PricingPackage::where('event_id', $event->id)->exists()) {
            return 0;
        }

        $perPhoto = $this->priceResolver->perPhoto($event->id);
        if ($perPhoto <= 0) {
            // Free events / events without a price set don't get auto bundles.
            // The photographer can manually add them once they decide pricing.
            return 0;
        }

        $categorySlug = optional($event->category)->slug;
        $templateKey  = $this->templateKeyForCategory($categorySlug);
        $template     = $this->templates()[$templateKey] ?? $this->templates()['standard'];

        $created = 0;
        foreach ($template['bundles'] as $i => $def) {
            $row = $this->materializeBundle($event, $perPhoto, $def, $i);
            if ($row) {
                PricingPackage::create($row);
                $created++;
            }
        }
        return $created;
    }

    /**
     * Apply a template to an event, REPLACING any existing bundles. Used by
     * the photographer "Apply Template" button which is explicit overwrite.
     */
    public function applyTemplate(Event $event, string $templateKey): int
    {
        // Wipe first — the photographer asked for a clean slate.
        PricingPackage::where('event_id', $event->id)->delete();

        $perPhoto = $this->priceResolver->perPhoto($event->id);
        if ($perPhoto <= 0) {
            return 0;
        }

        $template = $this->templates()[$templateKey] ?? $this->templates()['standard'];
        $created = 0;
        foreach ($template['bundles'] as $i => $def) {
            $row = $this->materializeBundle($event, $perPhoto, $def, $i);
            if ($row) {
                PricingPackage::create($row);
                $created++;
            }
        }

        // Bust the per-event price cache in case anything was stale.
        $this->priceResolver->forget($event->id);
        return $created;
    }

    /**
     * Convert a template bundle definition into a database row.
     * Returns null if the definition is malformed (defensive).
     */
    private function materializeBundle(Event $event, float $perPhoto, array $def, int $sortOrder): ?array
    {
        $type = $def['type'] ?? 'count';

        if ($type === 'count') {
            $count    = (int) ($def['count'] ?? 0);
            if ($count <= 0) return null;
            $discount = (float) ($def['discount'] ?? 0);
            $original = round($count * $perPhoto, 2);
            $price    = round($original * (1 - $discount / 100), 2);
            return [
                'event_id'        => $event->id,
                'name'            => "{$count} รูป",
                'photo_count'     => $count,
                'price'           => $price,
                'original_price'  => $original,
                'bundle_type'     => 'count',
                'discount_pct'    => $discount,
                'description'     => "เลือกได้ {$count} รูป — เพียง ฿" . number_format($price / $count, 0) . "/รูป",
                'bundle_subtitle' => $count <= 3 ? 'เหมาะสำหรับลูกค้าทั่วไป' : ($count >= 20 ? 'เหมาะสำหรับครอบครัว' : 'เหมาะสำหรับคนชอบเก็บความทรงจำ'),
                'badge'           => $def['badge']    ?? null,
                'is_featured'     => (bool) ($def['featured'] ?? false),
                'is_active'       => true,
                'sort_order'      => $sortOrder,
            ];
        }

        if ($type === 'face_match') {
            $discount = (float) ($def['discount'] ?? 50);
            $maxPrice = (float) ($def['max']     ?? 1500);
            return [
                'event_id'        => $event->id,
                'name'            => 'เหมารูปตัวเอง',
                'photo_count'     => null,
                'price'           => 0, // computed at view time
                'bundle_type'     => 'face_match',
                'discount_pct'    => $discount,
                'max_price'       => $maxPrice,
                'description'     => "ใช้ AI ค้นหารูปของคุณทั้งหมดในอีเวนต์ — ลด " . (int) $discount . "% (สูงสุด ฿" . number_format($maxPrice, 0) . ")",
                'bundle_subtitle' => '🎯 ค้นหาด้วย Face Recognition',
                'badge'           => $def['badge']    ?? '⭐ ฮิต',
                'is_featured'     => (bool) ($def['featured'] ?? false),
                'is_active'       => true,
                'sort_order'      => $sortOrder,
            ];
        }

        if ($type === 'event_all') {
            // event_all price = total photos × per_photo × multiplier (e.g. 0.20)
            $multiplier = (float) ($def['price_multiplier'] ?? 0.25);
            $totalPhotos = max(1, EventPhoto::where('event_id', $event->id)->count());
            $original    = round($totalPhotos * $perPhoto, 2);
            $price       = round($original * $multiplier, 2);
            // Floor at a sensible minimum (otherwise tiny events would be free).
            $price = max($price, 990);
            return [
                'event_id'        => $event->id,
                'name'            => 'เหมาทั้งอีเวนต์',
                'photo_count'     => $totalPhotos,
                'price'           => $price,
                'original_price'  => $original,
                'bundle_type'     => 'event_all',
                'description'     => "ดาวน์โหลดทุกรูปจากอีเวนต์ — " . number_format($totalPhotos) . " รูป",
                'bundle_subtitle' => '💎 เหมาะสำหรับผู้จัดงาน / ครอบครัว',
                'badge'           => $def['badge']    ?? '⭐ คุ้มสุด',
                'is_featured'     => (bool) ($def['featured'] ?? false),
                'is_active'       => true,
                'sort_order'      => $sortOrder,
            ];
        }

        return null;
    }

    /* ═══════════════════════════════════════════════════════════════
     * Live pricing for dynamic bundles (face_match, event_all)
     * ═══════════════════════════════════════════════════════════════ */

    /**
     * Compute the price the buyer would pay for a face_match bundle given
     * the number of photos that face-search returned for them.
     *
     * Returns an array with the full breakdown so the UI can show:
     *   "23 รูปของคุณ — ปกติ ฿2,300, ลด 50% เหลือ ฿1,150 (เพียง ฿50/รูป)"
     */
    public function calculateFaceBundle(Event $event, int $foundCount, ?PricingPackage $package = null): ?array
    {
        if ($foundCount < 1) return null;

        $package = $package ?: PricingPackage::forEvent($event->id)->faceMatch()->where('is_active', true)->first();
        if (!$package) return null;

        $perPhoto = $this->priceResolver->perPhoto($event->id);
        if ($perPhoto <= 0) return null;

        $discount  = (float) ($package->discount_pct ?? 50);
        $maxPrice  = (float) ($package->max_price ?? 1500);

        $original  = round($foundCount * $perPhoto, 2);
        $price     = round($original * (1 - $discount / 100), 2);
        $price     = min($price, $maxPrice);
        // Floor: never less than 1 photo at full price (avoid ฿0 bundles).
        $price     = max($price, $perPhoto);

        return [
            'package_id'      => $package->id,
            'photo_count'     => $foundCount,
            'price'           => $price,
            'original_price'  => $original,
            'savings'         => max(0, $original - $price),
            'savings_pct'     => $original > 0 ? round(($original - $price) / $original * 100) : 0,
            'per_photo_price' => $foundCount > 0 ? round($price / $foundCount, 2) : 0,
            'discount_pct'    => $discount,
            'max_price_hit'   => $price >= $maxPrice && $original > $maxPrice,
        ];
    }

    /* ═══════════════════════════════════════════════════════════════
     * Smart upsell — suggest the bundle that gives best value for cart
     * ═══════════════════════════════════════════════════════════════ */

    /**
     * Given a buyer with `$cartCount` photos in cart for `$event`, find the
     * bundle they should upgrade to (if any).
     *
     * Returns the bundle that maximizes per-photo savings while requiring the
     * smallest additional photo selection. Skips face_match / event_all
     * (those are pitched separately, not as cart upsells).
     */
    public function findBestUpsell(Event $event, int $cartCount, float $cartTotal): ?array
    {
        if ($cartCount < 1) return null;

        $bundles = PricingPackage::forEvent($event->id)
            ->countBundles()
            ->where('is_active', true)
            ->orderBy('photo_count')
            ->get();

        foreach ($bundles as $b) {
            // Bundle bigger than cart that the buyer can grow into.
            if ($b->photo_count > $cartCount) {
                $delta = $b->photo_count - $cartCount;
                $costAfter = (float) $b->price;
                $savingsVsAdding = max(0, $cartTotal + ($delta * $this->priceResolver->perPhoto($event->id)) - $costAfter);
                if ($savingsVsAdding > 0) {
                    return [
                        'package_id'    => $b->id,
                        'name'          => $b->name,
                        'add_photos'    => $delta,
                        'new_total'     => $costAfter,
                        'current_total' => $cartTotal,
                        'savings'       => round($savingsVsAdding, 2),
                        'badge'         => $b->badge,
                        'message'       => "เพิ่มอีก {$delta} รูป = ฿" . number_format($costAfter, 0) . " (ประหยัด ฿" . number_format($savingsVsAdding, 0) . "!)",
                    ];
                }
            }
        }
        return null;
    }

    /* ═══════════════════════════════════════════════════════════════
     * Recalculation — re-derive bundle prices from current per-photo
     * ═══════════════════════════════════════════════════════════════ */

    /**
     * Recalculate price + original_price on every count/event_all bundle
     * for `$event` using the current `event.price_per_photo`. Each row's
     * existing `discount_pct` is preserved, so the photographer's manual
     * tweaks to the discount percentage still apply.
     *
     * face_match bundles are skipped — their price is computed at view
     * time anyway (count × per_photo × discount, capped), so they always
     * stay in sync with the live per_photo without needing a row update.
     *
     * Use case: photographer creates an event at ฿100/photo, system
     * seeds bundles {3 rooks=฿270, 6=฿480, ...}, then a week later the
     * photographer raises per_photo to ฿200. Without recalc the buyer
     * still pays ฿270 for 3 photos = ฿90/photo (a 55% discount instead
     * of the intended 10%). This method brings the bundles back in line.
     *
     * @return array  [$updated, $skipped] count breakdown.
     */
    public function recalculatePrices(Event $event): array
    {
        $perPhoto = $this->priceResolver->perPhoto($event->id);
        if ($perPhoto <= 0) {
            return [0, 0];
        }

        $bundles = PricingPackage::where('event_id', $event->id)->get();
        $updated = 0;
        $skipped = 0;

        foreach ($bundles as $bundle) {
            // face_match: nothing to update (price = 0 placeholder; live
            // computation in calculateFaceBundle() always uses the
            // current per_photo).
            if ($bundle->bundle_type === self::TYPE_FACE_MATCH) {
                $skipped++;
                continue;
            }

            // count bundles: original_price = count × per_photo,
            //                price          = original × (1 - discount/100)
            if ($bundle->bundle_type === 'count' && $bundle->photo_count > 0) {
                $discount = (float) ($bundle->discount_pct ?? 0);
                $original = round($bundle->photo_count * $perPhoto, 2);
                $price    = round($original * (1 - $discount / 100), 2);

                $bundle->update([
                    'original_price' => $original,
                    'price'          => $price,
                ]);
                $updated++;
                continue;
            }

            // event_all: keep the user's manually-set price untouched
            // (they pick a flat fee like ฿2,990 and don't want it to
            // drift just because per_photo changed). Only refresh the
            // original_price (slashed-out display) for accurate discount %.
            if ($bundle->bundle_type === 'event_all' && $bundle->photo_count > 0) {
                $original = round($bundle->photo_count * $perPhoto, 2);
                $bundle->update(['original_price' => $original]);
                $updated++;
                continue;
            }

            $skipped++;
        }

        return [$updated, $skipped];
    }

    /**
     * Detect bundles whose price has drifted significantly from what the
     * current per-photo + discount would yield. Used by the photographer
     * UI to surface a "your bundles may be off" warning banner.
     *
     * Returns a list of [bundle, expected_price, actual_price, drift_pct]
     * tuples — empty array means everything is in sync.
     */
    public function detectPriceDrift(Event $event, float $thresholdPct = 5.0): array
    {
        $perPhoto = $this->priceResolver->perPhoto($event->id);
        if ($perPhoto <= 0) return [];

        $drift = [];
        $bundles = PricingPackage::where('event_id', $event->id)
            ->where('bundle_type', 'count')
            ->where('photo_count', '>', 0)
            ->get();

        foreach ($bundles as $b) {
            $discount = (float) ($b->discount_pct ?? 0);
            $expected = round($b->photo_count * $perPhoto * (1 - $discount / 100), 2);
            $actual   = (float) $b->price;
            if ($expected <= 0) continue;
            $diffPct = abs($actual - $expected) / $expected * 100;
            if ($diffPct >= $thresholdPct) {
                $drift[] = [
                    'bundle'    => $b,
                    'expected'  => $expected,
                    'actual'    => $actual,
                    'drift_pct' => round($diffPct, 1),
                ];
            }
        }

        return $drift;
    }

    /* ═══════════════════════════════════════════════════════════════
     * Stats — invoked from Order observer when a paid order has a package
     * ═══════════════════════════════════════════════════════════════ */

    public function recordPurchase(int $packageId): void
    {
        DB::table('pricing_packages')->where('id', $packageId)->increment('purchase_count');
    }
}

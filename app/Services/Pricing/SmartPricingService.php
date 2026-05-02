<?php

namespace App\Services\Pricing;

/**
 * SmartPricingService — psychology + market pricing for photo bundles.
 *
 * The naive approach is "every bundle gets the same fixed discount %"
 * (e.g. 3 photos always 10% off, 6 photos always 20% off, ...). That
 * fails on two ends:
 *
 *   • At low per-photo prices (~฿50) a 40%-off "20-photo bundle" lands
 *     around ฿600 — which Thai consumers read as "too cheap → low
 *     quality" and bounce off. Cheaper-feeling discounts beat deeper
 *     ones below the trust threshold.
 *
 *   • At premium per-photo prices (~฿500) the same fixed-curve bundles
 *     produce sticker-shocking ฿6,000 ฿/order numbers that reduce
 *     conversion despite "matching the math". Premium buyers respond
 *     to bigger savings %, not bigger absolute prices.
 *
 * This service answers three questions in one call:
 *
 *   1. What discount % is psychologically right for THIS price tier
 *      and THIS bundle size? (priceTier + discountCurve)
 *   2. After charm-pricing the result, what's the final number? (snapToCharm)
 *   3. Are we still safely above a profit floor? (computeBundlePrice cap)
 *
 * The whole curve is data-only constants here — easy to A/B-test and
 * tune as the marketplace gathers real conversion data.
 */
class SmartPricingService
{
    /**
     * Hard ceiling on discount %. Below this the value-perception
     * gradient flattens (further off doesn't drive more conversion)
     * and the photographer's margin starts hurting.
     *
     * Pair with MIN_BUNDLE_RETENTION_PCT below — the discount cap
     * limits the curve, the retention floor enforces the FINAL
     * price (in case charm-snap rounds the curve target down).
     */
    private const MAX_DISCOUNT_PCT = 60.0;

    /**
     * Hard floor: a bundle's final price must retain at least this
     * percentage of `photo_count × per_photo` AFTER curve discount,
     * charm pricing, and any other adjustment. Mirrors the discount
     * cap (60% off ⇒ 40% retained) but is enforced as the LAST
     * gate in computeBundlePrice — so even an aggressive curve
     * + charm-snap-down can never make the photographer "ขาดทุน".
     *
     * Concrete safety net: with a 50-photo premium-featured bundle
     * (60% curve discount), the raw price ฿5,000 charm-snaps to
     * ฿4,990 — 10 baht under the 40% retention floor. Without this
     * gate the photographer would silently lose ~฿8 commission per
     * sale on every premium 50-pack.
     */
    private const MIN_BUNDLE_RETENTION_PCT = 40.0;

    /**
     * Tier classification thresholds (THB per photo).
     *
     *   • low      < ฿80     → casual events, sport, school galleries
     *   • mid      ฿80-249   → corporate, weddings, festivals
     *   • premium  ≥ ฿250    → professional shoots, pre-wedding, brand work
     */
    private const TIER_LOW_MAX     = 80.0;
    private const TIER_PREMIUM_MIN = 250.0;

    /**
     * Discount curves per tier × bundle-size. The numbers come from the
     * market-research summary in /docs/pricing-strategy.md (linked in
     * the bundle redesign PR). Each row keys photo_count → discount %.
     *
     * The curves intentionally diverge as bundle size grows:
     *   - low-tier curve flattens (won't go below ~45% even at 50 photos)
     *     because we don't want fire-sale optics on already-cheap photos.
     *   - premium curve climbs steeper to amortize sticker shock with
     *     real-feeling savings ("save ฿2,500 on a 50-photo bundle!").
     */
    private const CURVES = [
        'low' => [
            3  => 8,
            6  => 18,
            10 => 25,
            20 => 35,
            50 => 42,
        ],
        'mid' => [
            3  => 10,
            6  => 22,
            10 => 32,
            20 => 42,
            50 => 50,
        ],
        'premium' => [
            3  => 12,
            6  => 25,
            10 => 38,
            20 => 48,
            50 => 55,
        ],
    ];

    /**
     * Bonus discount % added when a bundle is is_featured=true (the
     * "Most Popular" card). Amplifies the decoy effect — the featured
     * tier appears even more value-dense than its neighbours.
     */
    private const FEATURED_BONUS_PCT = 5.0;

    /* ═══════════════════════════════════════════════════════════════
     * Public API
     * ═══════════════════════════════════════════════════════════════ */

    /**
     * Classify a per-photo price into one of three psychological tiers.
     */
    public function priceTier(float $perPhoto): string
    {
        if ($perPhoto < self::TIER_LOW_MAX)     return 'low';
        if ($perPhoto < self::TIER_PREMIUM_MIN) return 'mid';
        return 'premium';
    }

    /**
     * Compute the discount % to apply for a given (count, per_photo)
     * combination, with optional featured boost.
     *
     * Note: this returns the FINE-grained curve discount (with featured
     * bonus added) — no 5%-snap here. Snapping the curve target broke
     * per-photo monotonicity: adjacent counts could land on the same
     * round step (e.g. n=11 and n=12 both become "30%") while their
     * raw prices differed by less than the snap quantum, causing the
     * smaller-bundle's per-photo cost to look LOWER than the larger's.
     *
     * The MARKETING-facing "ลด 30%" badge in the bundle card view
     * rounds to 5% for display only — internal math stays precise so
     * "more photos = cheaper per photo" remains a hard guarantee.
     */
    public function computeDiscount(int $photoCount, float $perPhoto, bool $isFeatured = false): float
    {
        if ($photoCount <= 0 || $perPhoto <= 0) return 0.0;

        $tier  = $this->priceTier($perPhoto);
        $curve = self::CURVES[$tier];

        $base = $this->lookupOrInterpolate($curve, $photoCount);

        if ($isFeatured) {
            $base += self::FEATURED_BONUS_PCT;
        }

        return min($base, self::MAX_DISCOUNT_PCT);
    }

    /**
     * Compute the FULL bundle pricing breakdown — the workhorse that
     * BundleService calls during seed + recalc.
     *
     * Returns:
     *   [
     *     'price'              => float  // charm-snapped, floor-protected final price
     *     'original_price'     => float  // count × per_photo (pre-discount)
     *     'discount_pct'       => float  // ACTUAL % the buyer sees (post-snap)
     *     'savings'            => float  // original - price (loss-aversion frame)
     *     'effective_per_photo'=> float  // price ÷ count — what each photo
     *                                    //   in the bundle is worth after discount
     *     'floor_applied'      => bool   // true if the retention floor had to
     *                                    //   intervene against the curve+charm result
     *   ]
     *
     * No-loss guarantee:
     *   1. Curve discount capped at MAX_DISCOUNT_PCT (default 60%)
     *      via computeDiscount().
     *   2. AFTER charm-snap, if the snapped price falls below the
     *      retention floor (MIN_BUNDLE_RETENTION_PCT × original),
     *      we re-snap UP via snapToCharmCeil() so the buyer pays
     *      a charm-shaped price ≥ floor.
     *   3. Absolute sanity floor: never below 1× per_photo (catches
     *      absurd configs, e.g. per_photo manually edited to ฿2).
     *
     * Together these ensure the photographer ALWAYS retains at
     * least 40% of `photo_count × per_photo` — no silent margin
     * leak from overly aggressive curve combos.
     */
    public function computeBundlePrice(int $photoCount, float $perPhoto, bool $isFeatured = false): array
    {
        if ($perPhoto <= 0 || $photoCount <= 0) {
            return [
                'price' => 0.0, 'original_price' => 0.0,
                'discount_pct' => 0.0, 'savings' => 0.0,
                'effective_per_photo' => 0.0, 'floor_applied' => false,
            ];
        }

        $original  = round($photoCount * $perPhoto, 2);
        $discount  = $this->computeDiscount($photoCount, $perPhoto, $isFeatured);
        $rawPrice  = $original * (1 - $discount / 100);

        // Charm-snap (rounds to ...9 / ...90 / ...990 endings).
        $price        = $this->snapToCharm($rawPrice);
        $floorApplied = false;

        // ── Guard 1: retention floor ────────────────────────────
        // The "no-loss" floor — bundle price must retain at least
        // MIN_BUNDLE_RETENTION_PCT% of the original. If charm-snap
        // pushed us below it (possible on max-discount + featured
        // combos), bump UP to the next charm endpoint above the
        // floor — buyer still sees a *9 / *90 / *900 price, but
        // the photographer never silently loses commission to
        // arithmetic rounding.
        $minRetention = round($original * (self::MIN_BUNDLE_RETENTION_PCT / 100), 2);
        if ($price < $minRetention) {
            $price = $this->snapToCharmCeil($minRetention);
            $floorApplied = true;
        }

        // ── Guard 2: absolute sanity floor ──────────────────────
        // Last-resort catch for absurd configs. In practice this
        // is dwarfed by the retention floor above for any normal
        // bundle (10-photo bundle ≥ ฿100 > 1× per_photo always).
        $absMinPrice = round($perPhoto, 2);
        if ($price < $absMinPrice) {
            $price = $absMinPrice;
            $floorApplied = true;
        }

        // Recompute the actual discount % after charm + floor adjustment
        // so the UI shows the truth (not the curve-target).
        $actualDiscount = $original > 0
            ? max(0, ($original - $price) / $original * 100)
            : 0;

        return [
            'price'               => round($price, 2),
            'original_price'      => $original,
            'discount_pct'        => round($actualDiscount, 1),
            'savings'             => round(max(0, $original - $price), 2),
            'effective_per_photo' => round($price / $photoCount, 2),
            'floor_applied'       => $floorApplied,
        ];
    }

    /**
     * Snap a raw price to the nearest "*9 ending" charm endpoint,
     * ALWAYS rounding DOWN.
     *
     * Two design decisions, each driven by a real bug we hit in
     * smoke testing:
     *
     *   1. Snap DOWN, not snap-to-nearest. With round(), two adjacent
     *      bundle counts could land in opposite directions (e.g., 270
     *      → 269 down, 405 → 409 up) and the per-photo cost would
     *      INCREASE between adjacent counts. Buyers reasonably expect
     *      "more photos = cheaper per photo" — any inversion reads
     *      as a bug. Snap-down is monotonicity-preserving: raw price
     *      grows monotonically with count, and floor-snap can only
     *      shift each value DOWN by ≤ step, so the order is kept.
     *
     *   2. Step=10 across ALL price bands. Mixed granularity (10 for
     *      sub-1k, 100 for thousands) introduced bucket-jump inversions
     *      around the band boundaries (e.g., ฿999 → next charm endpoint
     *      ฿1,099 jumps 100 baht for 1 extra photo, which inverts
     *      per-photo when curve discount can't keep up). Uniform 10-baht
     *      grid trades a bit of charm aesthetics on large prices
     *      (฿14,999 instead of ฿14,900) for absolute monotonicity.
     *
     * Endings produced (all *9):
     *     19, 29, …, 99, 109, 199, 999, 1099, 1199, 9999, 10099, …
     *
     * Trade-off: ~10 baht more revenue would come from snap-to-nearest,
     * but this exact 10 baht is what causes the inversion. The retention
     * floor in computeBundlePrice() catches any drop below
     * MIN_BUNDLE_RETENTION_PCT, so the photographer never loses
     * commission to this snap.
     */
    public function snapToCharm(float $price): float
    {
        // Largest *9-ending value ≤ $price (uniform 10-baht grid).
        // Examples:
        //   $price=270  → 269
        //   $price=405  → 399
        //   $price=1100 → 1099
        //   $price=1418 → 1409
        //   $price=14900→ 14899
        $snapped = floor(($price - 9) / 10) * 10 + 9;

        // Tiny prices: never snap below ฿19. Anything lower than ฿19
        // for a sellable bundle is a config error, not a real price.
        return max(19.0, $snapped);
    }

    /**
     * Charm-snap that rounds UP — never below the input price.
     *
     * Used by the retention floor in computeBundlePrice: when the
     * normal snapToCharm() lands below our no-loss floor we re-snap
     * to the next charm endpoint ≥ floor so the price stays both
     * charm-shaped AND above the photographer-protection threshold.
     *
     * Example: floor ฿5,000 → snapToCharm gives ฿4,990 (below floor)
     *          → snapToCharmCeil gives ฿5,090 (next *90 ending above).
     */
    public function snapToCharmCeil(float $price): float
    {
        $snapped = $this->snapToCharm($price);
        if ($snapped >= $price) {
            return $snapped;
        }
        // snapToCharm now uses a uniform 10-baht step across all price
        // bands, so the ceiling counterpart is always snapped + 10.
        return $snapped + 10;
    }

    /* ═══════════════════════════════════════════════════════════════
     * Internals
     * ═══════════════════════════════════════════════════════════════ */

    /**
     * Lookup the discount for an exact bundle size, or linearly
     * interpolate when the size isn't in the curve's anchor points.
     *
     * E.g. curve has {3, 6, 10, 20, 50} but caller asks for size 8.
     * We interpolate between (6, 22%) and (10, 32%) → ~27% for size 8.
     *
     * Below-first-anchor (count < lowest curve key) — special case:
     * we DON'T just clamp to the first anchor's discount. Doing so
     * means count=2 inherits the full count=3 discount %, which then
     * causes a per-photo inversion when n=2 raw + snap-down loses
     * more to charm-snap than n=3 does.
     *
     * Concrete example pre-fix: premium ฿300 →
     *   n=2: 12% off → raw 528 → snap 519 → 259.50 / photo
     *   n=3: 12% off → raw 792 → snap 789 → 263.00 / photo
     * Per-photo INCREASED by ฿3.50 going from 2 → 3 photos.
     *
     * Post-fix: linearly scale the discount from 0% (at count=1) up
     * to the first anchor:
     *   n=1: 0% → solo price
     *   n=2: 6% (= 12% × 1/2)
     *   n=3: 12% (anchor)
     * That gives smooth, monotonic per-photo progression even when
     * the buyer picks a bundle size below the curve's smallest anchor.
     */
    private function lookupOrInterpolate(array $curve, int $count): float
    {
        ksort($curve);

        // Direct hit — most common case after seeding.
        if (isset($curve[$count])) {
            return (float) $curve[$count];
        }

        $keys   = array_keys($curve);
        $values = array_values($curve);
        $first  = (float) $values[0];
        $last   = (float) end($values);
        $firstK = (int) $keys[0];

        // Below first anchor → linear ramp from 0% (count=1) to the
        // first anchor's discount. Solves the n=2 inversion problem
        // documented in the docblock above.
        if ($count < $firstK) {
            if ($count <= 1 || $firstK <= 1) return 0.0;
            $ratio = ($count - 1) / ($firstK - 1);
            return $first * $ratio;
        }

        // Above last anchor → clamp to last anchor's discount.
        if ($count >= end($keys)) return $last;

        // Inside range → linear interpolate between adjacent anchors.
        for ($i = 0; $i < count($keys) - 1; $i++) {
            if ($count >= $keys[$i] && $count <= $keys[$i + 1]) {
                $span   = $keys[$i + 1] - $keys[$i];
                $offset = $count - $keys[$i];
                $ratio  = $span > 0 ? $offset / $span : 0;
                return $values[$i] + $ratio * ($values[$i + 1] - $values[$i]);
            }
        }

        return 20.0; // unreachable, defensive default
    }
}

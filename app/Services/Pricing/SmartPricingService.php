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
     */
    private const MAX_DISCOUNT_PCT = 60.0;

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
     *     'price'          => float  // charm-snapped final price
     *     'original_price' => float  // count × per_photo (pre-discount)
     *     'discount_pct'   => float  // ACTUAL % the buyer sees (post-snap)
     *     'savings'        => float  // original - price (loss-aversion frame)
     *   ]
     *
     * Profit floor: the snapped price is never allowed below
     * `$perPhoto × 1.0` (i.e. you never sell a bundle for less than the
     * solo price of one photo). That guards against absurd combinations
     * (e.g. someone manually edits per_photo to ฿2 — the curve would
     * try to charge ฿0.96 for a 6-photo bundle which is silly).
     */
    public function computeBundlePrice(int $photoCount, float $perPhoto, bool $isFeatured = false): array
    {
        if ($perPhoto <= 0 || $photoCount <= 0) {
            return ['price' => 0.0, 'original_price' => 0.0, 'discount_pct' => 0.0, 'savings' => 0.0];
        }

        $original  = round($photoCount * $perPhoto, 2);
        $discount  = $this->computeDiscount($photoCount, $perPhoto, $isFeatured);
        $rawPrice  = $original * (1 - $discount / 100);

        // Charm-snap (rounds to ...9 / ...90 / ...990 endings).
        $price = $this->snapToCharm($rawPrice);

        // Profit floor: never below 1× per-photo solo price. Catches
        // edge cases where a tiny per_photo + featured bonus + 50-photo
        // bundle produced an embarrassingly low number.
        $minPrice = round($perPhoto, 2);
        if ($price < $minPrice) {
            $price = $minPrice;
        }

        // Recompute the actual discount % after charm + floor adjustment
        // so the UI shows the truth (not the curve-target).
        $actualDiscount = $original > 0
            ? max(0, ($original - $price) / $original * 100)
            : 0;

        return [
            'price'          => round($price, 2),
            'original_price' => $original,
            'discount_pct'   => round($actualDiscount, 1),
            'savings'        => round(max(0, $original - $price), 2),
        ];
    }

    /**
     * Snap a raw price to the nearest charm-pricing endpoint.
     *
     * Endings used (Thai retail convention):
     *   <฿100         → ends in 9        (29, 39, 49, 59, …, 99)
     *   ฿100-999      → ends in 9        (109, 199, 299, 399, …, 999)
     *   ฿1,000-9,999  → ends in 90       (1190, 1990, 2490, 4990, …)
     *   ≥ ฿10,000     → ends in 900      (14900, 19900, 29900, 99900)
     *
     * The function preserves "9" psychology while keeping snapped values
     * close enough to the curve target that we don't accidentally over-
     * or under-charge by more than one charm-step.
     */
    public function snapToCharm(float $price): float
    {
        if ($price < 30) {
            // Tiny prices: snap to ฿19, ฿29 — anything lower is silly.
            return max(19.0, floor($price / 10) * 10 + 9);
        }

        if ($price < 100) {
            // 35 → 39, 87 → 89, 95 → 99
            return floor($price / 10) * 10 + 9;
        }

        if ($price < 1000) {
            // 270 → 269, 481 → 479 (or 489 if closer), 700 → 699.
            // Round to nearest 10, then -1 to land on a *9 ending.
            return round($price / 10) * 10 - 1;
        }

        if ($price < 10000) {
            // 1200 → 1190, 1240 → 1290, 2400 → 2390, 4990 → 4990.
            // Round to nearest 100, then -10 to land on a *90 ending.
            return round($price / 100) * 100 - 10;
        }

        // ≥ 10k: 14900, 19900, 29900, 49900, 99900.
        return round($price / 1000) * 1000 - 100;
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

        // Outside the curve range → clamp to nearest endpoint.
        if ($count <= $keys[0])     return $first;
        if ($count >= end($keys))   return $last;

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

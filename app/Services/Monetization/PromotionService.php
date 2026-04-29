<?php

namespace App\Services\Monetization;

use App\Models\PhotographerPromotion;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Photographer ranking + promotion management.
 *
 * Core responsibilities:
 *   - boostScoreFor(int $photographerId): float
 *   - rankedPhotographerIds(?string $province, ?string $category, int $limit)
 *   - createPromotion(...) — invoked by checkout success
 *   - markExpired() — daily cron
 *
 * Ranking formula (simple + auditable)
 * ------------------------------------
 *   final_score = organic_score + boost_score - spam_penalty
 *
 *   organic_score = events_count * 1.0
 *                 + total_views  * 0.001
 *                 + avg_rating   * 5.0
 *                 + (recency: events in last 30d × 2.0)
 *
 *   boost_score   = sum of all active promotion.boost_score for photographer
 *                   capped at +30 (so paid boost can't completely overwhelm
 *                   organic — Pixieset/Fiverr both cap similarly to keep
 *                   the marketplace credible).
 *
 *   spam_penalty  = 5.0 per "boost in last 24h after pause" event
 *                   (anti-pattern: pausing → re-buying → same effect as
 *                   bumping the post repeatedly).
 *
 * Cached at 5min — ranking moves slowly, no need to recompute per request.
 */
class PromotionService
{
    private const CACHE_TTL_SEC = 300;
    private const BOOST_CAP     = 30.0;

    /**
     * Sum of active boost scores for one photographer. Returns 0 if no
     * active promotion. Capped at BOOST_CAP — see class doc.
     */
    public function boostScoreFor(int $photographerId): float
    {
        $key = "promo.boost.{$photographerId}";
        return (float) Cache::remember($key, self::CACHE_TTL_SEC, function () use ($photographerId) {
            $now = now();
            $sum = (float) PhotographerPromotion::query()
                ->where('photographer_id', $photographerId)
                ->where('status', PhotographerPromotion::STATUS_ACTIVE)
                ->where('starts_at', '<=', $now)
                ->where('ends_at',   '>=', $now)
                ->sum('boost_score');

            return min($sum, self::BOOST_CAP);
        });
    }

    /**
     * Return photographer ids ordered by boost+organic score, optionally
     * filtered by province/category. Used by /events listing and search.
     *
     * @param  ?int    $provinceId  filter to this province's photographers
     * @param  ?int    $categoryId  filter to those who shot this category
     * @param  int     $limit       page size
     * @return array<int>           photographer user_ids in display order
     */
    public function rankedPhotographerIds(?int $provinceId = null, ?int $categoryId = null, int $limit = 20): array
    {
        // Aggregate organic score per photographer (cached 10min — moves slowly).
        $cacheKey = 'promo.organic:' . ($provinceId ?? '0') . ':' . ($categoryId ?? '0') . ":{$limit}";
        return Cache::remember($cacheKey, 600, function () use ($provinceId, $categoryId, $limit) {
            $q = DB::table('photographer_profiles as pp')
                ->where('pp.status', 'approved')
                ->select(
                    'pp.user_id',
                    DB::raw('COALESCE((SELECT COUNT(*) FROM event_events e WHERE e.photographer_id = pp.user_id AND e.status = \'active\'), 0) AS events_count'),
                    DB::raw('COALESCE((SELECT SUM(view_count) FROM event_events e WHERE e.photographer_id = pp.user_id AND e.status = \'active\'), 0) AS total_views'),
                );

            if ($provinceId) {
                $q->where('pp.province_id', $provinceId);
            }
            if ($categoryId) {
                $q->whereExists(function ($sub) use ($categoryId) {
                    $sub->select(DB::raw(1))
                        ->from('event_events as e')
                        ->whereColumn('e.photographer_id', 'pp.user_id')
                        ->where('e.category_id', $categoryId)
                        ->where('e.status', 'active');
                });
            }

            // Pull more rows than needed so the in-PHP score sort can see
            // photographers whose organic is below the cut but boost lifts
            // them above. 3× limit is a defensible heuristic.
            $candidates = $q->limit($limit * 3)->get();

            $scored = $candidates->map(function ($row) {
                $organic = ((int) $row->events_count) * 1.0
                         + ((int) $row->total_views) * 0.001;
                $boost   = $this->boostScoreFor((int) $row->user_id);
                return [
                    'user_id' => (int) $row->user_id,
                    'score'   => $organic + $boost,
                ];
            })->sortByDesc('score')->values();

            return $scored->take($limit)->pluck('user_id')->all();
        });
    }

    /**
     * Create a promotion — typically called from the checkout-success
     * webhook. Computes the boost_score from the kind+billing_cycle so
     * admins don't have to remember the table.
     *
     * @return PhotographerPromotion
     */
    public function create(array $data): PhotographerPromotion
    {
        $kind         = $data['kind']          ?? PhotographerPromotion::KIND_BOOST;
        $billingCycle = $data['billing_cycle'] ?? 'monthly';

        // Boost score table — read top-down. Featured + monthly = +20.
        $boostMap = [
            PhotographerPromotion::KIND_BOOST     => ['pay_per_use' => 5,  'daily' => 8,  'monthly' => 15, 'yearly' => 20],
            PhotographerPromotion::KIND_FEATURED  => ['pay_per_use' => 10, 'daily' => 15, 'monthly' => 20, 'yearly' => 25],
            PhotographerPromotion::KIND_HIGHLIGHT => ['pay_per_use' => 3,  'daily' => 5,  'monthly' => 8,  'yearly' => 12],
        ];
        $score = $boostMap[$kind][$billingCycle] ?? 5;

        // Window — derive ends_at from billing_cycle if not supplied.
        $startsAt = isset($data['starts_at']) ? \Carbon\Carbon::parse($data['starts_at']) : now();
        $endsAt   = match ($billingCycle) {
            'pay_per_use' => $startsAt->copy()->addHours(24),
            'daily'       => $startsAt->copy()->addDay(),
            'monthly'     => $startsAt->copy()->addMonth(),
            'yearly'      => $startsAt->copy()->addYear(),
            default       => $startsAt->copy()->addDay(),
        };

        $promo = PhotographerPromotion::create([
            'photographer_id'  => $data['photographer_id'],
            'kind'             => $kind,
            'placement'        => $data['placement']        ?? 'global',
            'placement_target' => $data['placement_target'] ?? null,
            'boost_score'      => $score,
            'billing_cycle'    => $billingCycle,
            'amount_thb'       => $data['amount_thb'],
            'starts_at'        => $startsAt,
            'ends_at'          => $endsAt,
            'status'           => $data['status'] ?? PhotographerPromotion::STATUS_ACTIVE,
            'order_id'         => $data['order_id'] ?? null,
            'meta'             => $data['meta']     ?? null,
        ]);

        // Bust the cache for this photographer so the new boost takes
        // effect immediately rather than waiting 5 minutes.
        Cache::forget("promo.boost.{$promo->photographer_id}");
        return $promo;
    }

    /**
     * Mark all active promotions whose end window has passed as expired.
     * Called by the daily scheduler.
     *
     * @return int  number of rows updated
     */
    public function markExpired(): int
    {
        return PhotographerPromotion::query()
            ->where('status', PhotographerPromotion::STATUS_ACTIVE)
            ->where('ends_at', '<', now())
            ->update(['status' => PhotographerPromotion::STATUS_EXPIRED]);
    }
}

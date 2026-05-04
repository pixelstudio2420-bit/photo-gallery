<?php

namespace App\Services\Ranking;

use App\Models\PhotographerPromotion;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Boost-aware ordering for photographer + event listings.
 *
 * Reads active rows from `photographer_promotions` and produces a
 * boost score per photographer (sum of active boost_score across all
 * active promotions). Public list queries can either:
 *
 *   • applyToQuery($q, 'photographer_id') — joins the boost map onto
 *     a query so ORDER BY can sort photographers by boost first.
 *   • boostMap()                          — returns [photographer_id => score]
 *     for callers that load rows then re-sort in PHP.
 *
 * The boost map is cached for 60s — a brand-new boost takes up to a
 * minute to take effect, which is acceptable for a paid promotion
 * (admins set the schedule, not the photographer).
 *
 * Featured + Highlight kinds also contribute their boost_score, so
 * the kind distinction matters only for visual treatment (badge,
 * border) — not for ordering.
 */
class PhotographerRankingService
{
    private const CACHE_KEY = 'ranking.photographer_boost_map';
    private const CACHE_TTL = 60;

    /**
     * Per-photographer boost cap. Mirrors PromotionService::BOOST_CAP so
     * the ranking layer and the boost-score-for-one-photographer helper
     * can never disagree on the maximum effective boost.
     *
     * Without this cap, a photographer who buys 5 active boosts
     * simultaneously gets boost_score 5×15 = 75 — which:
     *   • lets paid placement completely overwhelm organic ranking
     *     (the original product principle was "boost amplifies, doesn't
     *     replace, organic merit")
     *   • diverges from PromotionService::boostScoreFor() which DID
     *     correctly cap at 30, creating two different boost numbers
     *     for the same photographer depending on which call site
     *     consumed them — directly contradicting the design contract
     *     in PromotionService class doc.
     */
    public const BOOST_CAP = 30.0;

    /**
     * @return array<int, float>  photographer_id ⇒ boost score (capped)
     */
    public function boostMap(): array
    {
        try {
            return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
                $now = now();
                $rows = DB::table('photographer_promotions')
                    ->where('status', PhotographerPromotion::STATUS_ACTIVE)
                    ->where(function ($q) use ($now) {
                        $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
                    })
                    ->where(function ($q) use ($now) {
                        $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
                    })
                    ->select('photographer_id', DB::raw('COALESCE(SUM(boost_score), 0) AS total_boost'))
                    ->groupBy('photographer_id')
                    ->pluck('total_boost', 'photographer_id')
                    ->all();
                // Apply the per-photographer cap in PHP. Doing it here (vs
                // a LEAST() in SQL) keeps the query portable across pgsql
                // / mysql / sqlite — LEAST has subtle behavioural diffs.
                return array_map(fn ($v) => min((float) $v, self::BOOST_CAP), $rows);
            });
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Re-sort an already-loaded collection of photographer-like rows by
     * boost score (DESC), preserving the input order as the tie-break.
     *
     * Use this when the calling controller has already run a complex
     * query (with cache, filters, joins) and only needs a final
     * boost-aware re-sort.
     *
     * @param  iterable  $rows                      Collection / array of rows
     * @param  string    $idKey                     Property holding the photographer id ('user_id' / 'photographer_id')
     * @return \Illuminate\Support\Collection
     */
    public function sortRows(iterable $rows, string $idKey = 'user_id'): \Illuminate\Support\Collection
    {
        $boost = $this->boostMap();
        $sequence = 0;
        $items = collect($rows)->map(function ($row) use ($boost, $idKey, &$sequence) {
            $id = is_array($row)
                ? ($row[$idKey] ?? null)
                : ($row->{$idKey} ?? null);
            return [
                'row'       => $row,
                'boost'     => $boost[$id] ?? 0,
                'order'     => $sequence++,
            ];
        });
        return $items
            ->sortBy([
                ['boost', 'desc'],   // boosted first
                ['order', 'asc'],    // then preserve original order
            ])
            ->pluck('row')
            ->values();
    }

    /**
     * Append an `addSelect` boost expression + ORDER BY to a query
     * builder. The query must alias the photographer-id column as
     * specified, and we sub-select from photographer_promotions
     * grouped by photographer.
     *
     * Use this when the controller wants the DB to do the ordering —
     * faster on large lists where re-sorting in PHP would mean loading
     * everything into memory first.
     *
     * @param  EloquentBuilder|QueryBuilder  $q
     * @param  string  $idColumn   Fully-qualified column ('u.id' / 'pp.photographer_id')
     */
    public function applyToQuery($q, string $idColumn)
    {
        // Subquery to flatten active boosts into one row per photographer.
        // We use a left join so non-boosted photographers get NULL → 0.
        // SQLite doesn't have NOW() — use CURRENT_TIMESTAMP which is
        // portable across Postgres/MySQL/SQLite.
        //
        // CASE WHEN sum > cap THEN cap ELSE sum END applies BOOST_CAP at
        // the SQL layer so the DB-driven sort path (this method) agrees
        // with the in-memory boostMap() path on the maximum boost score.
        // Using CASE instead of LEAST() because LEAST has different
        // behaviour with NULLs across pgsql/mysql.
        $cap = (int) self::BOOST_CAP;
        $sub = "(SELECT photographer_id,
                        CASE WHEN COALESCE(SUM(boost_score), 0) > {$cap}
                             THEN {$cap}
                             ELSE COALESCE(SUM(boost_score), 0)
                        END AS total_boost
                   FROM photographer_promotions
                  WHERE status = 'active'
                    AND (starts_at IS NULL OR starts_at <= CURRENT_TIMESTAMP)
                    AND (ends_at   IS NULL OR ends_at   >= CURRENT_TIMESTAMP)
                  GROUP BY photographer_id) AS rb";

        $q->leftJoin(DB::raw($sub), 'rb.photographer_id', '=', DB::raw($idColumn));

        // CRITICAL: must preserve the original `*` selection. addSelect()
        // on an Eloquent query that has no prior ->select() acts like a
        // REPLACE — the resulting SQL becomes `SELECT total_boost FROM …`
        // which loses every base column. Resolving the table name from the
        // query lets us anchor an explicit `{table}.*` so addSelect can
        // safely append.
        if ($q instanceof EloquentBuilder) {
            $tableName = $q->getModel()->getTable();
            $q->select($tableName . '.*')
              ->addSelect(DB::raw('COALESCE(rb.total_boost, 0) AS total_boost'));
        } else {
            // Raw query builder — caller is expected to have set up
            // selects already. Just append the boost column.
            $q->addSelect(DB::raw('COALESCE(rb.total_boost, 0) AS total_boost'));
        }

        // Boost-first ordering. Caller is responsible for chaining
        // additional orderBy() calls (e.g. 'recent first' as tie-break).
        $q->orderByDesc(DB::raw('COALESCE(rb.total_boost, 0)'));

        return $q;
    }

    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}

<?php

namespace App\Services\Marketing;

use App\Models\Marketing\MarketingEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Marketing Analytics v2 — funnel, cohort, LTV, ROAS.
 *
 * Designed to stay cheap: all queries use the 'marketing_events' table
 * with indexes on (event_name, occurred_at). Heavy computations should
 * be wrapped in a Cache::remember() layer by the calling controller.
 */
class MarketingAnalyticsService
{
    public function __construct(protected MarketingService $marketing)
    {
    }

    public function enabled(): bool
    {
        return $this->marketing->enabled('analytics');
    }

    /** Fire an event (no-op if analytics v2 disabled). */
    public function track(string $name, array $data = []): ?MarketingEvent
    {
        if (! $this->enabled()) return null;

        $payload = array_merge([
            'event_name'  => $name,
            'occurred_at' => now(),
        ], array_intersect_key($data, array_flip([
            'user_id', 'session_id', 'url', 'referrer',
            'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term',
            'lp_id', 'campaign_id', 'push_campaign_id', 'order_id',
            'value', 'currency', 'meta', 'ip', 'country', 'device',
        ])));

        try {
            return MarketingEvent::create($payload);
        } catch (\Throwable $e) {
            // analytics must never break the user request
            return null;
        }
    }

    /**
     * Funnel across a sequence of event names.
     * Returns: [ step => [name, count, rate_from_first, rate_from_prev] ]
     */
    public function funnel(array $stepNames, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $from ??= now()->subDays(30)->startOfDay();
        $to   ??= now()->endOfDay();

        $counts = [];
        foreach ($stepNames as $name) {
            $counts[$name] = MarketingEvent::where('event_name', $name)
                ->whereBetween('occurred_at', [$from, $to])
                ->distinct('session_id')
                ->count('session_id');
        }

        $first = $counts[array_key_first($counts)] ?? 0;
        $out = [];
        $prev = $first;
        foreach ($counts as $name => $count) {
            $out[] = [
                'name'           => $name,
                'count'          => $count,
                'rate_from_first'=> $first > 0 ? round($count / $first * 100, 2) : 0.0,
                'rate_from_prev' => $prev  > 0 ? round($count / $prev  * 100, 2) : 0.0,
            ];
            $prev = max($count, 1);
        }
        return $out;
    }

    /**
     * ROAS by source/medium/campaign.
     * Matches utm_source events to purchase revenue.
     */
    public function roas(?Carbon $from = null, ?Carbon $to = null): Collection
    {
        $from ??= now()->subDays(30)->startOfDay();
        $to   ??= now()->endOfDay();

        return MarketingEvent::select(
                'utm_source',
                'utm_medium',
                'utm_campaign',
                DB::raw('COUNT(*) as purchases'),
                DB::raw('COALESCE(SUM(value),0) as revenue')
            )
            ->where('event_name', MarketingEvent::EV_PURCHASE)
            ->whereBetween('occurred_at', [$from, $to])
            ->groupBy('utm_source', 'utm_medium', 'utm_campaign')
            ->orderByDesc('revenue')
            ->limit(50)
            ->get();
    }

    /**
     * Cohort retention — % of users (per signup week) who purchase in
     * subsequent weeks.
     * Returns: [ 'YYYY-WW' => [ size, w0, w1, w2, w3 ] ]
     */
    public function weeklyCohort(int $weeks = 8): array
    {
        $start = now()->subWeeks($weeks)->startOfWeek();

        $driver = DB::connection()->getDriverName();
        $cohortExpr = match ($driver) {
            'sqlite' => "strftime('%Y-W%W', occurred_at)",
            'pgsql'  => "to_char(occurred_at, 'IYYY\"-W\"IW')",
            default  => "DATE_FORMAT(occurred_at,'%x-W%v')",
        };

        $signups = MarketingEvent::select(
                'user_id',
                DB::raw("{$cohortExpr} as cohort")
            )
            ->where('event_name', MarketingEvent::EV_SIGNUP)
            ->whereNotNull('user_id')
            ->where('occurred_at', '>=', $start)
            ->distinct()
            ->get()
            ->groupBy('cohort');

        $purchases = MarketingEvent::select('user_id', 'occurred_at')
            ->where('event_name', MarketingEvent::EV_PURCHASE)
            ->whereNotNull('user_id')
            ->where('occurred_at', '>=', $start)
            ->get();

        $out = [];
        foreach ($signups as $cohort => $rows) {
            $size = $rows->count();
            $buckets = array_fill(0, 5, 0); // w0..w4
            $signupWeekDate = Carbon::createFromFormat('xi\-\WW', str_replace('-W', '-W', $cohort));
            // Fallback parse:
            // Use ISO year-week: take first signup date in cohort
            $first = $rows->first();
            try {
                $signupWeekDate = Carbon::parse($first['occurred_at'] ?? now())->startOfWeek();
            } catch (\Throwable $e) {
                $signupWeekDate = now();
            }

            $userIds = $rows->pluck('user_id')->unique()->all();

            foreach ($purchases as $p) {
                if (! in_array($p->user_id, $userIds)) continue;
                $diff = Carbon::parse($p->occurred_at)->startOfWeek()->diffInWeeks($signupWeekDate);
                $i = (int) $diff;
                if ($i >= 0 && $i < 5) $buckets[$i]++;
            }

            $pct = array_map(fn ($n) => $size > 0 ? round($n / $size * 100, 1) : 0.0, $buckets);
            $out[$cohort] = [
                'size'  => $size,
                'w0'    => $pct[0], 'w1' => $pct[1], 'w2' => $pct[2],
                'w3'    => $pct[3], 'w4' => $pct[4],
            ];
        }

        ksort($out);
        return $out;
    }

    /**
     * Lifetime Value buckets by acquisition source.
     * Returns average revenue per customer grouped by utm_source.
     */
    public function ltvBySource(): Collection
    {
        return MarketingEvent::select(
                'utm_source',
                DB::raw('COUNT(DISTINCT user_id) as customers'),
                DB::raw('COALESCE(SUM(value),0) as total_revenue'),
                DB::raw('COALESCE(AVG(value),0) as avg_order')
            )
            ->where('event_name', MarketingEvent::EV_PURCHASE)
            ->whereNotNull('user_id')
            ->whereNotNull('utm_source')
            ->groupBy('utm_source')
            ->orderByDesc('total_revenue')
            ->limit(20)
            ->get()
            ->map(function ($row) {
                $row->ltv = $row->customers > 0
                    ? round($row->total_revenue / $row->customers, 2)
                    : 0.0;
                return $row;
            });
    }

    /** Daily event counts for a line chart. */
    public function dailySeries(string $eventName, int $days = 30): array
    {
        $from = now()->subDays($days - 1)->startOfDay();

        $rows = MarketingEvent::select(
                DB::raw('DATE(occurred_at) as d'),
                DB::raw('COUNT(*) as c')
            )
            ->where('event_name', $eventName)
            ->where('occurred_at', '>=', $from)
            ->groupBy('d')
            ->orderBy('d')
            ->get()
            ->keyBy(fn ($r) => (string) $r->d);

        $series = [];
        for ($i = 0; $i < $days; $i++) {
            $d = $from->copy()->addDays($i)->toDateString();
            $series[] = [
                'date'  => $d,
                'count' => (int) ($rows[$d]->c ?? 0),
            ];
        }
        return $series;
    }

    public function overview(): array
    {
        $today    = now()->startOfDay();
        $week     = now()->subDays(7)->startOfDay();
        $month    = now()->subDays(30)->startOfDay();

        $q = fn (string $name, $since) =>
            MarketingEvent::where('event_name', $name)
                ->where('occurred_at', '>=', $since)
                ->count();

        return [
            'page_views_today'  => $q(MarketingEvent::EV_PAGE_VIEW, $today),
            'page_views_week'   => $q(MarketingEvent::EV_PAGE_VIEW, $week),
            'signups_month'     => $q(MarketingEvent::EV_SIGNUP, $month),
            'purchases_month'   => $q(MarketingEvent::EV_PURCHASE, $month),
            'revenue_month'     => (float) MarketingEvent::where('event_name', MarketingEvent::EV_PURCHASE)
                                        ->where('occurred_at', '>=', $month)->sum('value'),
            'top_source'        => MarketingEvent::where('event_name', MarketingEvent::EV_PURCHASE)
                                        ->where('occurred_at', '>=', $month)
                                        ->whereNotNull('utm_source')
                                        ->select('utm_source', DB::raw('COUNT(*) as c'))
                                        ->groupBy('utm_source')->orderByDesc('c')->limit(1)
                                        ->value('utm_source') ?? '—',
        ];
    }

    /** Purge events older than retention horizon (called by cron). */
    public function purgeOldEvents(): int
    {
        $days = (int) \App\Models\AppSetting::get('marketing_event_retention_days', 180);
        if ($days <= 0) return 0;
        return MarketingEvent::where('occurred_at', '<', now()->subDays($days))->delete();
    }
}

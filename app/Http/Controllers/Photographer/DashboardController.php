<?php

namespace App\Http\Controllers\Photographer;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventPhoto;
use App\Models\Order;
use App\Models\PhotographerPayout;
use App\Models\Review;
use App\Services\StorageQuotaService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Photographer dashboard — the landing screen after login.
 *
 * Philosophy: fast + informative. Every stat here is answered in a single
 * aggregate query (no N+1), so the page feels instant even for studios with
 * hundreds of events and thousands of photos. Numbers come in month-over-month
 * pairs wherever it makes sense so the photographer can see whether they are
 * trending up or down at a glance.
 *
 * Dark mode is owned by the blade template — this controller never cares
 * about presentation.
 */
class DashboardController extends Controller
{
    public function index()
    {
        $user    = Auth::user();
        $profile = $user->photographerProfile ?? null;

        // Bare dashboard for photographers who haven't been approved yet
        if (!$profile) {
            return view('photographer.dashboard', [
                'profile'           => null,
                'stats'             => $this->emptyStats(),
                'recentEvents'      => collect(),
                'recentOrders'      => collect(),
                'recentReviews'     => collect(),
                'recentEarnings'    => collect(),
                'monthlyTrend'      => [],
                'topEvents'         => collect(),
                'pendingPayout'     => 0.0,
                'availableBalance'  => 0.0,
                'moderationPending' => 0,
                'driveSyncPending'  => 0,
                'quotaInfo'         => null,
            ]);
        }

        $userId      = $user->id;
        $monthStart  = Carbon::now()->startOfMonth();
        $lastMonth   = (clone $monthStart)->subMonth();
        $lastMonthEnd = (clone $monthStart)->subSecond();

        // ── Event counts (with prior-month delta) ─────────────────────────
        // One aggregate row instead of four round-trips — matters on studio
        // accounts with thousands of events because the dashboard hits this
        // every page load.
        $eventAgg = DB::table('event_events')
            ->where('photographer_id', $userId)
            ->selectRaw(
                'COUNT(*) AS total,
                 SUM(CASE WHEN status IN (?, ?) THEN 1 ELSE 0 END) AS active,
                 SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) AS this_month,
                 SUM(CASE WHEN created_at >= ? AND created_at <= ? THEN 1 ELSE 0 END) AS last_month',
                ['active', 'published', $monthStart, $lastMonth, $lastMonthEnd]
            )
            ->first();

        $eventsTotal     = (int) ($eventAgg->total ?? 0);
        $eventsActive    = (int) ($eventAgg->active ?? 0);
        $eventsThisMonth = (int) ($eventAgg->this_month ?? 0);
        $eventsLastMonth = (int) ($eventAgg->last_month ?? 0);

        // ── Photo counts from the authoritative event_photos table ────────
        // Also breaks down by moderation status so the photographer can see
        // whether they have flagged images to review.
        $photoCounts = DB::table('event_photos')
            ->join('event_events', 'event_photos.event_id', '=', 'event_events.id')
            ->where('event_events.photographer_id', $userId)
            ->selectRaw(
                'COUNT(*) AS total,
                 SUM(CASE WHEN event_photos.status = ? THEN 1 ELSE 0 END) AS active,
                 SUM(CASE WHEN event_photos.moderation_status = ? THEN 1 ELSE 0 END) AS pending_moderation,
                 SUM(CASE WHEN event_photos.moderation_status = ? THEN 1 ELSE 0 END) AS flagged',
                ['active', 'pending', 'flagged']
            )
            ->first();

        $photosTotal       = (int) ($photoCounts->total ?? 0);
        $photosActive      = (int) ($photoCounts->active ?? 0);
        $moderationPending = (int) ($photoCounts->pending_moderation ?? 0) + (int) ($photoCounts->flagged ?? 0);

        // ── Order / sales counts ──────────────────────────────────────────
        // Six separate aggregates → one round-trip. `whereHas` on orders
        // generates a subquery but it's the same subquery all six times;
        // doing it once and reusing via CASE is much cheaper. We inline the
        // EXISTS check here via a JOIN to event_events instead.
        $salesAgg = DB::table('orders')
            ->join('event_events', 'orders.event_id', '=', 'event_events.id')
            ->where('event_events.photographer_id', $userId)
            ->whereIn('orders.status', ['paid', 'completed'])
            ->selectRaw(
                'COUNT(*) AS total,
                 SUM(CASE WHEN orders.created_at >= ? THEN 1 ELSE 0 END) AS this_month,
                 SUM(CASE WHEN orders.created_at >= ? AND orders.created_at <= ? THEN 1 ELSE 0 END) AS last_month,
                 COALESCE(SUM(orders.total), 0) AS revenue_all,
                 COALESCE(SUM(CASE WHEN orders.created_at >= ? THEN orders.total ELSE 0 END), 0) AS revenue_this_month,
                 COALESCE(SUM(CASE WHEN orders.created_at >= ? AND orders.created_at <= ? THEN orders.total ELSE 0 END), 0) AS revenue_last_month',
                [$monthStart, $lastMonth, $lastMonthEnd, $monthStart, $lastMonth, $lastMonthEnd]
            )
            ->first();

        $salesTotal            = (int)   ($salesAgg->total ?? 0);
        $salesThisMonth        = (int)   ($salesAgg->this_month ?? 0);
        $salesLastMonth        = (int)   ($salesAgg->last_month ?? 0);
        $salesRevenueAll       = (float) ($salesAgg->revenue_all ?? 0);
        $salesRevenueThisMonth = (float) ($salesAgg->revenue_this_month ?? 0);
        $salesRevenueLastMonth = (float) ($salesAgg->revenue_last_month ?? 0);

        $avgOrderValue = $salesTotal > 0 ? $salesRevenueAll / $salesTotal : 0.0;

        // ── Payouts / earnings ────────────────────────────────────────────
        // 5 sums in one query — same optimisation pattern as above.
        $payoutAgg = DB::table('photographer_payouts')
            ->where('photographer_id', $userId)
            ->selectRaw(
                'COALESCE(SUM(payout_amount), 0) AS total,
                 COALESCE(SUM(CASE WHEN status = ? THEN payout_amount ELSE 0 END), 0) AS paid,
                 COALESCE(SUM(CASE WHEN status = ? THEN payout_amount ELSE 0 END), 0) AS pending,
                 COALESCE(SUM(CASE WHEN created_at >= ? THEN payout_amount ELSE 0 END), 0) AS this_month,
                 COALESCE(SUM(CASE WHEN created_at >= ? AND created_at <= ? THEN payout_amount ELSE 0 END), 0) AS last_month',
                ['paid', 'pending', $monthStart, $lastMonth, $lastMonthEnd]
            )
            ->first();

        $earningsTotal     = (float) ($payoutAgg->total ?? 0);
        $earningsPaid      = (float) ($payoutAgg->paid ?? 0);
        $pendingPayout     = (float) ($payoutAgg->pending ?? 0);
        $earningsThisMonth = (float) ($payoutAgg->this_month ?? 0);
        $earningsLastMonth = (float) ($payoutAgg->last_month ?? 0);

        // ── Monthly revenue trend for the sparkline (last 6 months) ───────
        // PERF: previously this ran 6 separate `whereBetween + sum`
        // queries in a loop — 6 round-trips on every dashboard load.
        // Now we run a single GROUP BY YEAR(),MONTH() query and fill
        // missing months with 0. One round-trip regardless of range.
        $sixMonthsAgo = Carbon::now()->startOfMonth()->subMonths(5);
        // Year/month extraction differs per driver. Keep one expression per
        // dialect and pick at runtime — no userland data flows into the SQL,
        // so this stays safe.
        $driver  = DB::connection()->getDriverName();
        $yearMo  = match ($driver) {
            'pgsql'           => "EXTRACT(YEAR FROM created_at)::int as y, EXTRACT(MONTH FROM created_at)::int as m",
            'mysql', 'mariadb'=> "YEAR(created_at) as y, MONTH(created_at) as m",
            'sqlite'          => "CAST(strftime('%Y', created_at) AS INTEGER) as y, CAST(strftime('%m', created_at) AS INTEGER) as m",
            default           => "EXTRACT(YEAR FROM created_at) as y, EXTRACT(MONTH FROM created_at) as m",
        };
        $monthlyRows = Order::whereHas('event', fn($q) => $q->where('photographer_id', $userId))
            ->whereIn('status', ['paid', 'completed'])
            ->where('created_at', '>=', $sixMonthsAgo)
            ->selectRaw("$yearMo, SUM(total) as total")
            ->groupBy('y', 'm')
            ->get()
            ->keyBy(fn($r) => $r->y . '-' . str_pad($r->m, 2, '0', STR_PAD_LEFT));

        $monthlyTrend = [];
        for ($i = 5; $i >= 0; $i--) {
            $when = Carbon::now()->startOfMonth()->subMonths($i);
            $key  = $when->format('Y-m');
            $monthlyTrend[] = [
                'label' => $when->translatedFormat('M'),
                'value' => (float) ($monthlyRows[$key]->total ?? 0),
            ];
        }

        // ── Recent events (with order count for the table) ────────────────
        $recentEvents = Event::where('photographer_id', $userId)
            ->withCount([
                'orders as order_count' => fn($q) => $q->whereIn('status', ['paid', 'completed']),
            ])
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        // ── Top-earning events (lifetime) ─────────────────────────────────
        $topEvents = Event::where('photographer_id', $userId)
            ->withSum([
                'orders as total_revenue' => fn($q) => $q->whereIn('status', ['paid', 'completed']),
            ], 'total')
            ->orderByDesc('total_revenue')
            ->limit(5)
            ->get()
            ->filter(fn($e) => (float) $e->total_revenue > 0)
            ->values();

        // ── Recent paid orders (with event name) ──────────────────────────
        $recentOrders = Order::with(['event:id,name,slug,cover_image', 'user:id,first_name,last_name,email'])
            ->whereHas('event', fn($q) => $q->where('photographer_id', $userId))
            ->whereIn('status', ['paid', 'completed'])
            ->orderByDesc('created_at')
            ->limit(6)
            ->get();

        // ── Recent reviews ────────────────────────────────────────────────
        $recentReviews = collect();
        if (Schema::hasTable('reviews')) {
            try {
                $recentReviews = Review::where('photographer_id', $userId)
                    ->with(['user:id,first_name,last_name'])
                    ->orderByDesc('created_at')
                    ->limit(4)
                    ->get();
            } catch (\Throwable) {
                // Reviews table may not be present in every environment
            }
        }

        // ── Recent earnings list (for the sidebar card) ───────────────────
        $recentEarnings = PhotographerPayout::with(['order:id,event_id', 'order.event:id,name'])
            ->where('photographer_id', $userId)
            ->orderByDesc('created_at')
            ->limit(6)
            ->get()
            ->map(fn($p) => (object) [
                'order_id'    => $p->order_id,
                'event_title' => $p->order?->event?->name,
                'amount'      => (float) $p->payout_amount,
                'status'      => $p->status,
                'created_at'  => $p->created_at,
            ]);

        // ── Drive sync pending (if Drive is in use) ───────────────────────
        $driveSyncPending = 0;
        if (Schema::hasTable('sync_queue')) {
            try {
                $driveSyncPending = (int) DB::table('sync_queue')
                    ->join('event_events', 'sync_queue.event_id', '=', 'event_events.id')
                    ->where('event_events.photographer_id', $userId)
                    ->whereIn('sync_queue.status', ['pending', 'running'])
                    ->count();
            } catch (\Throwable) {
            }
        }

        $stats = [
            'events' => [
                'total'     => $eventsTotal,
                'active'    => $eventsActive,
                'this_mo'   => $eventsThisMonth,
                'last_mo'   => $eventsLastMonth,
                'delta_pct' => $this->deltaPct($eventsThisMonth, $eventsLastMonth),
            ],
            'photos' => [
                'total'              => $photosTotal,
                'active'             => $photosActive,
                'pending_moderation' => $moderationPending,
            ],
            'sales' => [
                'total'     => $salesTotal,
                'this_mo'   => $salesThisMonth,
                'last_mo'   => $salesLastMonth,
                'delta_pct' => $this->deltaPct($salesThisMonth, $salesLastMonth),
                'aov'       => $avgOrderValue,
            ],
            'revenue' => [
                'all'       => $salesRevenueAll,
                'this_mo'   => $salesRevenueThisMonth,
                'last_mo'   => $salesRevenueLastMonth,
                'delta_pct' => $this->deltaPct($salesRevenueThisMonth, $salesRevenueLastMonth),
            ],
            'earnings' => [
                'total'     => $earningsTotal,
                'paid'      => $earningsPaid,
                'pending'   => $pendingPayout,
                'this_mo'   => $earningsThisMonth,
                'last_mo'   => $earningsLastMonth,
                'delta_pct' => $this->deltaPct($earningsThisMonth, $earningsLastMonth),
            ],
        ];

        // ── Storage quota info (for the widget) ───────────────────────────
        // Fail-soft: if the service isn't wired up, the widget simply won't
        // render — dashboard still loads.
        $quotaInfo = null;
        try {
            $quota = app(StorageQuotaService::class);
            $quotaInfo = [
                'enabled'        => $quota->enforcementEnabled(),
                'tier'           => $profile->tier ?? 'creator',
                'used_bytes'     => (int) ($profile->storage_used_bytes ?? 0),
                'quota_bytes'    => $quota->quotaFor($profile),
                'percent'        => $quota->percentUsed($profile),
                'used_human'     => $quota->humanBytes((int) ($profile->storage_used_bytes ?? 0)),
                'quota_human'    => $quota->humanBytes($quota->quotaFor($profile)),
                'warn_threshold' => (int) \App\Models\AppSetting::get('photographer_quota_warn_threshold_pct', '80'),
                'savings'        => $quota->upgradeSavings($profile->tier ?? 'creator'),
            ];
        } catch (\Throwable) {
            // Service missing or misconfigured — skip widget silently.
        }

        // ── Upload credits info (for the credits widget) ──────────────────
        // Only populated when credits system is online; the blade partial
        // itself hides for commission-mode photographers.
        $creditsInfo = null;
        try {
            $creditsInfo = app(\App\Services\CreditService::class)->dashboardSummary($profile);
        } catch (\Throwable) {
            // Silent fail — widget won't render, dashboard stays up.
        }

        // ── Subscription info (for the subscription widget) ───────────────
        // Only populated when subscription system is online; the blade partial
        // hides itself if the system is globally disabled.
        $subscriptionInfo = null;
        try {
            $subscriptionInfo = app(\App\Services\SubscriptionService::class)->dashboardSummary($profile);
        } catch (\Throwable) {
            // Silent fail — widget won't render, dashboard stays up.
        }

        return view('photographer.dashboard', compact(
            'profile',
            'stats',
            'recentEvents',
            'recentOrders',
            'recentReviews',
            'recentEarnings',
            'monthlyTrend',
            'topEvents',
            'pendingPayout',
            'moderationPending',
            'driveSyncPending',
            'quotaInfo',
            'creditsInfo',
            'subscriptionInfo'
        ));
    }

    /** Safe percent-change between two values — returns null when prior is 0. */
    private function deltaPct(float|int $now, float|int $prior): ?float
    {
        if ($prior <= 0) return $now > 0 ? null : 0.0;
        return round((($now - $prior) / $prior) * 100, 1);
    }

    private function emptyStats(): array
    {
        return [
            'events'   => ['total' => 0, 'active' => 0, 'this_mo' => 0, 'last_mo' => 0, 'delta_pct' => 0.0],
            'photos'   => ['total' => 0, 'active' => 0, 'pending_moderation' => 0],
            'sales'    => ['total' => 0, 'this_mo' => 0, 'last_mo' => 0, 'delta_pct' => 0.0, 'aov' => 0.0],
            'revenue'  => ['all' => 0.0, 'this_mo' => 0.0, 'last_mo' => 0.0, 'delta_pct' => 0.0],
            'earnings' => ['total' => 0.0, 'paid' => 0.0, 'pending' => 0.0, 'this_mo' => 0.0, 'last_mo' => 0.0, 'delta_pct' => 0.0],
        ];
    }
}

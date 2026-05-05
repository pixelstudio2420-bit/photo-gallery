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

        // ── Feature-by-feature status (the redesigned widget consumes this)
        // For every feature the platform exposes, decide:
        //   • is it in this photographer's plan?
        //   • is the global flag on (admin kill switch)?
        //   • what's the cheapest plan that grants it (for the upgrade label)?
        //   • does the live PlanGate currently allow it?
        //   • for AI features, what's the per-month usage?
        // This array drives the "ฟีเจอร์ที่ใช้งานได้ / ฟีเจอร์ที่ล็อค" sections
        // of the subscription-widget partial — single source of truth for the
        // dashboard's feature visibility.
        $featureStatus = [];
        try {
            $featureStatus = $this->buildFeatureStatus($profile, $subscriptionInfo);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('buildFeatureStatus failed: ' . $e->getMessage());
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
            'subscriptionInfo',
            'featureStatus'
        ));
    }

    /**
     * Live JSON snapshot of the subscription widget — used by the
     * dashboard's auto-refresh polling. Returns the same shape the
     * blade partial consumes (plan + storage + events + AI credits +
     * featureStatus) so the JS can patch every meter without re-render.
     *
     * Cached for 5 seconds at the response layer so a hot poll loop
     * (5+ tabs open, 30s interval each) doesn't hammer the DB. Cache
     * is keyed per-user so different photographers don't share state.
     */
    public function subscriptionSummaryJson()
    {
        $user    = Auth::user();
        $profile = $user?->photographerProfile ?? null;
        if (!$profile) {
            return response()->json(['error' => 'no_profile'], 404);
        }

        $cacheKey = 'photographer_sub_summary_' . $user->id;
        $payload = \Illuminate\Support\Facades\Cache::remember(
            $cacheKey,
            5, // seconds
            function () use ($profile) {
                $svc = app(\App\Services\SubscriptionService::class);
                $subscriptionInfo = $svc->dashboardSummary($profile);
                $featureStatus    = $this->buildFeatureStatus($profile, $subscriptionInfo);

                // Slim the payload to fields the JS actually patches.
                // Plan / subscription model objects can't be JSON-serialised
                // safely (lazy relations + accessors), so we project them.
                $plan = $subscriptionInfo['plan'] ?? null;
                return [
                    'fetched_at' => now()->toIso8601String(),
                    'plan'       => $plan ? [
                        'code'  => $plan->code,
                        'name'  => $plan->name,
                        'badge' => $plan->badge,
                        'icon'  => $plan->iconClass(),
                        'price_thb' => (float) ($plan->price_thb ?? 0),
                        'color_hex' => $plan->color_hex,
                    ] : null,
                    'storage' => [
                        'used_bytes'  => (int) ($subscriptionInfo['storage_used_bytes']  ?? 0),
                        'quota_bytes' => (int) ($subscriptionInfo['storage_quota_bytes'] ?? 0),
                        'used_gb'     => (float) ($subscriptionInfo['storage_used_gb']  ?? 0),
                        'quota_gb'    => (float) ($subscriptionInfo['storage_quota_gb'] ?? 0),
                        'used_pct'    => (float) ($subscriptionInfo['storage_used_pct'] ?? 0),
                        'warn'        => (bool)  ($subscriptionInfo['storage_warn']     ?? false),
                        'critical'    => (bool)  ($subscriptionInfo['storage_critical'] ?? false),
                    ],
                    'events' => [
                        'used'      => (int)  ($subscriptionInfo['events_used'] ?? 0),
                        'cap'       => $subscriptionInfo['events_cap'] ?? null,
                        'unlimited' => (bool) ($subscriptionInfo['events_unlimited'] ?? false),
                        'used_pct'  => (float)($subscriptionInfo['events_used_pct']  ?? 0),
                    ],
                    'ai_credits' => [
                        'used'      => (int)($subscriptionInfo['ai_credits_used']      ?? 0),
                        'cap'       => (int)($subscriptionInfo['ai_credits_cap']       ?? 0),
                        'remaining' => (int)($subscriptionInfo['ai_credits_remaining'] ?? 0),
                        'used_pct'  => (float)($subscriptionInfo['ai_credits_used_pct'] ?? 0),
                    ],
                    'commission' => [
                        'platform_pct'     => (float)($subscriptionInfo['commission_pct']         ?? 0),
                        'photographer_pct' => (float)($subscriptionInfo['photographer_share_pct'] ?? 100),
                    ],
                    'state' => [
                        'is_free'              => (bool) ($subscriptionInfo['is_free']              ?? true),
                        'has_active_paid'      => (bool) ($subscriptionInfo['has_active_paid']      ?? false),
                        'in_grace'             => (bool) ($subscriptionInfo['in_grace']             ?? false),
                        'cancel_at_period_end' => (bool) ($subscriptionInfo['cancel_at_period_end'] ?? false),
                        'days_until_renewal'   => $subscriptionInfo['days_until_renewal']           ?? null,
                        'current_period_end'   => optional($subscriptionInfo['current_period_end'])->toIso8601String(),
                        'grace_ends_at'        => optional($subscriptionInfo['grace_ends_at'])->toIso8601String(),
                    ],
                    'features' => array_values(array_map(fn ($r) => [
                        'code'           => $r['code'],
                        'label'          => $r['label'],
                        'icon'           => $r['icon'],
                        'group'          => $r['group'],
                        'in_plan'        => $r['in_plan'],
                        'available'      => $r['available'],
                        'live_ok'        => $r['live_ok'],
                        'blocked_reason' => $r['blocked_reason'],
                        'usage'          => $r['usage'],
                        'upgrade_to'     => $r['upgrade_to'],
                    ], $featureStatus)),
                    'counts' => [
                        'available' => collect($featureStatus)->filter(fn ($r) => $r['available'] && $r['live_ok'] !== false)->count(),
                        'blocked'   => collect($featureStatus)->filter(fn ($r) => $r['in_plan'] && $r['live_ok'] === false)->count(),
                        'locked'    => collect($featureStatus)->filter(fn ($r) => !$r['in_plan'])->count(),
                    ],
                ];
            }
        );

        return response()->json($payload);
    }

    /**
     * Build a feature-by-feature status array for the subscription widget.
     *
     * For each registered feature (FeatureFlagController::featureLabels()),
     * resolves four answers:
     *   1. in_plan          — appears in the photographer's plan ai_features
     *   2. globally_enabled — admin's master switch (feature_<f>_enabled)
     *   3. available        — both above (the practical "you can use this")
     *   4. blocked_reason   — when in_plan is true but PlanGate refuses live
     *                         (over cap, plan expired, global flag flipped)
     *
     * Plus per-feature data:
     *   • icon       — bi-* class for the row
     *   • group      — ai / line / workflow / branding / platform (for filtering)
     *   • upgrade_to — name of the cheapest plan that includes this feature
     *                  when the photographer doesn't have it (drives the
     *                  "Upgrade to Pro to unlock" CTA).
     *   • usage      — for AI features only: ['used' => N, 'cap' => M, 'pct' => P]
     *                  shared across all AI resources to match the budget model.
     */
    private function buildFeatureStatus(\App\Models\PhotographerProfile $profile, ?array $summary): array
    {
        $subs = app(\App\Services\SubscriptionService::class);
        $plan = $summary['plan'] ?? null;
        $planFeatures = is_array($plan?->ai_features ?? null) ? $plan->ai_features : [];

        // Pre-load all features that map to a label — anything else is a
        // legacy code we don't surface (FeatureFlagController is the
        // canonical list).
        $labels = \App\Http\Controllers\Admin\FeatureFlagController::featureLabels();

        // Look up "cheapest plan for each feature" once. Used to render
        // upgrade hints like "Pro+" / "Business+" next to locked rows.
        $allPlans = \App\Models\SubscriptionPlan::query()
            ->where('is_active', true)
            ->where('is_public', true)
            ->orderBy('price_thb')
            ->get(['code', 'name', 'price_thb', 'ai_features']);
        $cheapestForFeature = [];
        foreach ($allPlans as $p) {
            foreach ((array) $p->ai_features as $feat) {
                if (!isset($cheapestForFeature[$feat])) {
                    $cheapestForFeature[$feat] = $p->name;
                }
            }
        }

        // Live AI usage — same shared budget that PlanGate enforces.
        $aiUsed = $profile->user_id
            ? \App\Support\PlanGate::aiCreditsUsedThisMonth((int) $profile->user_id)
            : 0;
        $aiCap = (int) ($plan?->monthly_ai_credits ?? 0);
        $aiPct = $aiCap > 0 ? min(100, round(($aiUsed / $aiCap) * 100, 1)) : 0;

        // Live LINE gate — boolean, no usage counter we trust right now.
        $canLineLive = $profile->user_id
            ? \App\Support\PlanGate::canUseLine((int) $profile->user_id)
            : false;

        $rows = [];
        foreach ($labels as $code => $meta) {
            // featureLabels() returns either [label, icon, group] or [label, group].
            // Normalise so downstream code doesn't have to branch.
            $label = $meta[0] ?? $code;
            $icon  = $meta[1] ?? 'bi-circle';
            $group = $meta[2] ?? 'ai';
            // If meta is 2-tuple [label, group], shift the icon out.
            if (!str_starts_with((string) $icon, 'bi-')) {
                $group = $icon;
                $icon  = 'bi-circle';
            }

            $inPlan      = in_array($code, $planFeatures, true);
            $globallyOn  = $subs->featureGloballyEnabled($code);
            $available   = $inPlan && $globallyOn;

            $blockedReason = null;
            if ($inPlan && !$globallyOn) {
                $blockedReason = 'feature_disabled_by_admin';
            }

            // Per-feature usage / live gate enrichment.
            $usage = null;
            $liveOk = null;
            if ($code === 'face_search'
                || $code === 'quality_filter'
                || $code === 'duplicate_detection'
                || $code === 'auto_tagging'
                || $code === 'best_shot'
                || $code === 'color_enhance'
                || $code === 'smart_captions'
                || $code === 'ai_preview_limited') {
                // AI feature — show shared monthly_ai_credits budget
                $usage = ['used' => $aiUsed, 'cap' => $aiCap, 'pct' => $aiPct, 'label' => 'AI calls'];
                if ($available && $aiCap > 0 && $aiUsed >= $aiCap) {
                    $blockedReason = 'monthly_cap_reached';
                    $liveOk = false;
                } elseif ($available) {
                    $liveOk = true;
                }
            } elseif ($code === 'line_notify' || $code === 'line_delivery'
                   || $code === 'line_notify_admin' || $code === 'line_notify_customer') {
                // LINE feature — defer to PlanGate's live answer
                $liveOk = $canLineLive;
                if ($inPlan && $globallyOn && !$canLineLive) {
                    $blockedReason = 'plan_inactive';
                }
            } else {
                // Other features (priority_upload, presets, custom_branding,
                // api_access, etc.) — boolean availability only.
                $liveOk = $available;
            }

            $rows[$code] = [
                'code'             => $code,
                'label'            => (string) $label,
                'icon'             => (string) $icon,
                'group'            => (string) $group,
                'in_plan'          => $inPlan,
                'globally_enabled' => $globallyOn,
                'available'        => $available,
                'live_ok'          => $liveOk,
                'blocked_reason'   => $blockedReason,
                'usage'            => $usage,
                'upgrade_to'       => !$inPlan ? ($cheapestForFeature[$code] ?? null) : null,
            ];
        }

        return $rows;
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

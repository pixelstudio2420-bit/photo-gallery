<?php

namespace App\Http\Controllers\Photographer;

use App\Http\Controllers\Controller;
use App\Services\SubscriptionService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    public function index()
    {
        $userId = Auth::id();

        // Plan-feature gate: basic revenue analytics is available to all
        // photographers (existing behaviour), but the deeper "customer
        // analytics" surface (top customers, conversion funnel, behaviour
        // metrics) is gated behind the Business+ feature flag. We pass
        // the boolean to the view so it can render advanced widgets only
        // when allowed.
        $profile = Auth::user()?->photographerProfile;
        $hasCustomerAnalytics = $profile
            ? app(SubscriptionService::class)->canAccessFeature($profile, 'customer_analytics')
            : false;

        // --- Revenue over last 30 days ---
        $dailyRevenue = DB::table('photographer_payouts')
            ->where('photographer_id', $userId)
            ->where('created_at', '>=', now()->subDays(30))
            ->selectRaw('DATE(created_at) as date, SUM(payout_amount) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        $revenueDays = collect();
        for ($i = 29; $i >= 0; $i--) {
            $d = now()->subDays($i)->format('Y-m-d');
            $revenueDays->push([
                'date'  => $d,
                'label' => now()->subDays($i)->format('d M'),
                'total' => (float) ($dailyRevenue[$d]->total ?? 0),
            ]);
        }

        // --- Monthly revenue (last 12 months) ---
        $monthlyRevenue = DB::table('photographer_payouts')
            ->where('photographer_id', $userId)
            ->where('created_at', '>=', now()->subMonths(12))
            ->selectRaw("to_char(created_at, 'YYYY-MM') as month, SUM(payout_amount) as total, SUM(gross_amount) as gross")
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->keyBy('month');

        $revenueMonths = collect();
        for ($i = 11; $i >= 0; $i--) {
            $m = now()->subMonths($i)->format('Y-m');
            $revenueMonths->push([
                'month' => $m,
                'label' => now()->subMonths($i)->translatedFormat('M Y'),
                'total' => (float) ($monthlyRevenue[$m]->total ?? 0),
                'gross' => (float) ($monthlyRevenue[$m]->gross ?? 0),
            ]);
        }

        // --- Summary stats ---
        $totalEarnings = DB::table('photographer_payouts')
            ->where('photographer_id', $userId)->sum('payout_amount');
        $totalGross = DB::table('photographer_payouts')
            ->where('photographer_id', $userId)->sum('gross_amount');
        $totalOrders = DB::table('photographer_payouts')
            ->where('photographer_id', $userId)->count();
        $thisMonthEarnings = DB::table('photographer_payouts')
            ->where('photographer_id', $userId)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('payout_amount');

        // --- Top events by revenue ---
        $topEvents = DB::table('photographer_payouts as pp')
            ->join('orders as o', 'pp.order_id', '=', 'o.id')
            ->join('event_events as e', 'o.event_id', '=', 'e.id')
            ->where('pp.photographer_id', $userId)
            ->selectRaw('e.id, e.name, e.slug, COUNT(pp.id) as order_count, SUM(pp.payout_amount) as revenue')
            ->groupBy('e.id', 'e.name', 'e.slug')
            ->orderByDesc('revenue')
            ->limit(10)
            ->get();

        // --- Order status breakdown ---
        $orderStatuses = DB::table('orders')
            ->whereIn('event_id', function ($q) use ($userId) {
                $q->select('id')->from('event_events')->where('photographer_id', $userId);
            })
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        // --- Photo stats ---
        $totalPhotos = DB::table('event_photos')
            ->whereIn('event_id', function ($q) use ($userId) {
                $q->select('id')->from('event_events')->where('photographer_id', $userId);
            })
            ->count();

        $totalViews = DB::table('event_events')
            ->where('photographer_id', $userId)
            ->sum('view_count');

        // ── Customer Analytics (Business+) — top customers + conversion ──
        $topCustomers = collect();
        $conversionRate = 0;
        $hourlyHeatmap = collect();

        if ($hasCustomerAnalytics) {
            // Top customers by spend (only paid orders on this photographer's events)
            // The User model maps to `auth_users` (no `name` column — only
            // first_name + last_name). Use raw concat in SELECT.
            $topCustomers = DB::table('orders as o')
                ->join('event_events as e', 'o.event_id', '=', 'e.id')
                ->leftJoin('auth_users as u', 'o.user_id', '=', 'u.id')
                ->where('e.photographer_id', $userId)
                ->where('o.status', 'paid')
                ->selectRaw("o.user_id, TRIM(COALESCE(u.first_name, '') || ' ' || COALESCE(u.last_name, '')) as name, u.email, SUM(o.total) as spend, COUNT(o.id) as orders")
                ->groupBy('o.user_id', 'u.first_name', 'u.last_name', 'u.email')
                ->orderByDesc('spend')
                ->limit(10)
                ->get();

            // Conversion funnel — total event views vs paid orders
            $conversionRate = $totalViews > 0
                ? round(($totalOrders / $totalViews) * 100, 2)
                : 0;

            // Hourly heatmap — when do customers actually buy?
            // MySQL uses HOUR(); Postgres uses EXTRACT(HOUR FROM ...).
            $hourExpr = DB::connection()->getDriverName() === 'pgsql'
                ? "EXTRACT(HOUR FROM o.created_at)::int"
                : "HOUR(o.created_at)";
            $hourlyHeatmap = DB::table('orders as o')
                ->join('event_events as e', 'o.event_id', '=', 'e.id')
                ->where('e.photographer_id', $userId)
                ->where('o.status', 'paid')
                ->where('o.created_at', '>=', now()->subDays(60))
                ->selectRaw("{$hourExpr} as hour, COUNT(*) as orders")
                ->groupBy('hour')
                ->orderBy('hour')
                ->get()
                ->keyBy('hour');
        }

        // ─── GA4-powered widgets (optional — only if admin configured) ───
        // Per-photographer traffic sources + geographic breakdown,
        // filtered by pages the photographer's content lives on.
        // We pattern-match `/photographer/{slug}` and `/events/...`
        // sections — coarse but works as long as photographer slugs
        // are unique. Empty arrays when unconfigured / API down.
        $gaService = app(\App\Services\Google\GoogleAnalyticsService::class);
        $trafficSources = [];
        $geoBreakdown   = [];
        if ($gaService->isConfigured() && $profile) {
            $pageFilter = '/photographer/' . ($profile->slug ?: '');
            $trafficSources = $gaService->trafficSources('30daysAgo', 'today', $pageFilter, 8);
            $geoBreakdown   = $gaService->geoBreakdown('30daysAgo', 'today', $pageFilter, 15);
        }
        $gaConfigured = $gaService->isConfigured();

        // Search Console — top keywords that brought users to this
        // photographer's pages. Photographer wants this for SEO.
        $scService = app(\App\Services\Google\GoogleSearchConsoleService::class);
        $topKeywords = [];
        if ($scService->isConfigured() && $profile) {
            $topKeywords = $scService->topKeywords(28, '/photographer/' . ($profile->slug ?: ''), 15);
        }
        $scConfigured = $scService->isConfigured();

        return view('photographer.analytics.index', compact(
            'revenueDays', 'revenueMonths',
            'totalEarnings', 'totalGross', 'totalOrders', 'thisMonthEarnings',
            'topEvents', 'orderStatuses',
            'totalPhotos', 'totalViews',
            'hasCustomerAnalytics', 'topCustomers', 'conversionRate', 'hourlyHeatmap',
            'trafficSources', 'geoBreakdown', 'topKeywords',
            'gaConfigured', 'scConfigured'
        ));
    }
}

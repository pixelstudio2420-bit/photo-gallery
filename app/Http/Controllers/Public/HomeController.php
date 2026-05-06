<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventCategory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HomeController extends Controller
{
    public function index()
    {
        // Home page is the hottest route on the site — every block is cached
        // for 60s. The cache is safe to invalidate on demand (see Observers)
        // but this keeps DB load flat even under 10k+ daily visitors.
        //
        // Each block is wrapped in safeQuery() so that DB outages (MySQL down,
        // timeout, permission denied) degrade gracefully to an empty section
        // rather than crashing the entire home page.

        // Featured events — pulled by recency, then re-sorted so events
        // from boosted photographers ride to the top. Same boost-then-
        // recency contract as the photographer list, applied to events.
        $featuredEvents = $this->safeQuery('public.home.featured_events', 60, fn () =>
            Event::with('category')
                ->whereIn('status', ['active', 'published'])
                ->where('visibility', 'public')
                ->orderByDesc('shoot_date')
                ->limit(20)   // extra so boost re-sort has options
                ->get()
        );
        try {
            $featuredEvents = app(\App\Services\Ranking\PhotographerRankingService::class)
                ->sortRows($featuredEvents, 'photographer_id')
                ->take(8);
        } catch (\Throwable $e) {
            $featuredEvents = $featuredEvents->take(8);
        }

        $latestEvents = $this->safeQuery('public.home.latest_events', 60, fn () =>
            Event::with('category')
                ->whereIn('status', ['active', 'published'])
                ->orderByDesc('created_at')
                ->limit(8)
                ->get()
        );

        $categories = $this->safeQuery('public.home.categories', 300, fn () =>
            EventCategory::withCount([
                    'events' => fn ($q) => $q->whereIn('status', ['active', 'published'])
                ])
                ->orderBy('name')
                ->get()
        );

        // ── Photographers (fixed N+1 subquery-per-row) ───────────────────
        // Before: correlated subquery in SELECT list ran one count per photographer.
        // After : single GROUP BY join, cached 5 minutes.
        //
        // Ordering: paid-promotion boost FIRST (active rows in
        // photographer_promotions), then by event count. This is the
        // contract the ad system promised — photographers who bought
        // boost/featured ride above non-boosted ones in the public list.
        // The PhotographerRankingService re-sorts the loaded rows so the
        // existing SQL stays unchanged + cache-friendly.
        $photographers = $this->safeQuery('public.home.photographers', 300, fn () =>
            DB::table('photographer_profiles as pp')
                ->join('auth_users as u', 'u.id', '=', 'pp.user_id')
                ->leftJoin(
                    DB::raw('(
                        SELECT photographer_id, COUNT(*) AS events_count
                          FROM event_events
                         WHERE status IN (\'active\',\'published\')
                         GROUP BY photographer_id
                    ) as ec'),
                    'ec.photographer_id', '=', 'u.id'
                )
                ->where('pp.status', 'approved')
                ->select(
                    'pp.*',
                    'u.first_name',
                    'u.last_name',
                    'u.email',
                    DB::raw('COALESCE(pp.avatar, u.avatar) as avatar'),
                    DB::raw('COALESCE(pp.display_name, u.first_name) as display_name'),
                    DB::raw('COALESCE(ec.events_count, 0) as events_count')
                )
                ->orderByDesc('events_count')
                ->limit(24)   // pull extra so the re-sort has range to work with
                ->get()
        );

        // Re-sort by paid-promotion boost (photographer_promotions.boost_score
        // when status='active'). Tie-break preserves the event-count order
        // we already loaded by, then trim to 12 cards.
        try {
            $photographers = app(\App\Services\Ranking\PhotographerRankingService::class)
                ->sortRows($photographers, 'user_id')
                ->take(12);
        } catch (\Throwable $e) {
            Log::debug('home.photographer_boost_resort_failed', ['err' => $e->getMessage()]);
            $photographers = $photographers->take(12);
        }

        try {
            $seo = app(\App\Services\SeoService::class);
            // Set explicit title + description for the homepage. Without
            // this, getTitle() falls back to seo_site_name only — and the
            // home page is the highest-traffic, highest-impact-on-rank page.
            $seo->set([
                'title'       => 'ค้นหาและซื้อรูปงานอีเวนต์ของคุณด้วย AI Face Search',
                'description' => 'แพลตฟอร์มซื้อขายรูปงานอีเวนต์อันดับ 1 ในไทย — งานวิ่ง รับปริญญา แต่งงาน คอนเสิร์ต. ค้นหาตัวเองด้วย AI ใน 3 วินาที, จ่ายเงิน → รับรูปทาง LINE ทันที',
                'keywords'    => 'ซื้อรูปออนไลน์, ค้นหารูปด้วยใบหน้า, AI Face Search, รูปงานวิ่ง, รูปรับปริญญา, รูปงานแต่ง, ภาพอีเวนต์, ช่างภาพไทย',
            ])->websiteSchema()->organizationSchema();
            $seo->setBreadcrumbs([['name' => 'หน้าแรก']]);
        } catch (\Throwable $e) {
            Log::warning('home.seo_failed', ['err' => $e->getMessage()]);
        }

        // Adaptive trust counters — see App\Support\PlatformStats for the
        // tier logic (founding/growing/mature). Cached internally so this
        // is a hashtable read on cache hits.
        $stats = \App\Support\PlatformStats::snapshot();

        return view('public.home', compact('featuredEvents', 'latestEvents', 'categories', 'photographers', 'stats'));
    }

    /**
     * "Sell on this platform" — public B2B landing page targeting
     * photographers who haven't signed up yet.
     *
     * Why this exists separately from /become-photographer/quick
     * ----------------------------------------------------------
     * /become-photographer/quick is a *form*. Visitors arriving cold
     * from an ad / social post need the WHY (USP, pricing, social
     * proof) before they fill out the form. Two-step funnel:
     *
     *   1. Ad/SEO →  this page (sell the dream)
     *      └── primary CTA → /become-photographer/quick (capture)
     *      └── secondary CTA → pricing detail / FAQ
     *
     * Pulls live subscription_plans rows so the pricing block stays in
     * sync with what /admin/subscriptions/plans shows.
     */
    public function forPhotographers()
    {
        // Pull the public, active plans ordered by sort_order. The DB
        // already has the marketing copy (tagline, features_json, badge)
        // so we don't repeat it in the blade — single source of truth.
        $plans = $this->safeQuery('public.for-photographers.plans', 600, fn () =>
            \App\Models\SubscriptionPlan::query()
                ->where('is_active', true)
                ->where('is_public', true)
                ->orderBy('sort_order')
                ->get()
        );

        try {
            $seo = app(\App\Services\SeoService::class);
            $seo->set([
                'title'       => 'เริ่มขายรูปออนไลน์ — 0% คอมมิชชั่นบนแผน Pro/Studio · ส่งเข้า LINE อัตโนมัติ',
                'description' => 'แพลตฟอร์มขายรูปอีเวนต์สำหรับช่างภาพไทย — AI Face Search ผ่าน AWS Rekognition, ส่งเข้า LINE หลังจ่ายเงิน, แจ้งถอนเข้าบัญชีไทย, 0% ค่าคอมมิชชั่นบนแผน Pro/Studio',
            ])->setBreadcrumbs([
                ['name' => 'หน้าแรก', 'url' => route('home')],
                ['name' => 'สำหรับช่างภาพ'],
            ]);
        } catch (\Throwable $e) {
            Log::warning('home.for_photographers.seo_failed', ['err' => $e->getMessage()]);
        }

        return view('public.for-photographers', compact('plans'));
    }

    /**
     * Standalone /pricing page — visible from main nav so customers
     * and photographers can both see what they'll pay before signing
     * up. Different from /sell-photos which is a "sell-the-dream"
     * landing page; /pricing is the cold-blooded "what does this
     * cost" answer page.
     *
     * Combines:
     *   - photographer-side subscription plans (from DB)
     *   - customer-side commission/fee structure (static for now —
     *     when a per-event pricing model lands, swap to DB)
     */
    public function pricing()
    {
        $plans = $this->safeQuery('public.pricing.plans', 600, fn () =>
            \App\Models\SubscriptionPlan::query()
                ->where('is_active', true)
                ->where('is_public', true)
                ->orderBy('sort_order')
                ->get()
        );

        try {
            $seo = app(\App\Services\SeoService::class);
            $seo->set([
                'title'       => 'แพ็กเกจราคา & ค่าธรรมเนียม — โปร่งใส ไม่มีค่าซ่อน',
                'description' => 'ราคาช่างภาพ + ค่าธรรมเนียมลูกค้าทั้งหมด ดูได้ก่อนสมัคร — ฟรี 60 วัน, ไม่ต้องใช้บัตรเครดิต, ยกเลิกได้ทุกเมื่อ',
            ])->setBreadcrumbs([
                ['name' => 'หน้าแรก', 'url' => route('home')],
                ['name' => 'ราคา'],
            ]);
        } catch (\Throwable $e) {
            Log::warning('home.pricing.seo_failed', ['err' => $e->getMessage()]);
        }

        return view('public.pricing', compact('plans'));
    }

    /**
     * Cached query with DB-outage fallback.
     *
     * If the query fails (MySQL down, timeout, permission error) we log the
     * failure and return an empty Collection so the rest of the page renders.
     * The exception is NOT cached — next request will retry.
     */
    protected function safeQuery(string $cacheKey, int $ttl, \Closure $callback): Collection
    {
        try {
            return Cache::remember($cacheKey, $ttl, $callback);
        } catch (\Throwable $e) {
            Log::warning('home.query_failed', [
                'key' => $cacheKey,
                'err' => $e->getMessage(),
            ]);
            return collect();
        }
    }
}

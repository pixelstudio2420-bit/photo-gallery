<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventCategory;
use App\Models\PhotographerProfile;
use App\Models\Review;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PhotographerController extends Controller
{
    /**
     * Public list of approved photographers — `/photographers`.
     *
     * Sorting strategy:
     *   1. Paid promotion (active boost/featured) — boosted first
     *   2. Event count — more events → higher
     *   3. Newest — tie-breaker
     *
     * Filters: ?province={id}&search={kw}&category={id}
     * Pagination: 24 per page (3 cols × 8 rows on desktop).
     */
    public function index(Request $request)
    {
        $query = DB::table('photographer_profiles as pp')
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
            ->where('pp.status', 'approved');

        if ($request->filled('province')) {
            $query->where('pp.province_id', (int) $request->province);
        }
        if ($request->filled('search')) {
            $kw = '%' . $request->search . '%';
            $query->where(function ($w) use ($kw) {
                $w->where('pp.display_name', 'ilike', $kw)
                  ->orWhere('u.first_name', 'ilike', $kw)
                  ->orWhere('u.last_name',  'ilike', $kw)
                  ->orWhere('pp.bio',       'ilike', $kw);
            });
        }

        $query->select(
            'pp.user_id',
            'pp.display_name',
            'pp.avatar',
            'pp.bio',
            'pp.tier',
            'pp.slug',
            'pp.specialties',
            'pp.years_experience',
            'pp.province_id',
            'u.first_name',
            'u.last_name',
            DB::raw('COALESCE(ec.events_count, 0) as events_count')
        )
            ->orderByDesc('events_count')
            ->orderByDesc('pp.id');

        $rows = $query->paginate(24)->withQueryString();

        // Re-sort the page contents by paid-promotion boost. We do it on
        // the loaded page so the DB doesn't need a more complex JOIN —
        // the boost map is in-memory + cached.
        try {
            $boost = app(\App\Services\Ranking\PhotographerRankingService::class)->boostMap();
            $items = collect($rows->items())
                ->sortByDesc(fn ($r) => $boost[$r->user_id] ?? 0)
                ->values()
                ->all();
            $rows->setCollection(collect($items));
        } catch (\Throwable) {}

        $provinces = Cache::remember('public.provinces.all', 3600, function () {
            return \App\Models\ThaiProvince::orderBy('name_th')->get();
        });

        try {
            app(\App\Services\SeoService::class)
                ->title('ช่างภาพมืออาชีพ')
                ->description('ค้นหาช่างภาพมืออาชีพในประเทศไทย — แต่งงาน · รับปริญญา · งานวิ่ง · คอนเสิร์ต · อีเวนต์บริษัท · พรีเวดดิ้ง')
                ->setBreadcrumbs([
                    ['name' => 'หน้าแรก',  'url' => url('/')],
                    ['name' => 'ช่างภาพ'],
                ]);
        } catch (\Throwable) {}

        return view('public.photographers.index', compact('rows', 'provinces'));
    }

    public function show(Request $request, $idOrSlug)
    {
        // Accept either numeric user_id (legacy /photographers/{id}) or
        // a slug (/photographer/{slug}). The slug column was added in
        // 2026_04_28_260000 and is unique. Both URL forms resolve to
        // the same view — we redirect callers off the legacy form to
        // the slug form for canonical SEO.
        if (is_numeric($idOrSlug)) {
            $profile = PhotographerProfile::where('user_id', (int) $idOrSlug)
                ->where('status', 'approved')
                ->firstOrFail();

            // 301 to the canonical slug URL so Google + customers
            // converge on a single index entry per photographer.
            if (!empty($profile->slug)) {
                $target = route('photographers.show.slug', ['slug' => $profile->slug]);
                if (request()->getRequestUri() !== parse_url($target, PHP_URL_PATH)) {
                    return redirect()->away($target, 301);
                }
            }
        } else {
            $profile = PhotographerProfile::where('slug', $idOrSlug)
                ->where('status', 'approved')
                ->firstOrFail();
        }

        $id   = $profile->user_id;
        $user = User::findOrFail($id);

        // Base events query
        $eventsQuery = Event::with('category')
            ->where('photographer_id', $id)
            ->where('status', 'active')
            ->where('visibility', 'public');

        // Category filter
        if ($request->filled('category')) {
            $eventsQuery->where('category_id', $request->category);
        }

        // Sort
        $sort = $request->get('sort', 'latest');
        match ($sort) {
            'popular'    => $eventsQuery->orderByDesc('view_count'),
            'oldest'     => $eventsQuery->orderBy('shoot_date'),
            'price_low'  => $eventsQuery->orderBy('price_per_photo'),
            'price_high' => $eventsQuery->orderByDesc('price_per_photo'),
            default      => $eventsQuery->orderByDesc('shoot_date'), // latest
        };

        $events = $eventsQuery->paginate(12)->withQueryString();

        // ── Portfolio: archived-but-previewable past work ──────────────────
        // Events that have passed their retention window (originals_purged_at
        // set) or that the photographer manually pinned (is_portfolio=1) still
        // appear here as a "past works" showcase. The cover + watermarked
        // previews remain visible; purchases are disabled elsewhere by
        // Event::isSellable().
        $portfolio = Event::with('category')
            ->where('photographer_id', $id)
            ->where('visibility', 'public')
            ->portfolio()
            ->orderByDesc('shoot_date')
            ->limit(8)
            ->get();

        // ── Aggregate stats + categories ─────────────────────────────────
        // Previously: 4 separate queries against event_events. Now: one query
        // that produces totalEvents + totalViews + distinct categoryIds.
        // Cached per-photographer for 5 minutes.
        $cacheKey = "public.photographer.{$id}.stats";
        $stats = Cache::remember($cacheKey, 300, function () use ($id) {
            $out = ['total_events' => 0, 'total_views' => 0, 'category_ids' => [], 'avg_rating' => null];
            try {
                // Postgres: STRING_AGG replaces MySQL's GROUP_CONCAT.
                // category_id is integer → cast to text first, then aggregate.
                $row = DB::selectOne(
                    "SELECT
                        COUNT(*)                                                       AS total_events,
                        COALESCE(SUM(view_count), 0)                                   AS total_views,
                        STRING_AGG(DISTINCT category_id::text, ',')                    AS category_ids
                     FROM event_events
                     WHERE photographer_id = ?
                       AND status = 'active'
                       AND visibility = 'public'",
                    [$id]
                );
                if ($row) {
                    $out['total_events'] = (int) $row->total_events;
                    $out['total_views']  = (int) $row->total_views;
                    $out['category_ids'] = $row->category_ids
                        ? array_values(array_filter(explode(',', $row->category_ids)))
                        : [];
                }
            } catch (\Throwable $e) {}

            try {
                $out['avg_rating'] = (float) Review::where('photographer_id', $id)
                    ->where('is_visible', 1)
                    ->avg('rating');
            } catch (\Throwable $e) {}

            return $out;
        });

        $totalEvents = $stats['total_events'];
        $totalViews  = $stats['total_views'];
        $avgRating   = $stats['avg_rating'];

        $categories = !empty($stats['category_ids'])
            ? EventCategory::whereIn('id', $stats['category_ids'])->orderBy('name')->get()
            : collect();

        // Latest reviews (limit 10)
        $reviews = Review::with(['user', 'event'])
            ->where('photographer_id', $id)
            ->where('is_visible', 1)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return view('public.photographers.show', compact(
            'profile',
            'user',
            'events',
            'portfolio',
            'categories',
            'reviews',
            'totalEvents',
            'totalViews',
            'avgRating',
            'sort'
        ));
    }
}

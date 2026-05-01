<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventCategory;
use App\Models\ThaiProvince;
use App\Services\EventService;
use App\Services\GoogleDriveService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class EventController extends Controller
{
    public function index(Request $request)
    {
        $query = $this->buildEventQuery($request);

        $events = $query->with('province')->paginate(24)->withQueryString();

        // Categories + provinces don't change often — cache for 5 minutes.
        $categories = Cache::remember('public.events.categories_with_count', 300, function () {
            return EventCategory::withCount(['events' => fn($q) => $q->whereIn('status', ['active', 'published'])])
                ->orderBy('name')
                ->get();
        });
        $provinces = Cache::remember('public.provinces.all', 3600, function () {
            return ThaiProvince::orderBy('name_th')->get();
        });

        // Stats — one aggregate query, cached 60s.
        $stats = Cache::remember('public.events.stats', 60, function () {
            $defaults = ['total' => 0, 'free' => 0, 'this_month' => 0];
            try {
                // Postgres: SUM(boolean) → COUNT(*) FILTER (WHERE …);
                // first day of current month via date_trunc('month', NOW()).
                $r = DB::selectOne(
                    "SELECT
                        COUNT(*) FILTER (WHERE status IN ('active','published')) AS total,
                        COUNT(*) FILTER (WHERE status IN ('active','published') AND is_free = true) AS free,
                        COUNT(*) FILTER (WHERE status IN ('active','published') AND shoot_date >= date_trunc('month', NOW())::date) AS this_month
                     FROM event_events"
                );
                if ($r) foreach ($defaults as $k => $_) $defaults[$k] = (int) ($r->{$k} ?? 0);
            } catch (\Throwable $e) {}
            return $defaults;
        });

        // AJAX → return partial
        if ($request->ajax() || $request->wantsJson()) {
            $html = view('public.events._grid', compact('events'))->render();
            $pagination = $events->hasPages()
                ? view('public.events._pagination', compact('events'))->render()
                : '';
            return response()->json([
                'html'       => $html,
                'pagination' => $pagination,
                'total'      => $events->total(),
                'showing'    => $events->count(),
            ]);
        }

        $seo = app(\App\Services\SeoService::class);
        $seo->title('อีเวนต์ทั้งหมด')
            ->description('ค้นหาและเลือกชมอีเวนต์ถ่ายภาพทั้งหมด')
            ->type('website')
            ->setBreadcrumbs([
                ['name' => 'หน้าแรก', 'url' => url('/')],
                ['name' => 'อีเวนต์'],
            ]);

        return view('public.events.index', compact('events', 'categories', 'provinces', 'stats'));
    }

    /**
     * Build the filtered event query (shared by full page + AJAX).
     */
    private function buildEventQuery(Request $request)
    {
        $query = Event::with('category')
            ->whereIn('status', ['active', 'published'])
            ->where('visibility', 'public');

        if ($request->filled('category')) {
            // Accept either numeric id (?category=2) OR slug (?category=wedding)
            // — links from SEO landing pages pass slug; legacy admin filters
            // pass id. Without slug resolution, Postgres throws
            // "invalid input syntax for type integer" when a slug is given.
            $cat = $request->category;
            if (is_numeric($cat)) {
                $query->where('category_id', (int) $cat);
            } else {
                $resolved = \App\Models\EventCategory::where('slug', $cat)->value('id');
                if ($resolved) {
                    $query->where('category_id', (int) $resolved);
                }
                // Unknown slug → silently drop the filter (no rows hidden,
                // user lands on full event list instead of an empty page).
            }
        }
        if ($request->filled('province')) {
            // Same defence — accept ?province=10 (id) OR ?province=กรุงเทพ
            // (Thai name match) so SEO landing links don't 500.
            $prov = $request->province;
            if (is_numeric($prov)) {
                $query->where('province_id', (int) $prov);
            } else {
                $resolved = \App\Models\ThaiProvince::where('name_th', 'like', '%' . $prov . '%')
                    ->orWhere('name_en', 'like', '%' . $prov . '%')
                    ->value('id');
                if ($resolved) {
                    $query->where('province_id', (int) $resolved);
                }
            }
        }
        if ($request->filled('district')) {
            $dist = $request->district;
            if (is_numeric($dist)) {
                $query->where('district_id', (int) $dist);
            }
            // District has no slug column today — skip non-numeric values.
        }
        if ($request->filled('price')) {
            match ($request->price) {
                'free' => $query->where('is_free', true),
                'paid' => $query->where('is_free', false)->where('price_per_photo', '>', 0),
                default => null,
            };
        }
        if ($request->filled('q')) {
            $search = trim((string) $request->q);
            // Postgres: use ILIKE for case-insensitive substring matching.
            // For higher-traffic deployments, switch to tsvector GIN index
            // + plainto_tsquery — see TODO in pgsql migration notes.
            $query->where(function ($q) use ($search) {
                $like = "%{$search}%";
                $q->where('name',            'ilike', $like)
                  ->orWhere('description',   'ilike', $like)
                  ->orWhere('location',      'ilike', $like)
                  ->orWhere('location_detail','ilike', $like);
            });
        }

        $sort = $request->get('sort', 'latest');

        // Boost-aware sort: events from photographers who paid for an
        // active boost/featured/highlight ride above non-boosted events.
        // Applied as the FIRST orderBy so it's always the dominant key —
        // the user's chosen sort becomes the tie-break inside the same
        // boost bucket.
        //
        // Skipped for the explicit "name"/"price_*" sorts since those
        // alphabetical/price orderings have no meaning when paid promotion
        // is asserting itself. Boost only applies to the recency-style
        // sorts (latest + popular) where the customer's intent is "show
        // me what's most relevant".
        if (in_array($sort, ['latest', 'popular'], true)) {
            try {
                app(\App\Services\Ranking\PhotographerRankingService::class)
                    ->applyToQuery($query, 'event_events.photographer_id');
            } catch (\Throwable) {
                // Boost subquery failed — fall back to plain ordering below.
            }
        }

        match ($sort) {
            'name'    => $query->orderBy('name'),
            'popular' => $query->orderByDesc('view_count'),
            'price_low'  => $query->orderBy('price_per_photo'),
            'price_high' => $query->orderByDesc('price_per_photo'),
            default   => $query->orderByDesc('shoot_date'),
        };

        return $query;
    }

    public function show($slug)
    {
        // Resolve event by slug, falling back to numeric ID lookup for the
        // legacy /events/{id} URL shape.
        //
        // We MUST NOT just chain `->orWhere('id', $slug)` here — Postgres
        // is strict about column types and refuses to compare a non-numeric
        // string against an integer column, throwing
        //   SQLSTATE[22P02]: invalid input syntax for type integer: "ab"
        // …which surfaces as a 500 on every event slug page that contains
        // letters. (MySQL silently coerces 'ab' → 0 and matches nothing,
        // which is why this latent bug went unnoticed until the recent
        // pgsql migration.)
        //
        // Only fold in the id-equality clause when the input is a pure
        // digit string. ctype_digit() rejects '1.5', '1e5', and negative
        // numbers — exactly the values that can't be a primary-key match.
        $query = Event::with('category')->where('slug', $slug);
        if (ctype_digit((string) $slug)) {
            $query->orWhere('id', (int) $slug);
        }
        $event = $query->firstOrFail();

        // Check password-protected visibility
        if ($event->visibility === 'password') {
            $sessionKey = "event_access_{$event->id}";
            if (!session($sessionKey)) {
                return view('public.events.password', compact('event'));
            }
        }

        // Increment view count — buffered via cache to avoid one UPDATE per
        // pageview. Every 30 views or every 5 minutes we flush to the DB.
        // With 1000 concurrent users each viewing 10 pages, this turns
        // 10,000 UPDATEs into ~330.
        $this->bufferedIncrementView($event->id);

        // Photos are loaded via AJAX (SWR-cached) for faster page load
        $photos = [];
        $prices = [];

        // Get pricing from event's own price or pricing_event_prices table
        $eventPrice = \DB::table('pricing_event_prices')
            ->where('event_id', $event->id)
            ->first();

        if ($eventPrice) {
            // Create a price object matching the view's expected format
            $prices = collect([(object)[
                'price' => $eventPrice->price_per_photo,
                'label' => 'ราคาต่อภาพ',
                'type'  => 'photo',
            ]]);
        } elseif ($event->price_per_photo > 0) {
            $prices = collect([(object)[
                'price' => $event->price_per_photo,
                'label' => 'ราคาต่อภาพ',
                'type'  => 'photo',
            ]]);
        } else {
            $prices = collect();
        }

        $seo = app(\App\Services\SeoService::class);
        $seo->set([
            'title' => $event->name,
            'description' => $event->description ?? $event->name,
            'canonical' => route('events.show', $event->slug ?: $event->id),
            'type' => 'article',
        ])
        ->image($event->cover_image ? asset('storage/' . $event->cover_image) : '')
        ->eventSchema([
            'name' => $event->name,
            'description' => $event->description ?? '',
            // ISO 8601 with Asia/Bangkok offset — Google's Event rich
            // result requires a tz-tagged start; the Event model
            // builds it from shoot_date + start_time (or 00:00:00 when
            // no time was filled in). Falls back to the legacy
            // shoot_date / created_at chain when the model returns null.
            'date'        => $event->startDateIso() ?? ($event->shoot_date ?? $event->created_at),
            'end_date'    => $event->endDateIso(),
            'location'    => $event->location ?? '',
            'venue'       => $event->venue_name ?? '',
            'image'       => $event->cover_image ? asset('storage/' . $event->cover_image) : '',
            'url'         => route('events.show', $event->slug ?: $event->id),
            'price_per_photo' => $event->price_per_photo ?? 0,
            // Enriched fields (2026-05-01) — feed straight into the
            // SchemaService Event payload so eventStatus, organizer,
            // attendees, and contact links land in JSON-LD without
            // every controller having to build them by hand.
            'organizer'         => $event->organizer ?? '',
            'event_type'        => $event->event_type ?? '',
            'expected_attendees'=> (int) ($event->expected_attendees ?? 0),
            'highlights'        => is_array($event->highlights) ? $event->highlights : [],
            'tags'              => is_array($event->tags) ? $event->tags : [],
            'contact_phone'     => $event->contact_phone ?? '',
            'contact_email'     => $event->contact_email ?? '',
            'website_url'       => $event->website_url ?? '',
            'facebook_url'      => $event->facebook_url ?? '',
        ])
        ->setBreadcrumbs([
            ['name' => 'หน้าแรก', 'url' => url('/')],
            ['name' => 'อีเวนต์', 'url' => route('events.index')],
            ['name' => $event->name],
        ]);

        // Load active packages (global + event-specific)
        $packages = \DB::table('pricing_packages')
            ->where('is_active', 1)
            ->where(fn($q) => $q->whereNull('event_id')->orWhere('event_id', $event->id))
            ->orderBy('sort_order')
            ->orderBy('photo_count')
            ->get();

        // Derive cheapest per-photo price from packages.
        //
        // NOTE: only count-bundles have a fixed photo_count to divide by —
        // face_match bundles store photo_count=NULL (count is per-buyer
        // dynamic) and event_all uses photo_count for display only. Skip
        // both when computing the headline "starting at ฿X/photo" stat,
        // otherwise we'd hit DivisionByZeroError on the face_match row.
        $countBundles = $packages->filter(
            fn ($p) => ($p->bundle_type ?? 'count') === 'count'
                && (int) ($p->photo_count ?? 0) > 0
                && (float) ($p->price ?? 0) > 0
        );
        $basePricePerPhoto = $countBundles->count() > 0
            ? $countBundles->map(fn ($p) => (float) $p->price / (int) $p->photo_count)->min()
            : 0;

        // Time-decay urgency — drives countdown badge + bonus discount in
        // the bundle cards section. computeTimeDecayBonus returns 0 when
        // disabled or far from expiry, so this is safe to call always.
        $bundleService    = app(\App\Services\Pricing\BundleService::class);
        $timeDecayBonus   = $bundleService->computeTimeDecayBonus($event);
        $timeDecayTier    = $bundleService->urgencyTier($event);
        $timeDecayExpiry  = $event->auto_delete_at;

        return view('public.events.show', compact(
            'event', 'photos', 'prices', 'packages', 'basePricePerPhoto',
            'timeDecayBonus', 'timeDecayTier', 'timeDecayExpiry'
        ));
    }

    /**
     * Verify the password for a password-protected event.
     */
    public function verifyEventPassword(Request $request, $id): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'string'],
        ]);

        $event = Event::findOrFail($id);

        if ($event->visibility !== 'password') {
            return redirect()->route('events.show', $event->slug ?: $event->id);
        }

        // Constant-time bcrypt verify (auto-upgrades legacy plaintext rows).
        if ($event->checkPassword($request->password)) {
            session(["event_access_{$event->id}" => true]);
            return redirect()->route('events.show', $event->slug ?: $event->id);
        }

        return redirect()->back()->withErrors([
            'password' => 'รหัสผ่านไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง',
        ])->withInput();
    }

    /**
     * Buffered +1 to view_count. Holds the counter in cache and flushes to
     * the DB every `$flushEvery` increments (or after `$ttl` seconds).
     */
    private function bufferedIncrementView(int $eventId, int $flushEvery = 30, int $ttl = 300): void
    {
        try {
            $key = "event_views_buffer:{$eventId}";
            $buffered = (int) Cache::increment($key);
            if ($buffered === 1) {
                // First hit — set TTL so the counter flushes even on low traffic.
                Cache::put($key, 1, $ttl);
            }
            if ($buffered >= $flushEvery) {
                // Flush: swap the cached value into MySQL atomically.
                Cache::forget($key);
                DB::table('event_events')
                    ->where('id', $eventId)
                    ->update(['view_count' => DB::raw("view_count + {$buffered}")]);
            }
        } catch (\Throwable $e) {
            // If cache/DB hiccups, fall back to direct increment so counter isn't lost.
            try {
                DB::table('event_events')->where('id', $eventId)
                    ->update(['view_count' => DB::raw('view_count + 1')]);
            } catch (\Throwable $_) {}
        }
    }
}

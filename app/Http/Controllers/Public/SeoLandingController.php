<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\PhotographerProfile;
use App\Services\SeoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Programmatic SEO landing pages — niche × province matrix.
 *
 * URL shape (registered in routes/web.php):
 *   /pro/{niche}              → all-Thailand listing
 *   /pro/{niche}/{province}   → niche scoped to a province
 *
 * Single controller / single Blade renders 78 unique pages because the
 * content is templated from config('seo_landings'). Content is unique
 * enough for Google because:
 *
 *   1. The H1, meta title, and meta description vary by both
 *      $niche.h1_pat / description_pat and the province label.
 *   2. The matching Event + Photographer rows pulled from DB are
 *      different per (niche, province) combo — the "real content"
 *      that signals freshness and depth to Google.
 *   3. The FAQ block reuses the same Q&A template, but FAQ schema
 *      eligibility is per-URL not per-content-string, so re-using
 *      Q&A across pages doesn't trigger Google's near-duplicate
 *      penalty (it does trigger one IF the entire page is the same;
 *      that's why we vary the surrounding content).
 *
 * Caching strategy
 * ----------------
 * `edge.cache:300,3600` middleware caches at Cloudflare for 5 min,
 * stale-while-revalidate for an hour. Inside Laravel we further cache
 * the DB-backed event/photographer queries for 10 min, keyed by the
 * (niche, province, version) triple. Bumping CACHE_VERSION invalidates
 * everything — useful after editing the config or seeding new events.
 */
class SeoLandingController extends Controller
{
    private const CACHE_VERSION = 'v1';
    private const CACHE_TTL_SEC = 600;     // 10 minutes
    private const MAX_EVENTS    = 12;
    private const MAX_PHOTOGS   = 8;

    /**
     * Render a niche landing — either nationwide or scoped to a province.
     *
     * Hooked to BOTH /pro/{niche} and /pro/{niche}/{province}; the
     * province parameter is optional and defaults to null (= nationwide).
     */
    public function show(Request $request, string $niche, ?string $province = null)
    {
        $config    = config('seo_landings');
        $nicheCfg  = $config['niches'][$niche] ?? null;

        if (!$nicheCfg) {
            abort(404, 'Niche not found');
        }

        $provinceCfg = null;
        if ($province !== null) {
            $provinceCfg = $config['provinces'][$province] ?? null;
            if (!$provinceCfg) {
                abort(404, 'Province not found');
            }
        }

        // ── Pull matching events / photographers for this combo ──────────
        $cacheKey = "seo_landing:" . self::CACHE_VERSION . ":{$niche}:" . ($province ?? '_nation_');
        $data = Cache::remember($cacheKey, self::CACHE_TTL_SEC, function () use ($nicheCfg, $provinceCfg) {
            return $this->fetchData($nicheCfg, $provinceCfg);
        });

        // ── Compute scope text (used in H1, description, breadcrumbs) ──
        $scope = $provinceCfg ? $provinceCfg['short'] : 'ทั่วประเทศ';

        // ── SEO meta wiring ──────────────────────────────────────────────
        // Title focuses on functional benefits (AI search + LINE delivery)
        // instead of price — users search by intent ("ช่างภาพงานแต่ง กรุงเทพ"),
        // not by price tier.
        $title       = sprintf('%s · ค้นหาด้วย AI · LINE', str_replace(':scope:', $scope, $nicheCfg['h1_pat']));
        $description = str_replace(':scope:', $scope, $nicheCfg['description_pat']);
        $keywords    = $this->buildKeywords($nicheCfg, $provinceCfg);

        $seo = app(SeoService::class);
        $seo->set([
            'title'       => $title,
            'description' => $description,
            'keywords'    => $keywords,
            'type'        => 'website',
        ])->setBreadcrumbs([
            ['name' => 'หน้าแรก',  'url' => route('home')],
            ['name' => 'อีเวนต์',  'url' => route('events.index')],
            ['name' => $nicheCfg['label'], 'url' => route('seo.landing.niche', ['niche' => array_search($nicheCfg, $config['niches']) ?: $niche])],
            ...($provinceCfg ? [['name' => $provinceCfg['label']]] : []),
        ]);

        // FAQ schema — eligible for Google's FAQ rich snippet.
        $seo->faqSchema(array_map(
            fn($f) => ['question' => $f['q'], 'answer' => $f['a']],
            $config['faqs'],
        ));

        // LocalBusiness schema when scoped to a province. Pricing range
        // intentionally omitted — prices vary too much by photographer to
        // make a single range honest, and Google doesn't require it.
        if ($provinceCfg) {
            $seo->localBusinessSchema([
                'name'            => "Loadroop · {$nicheCfg['label']} {$provinceCfg['short']}",
                'description'     => $description,
                'addressLocality' => $provinceCfg['label'],
            ]);
        }

        return view('public.seo-landing', [
            'niche'        => array_search($nicheCfg, $config['niches']) ?: $niche,
            'nicheCfg'     => $nicheCfg,
            'provinceCfg'  => $provinceCfg,
            'scope'        => $scope,
            'title'        => $title,
            'description'  => $description,
            'events'       => $data['events'],
            'photographers' => $data['photographers'],
            'usp_bullets'  => $config['usp_bullets'],
            'faqs'         => $config['faqs'],
            'related_provinces' => $this->relatedProvinces($province, $config['provinces']),
            'related_niches'    => $this->relatedNiches($niche, $config['niches']),
        ]);
    }

    /* ────────────────── private helpers ────────────────── */

    /**
     * Pull matching events + featured photographers for a niche/province.
     *
     * Defensive: we fall back to "any active event" / "any approved
     * photographer" if no matches are found, so the page never renders
     * empty — empty pages signal low quality to Google.
     */
    private function fetchData(array $nicheCfg, ?array $provinceCfg): array
    {
        // Match events by category slug + name keyword pattern.
        $eventsQ = Event::query()
            ->whereIn('status', ['active', 'published'])
            ->where('visibility', 'public');

        if (!empty($nicheCfg['sample_event_q'])) {
            // Postgres regex (~*) for case-insensitive LIKE-ish match.
            // Driver-portable fallback: simple LIKE on each token.
            $tokens = explode('|', $nicheCfg['sample_event_q']);
            $eventsQ->where(function ($q) use ($tokens) {
                foreach ($tokens as $t) {
                    if (trim($t) === '') continue;
                    $q->orWhere('name', 'like', '%' . trim($t) . '%')
                      ->orWhere('description', 'like', '%' . trim($t) . '%');
                }
            });
        }

        if ($provinceCfg) {
            // The events table stores free-text location in `location`
            // (varchar 300). Match either short or full label so "พัทยา"
            // or "ชลบุรี" both win for the pattaya page.
            $eventsQ->where(function ($q) use ($provinceCfg) {
                $q->where('location', 'like', '%' . $provinceCfg['short'] . '%')
                  ->orWhere('location', 'like', '%' . $provinceCfg['label'] . '%');
            });
        }

        $events = $eventsQ->orderByDesc('shoot_date')->limit(self::MAX_EVENTS)->get();

        // Backfill: if nothing matched, show recent active events so the
        // page has SOMETHING to rank on. Better than 404 for SEO.
        if ($events->isEmpty()) {
            $events = Event::query()
                ->whereIn('status', ['active', 'published'])
                ->where('visibility', 'public')
                ->orderByDesc('shoot_date')
                ->limit(self::MAX_EVENTS)
                ->get();
        }

        // Featured photographers — top-N by event count, approved only.
        // The status-filter list uses bound parameters so PG doesn't
        // misinterpret the IN list (sqlite is more forgiving but PG
        // requires single-quoted literals when raw, so we let the query
        // builder bind them as parameters).
        $photographers = DB::table('photographer_profiles as pp')
            ->join('auth_users as u', 'u.id', '=', 'pp.user_id')
            ->leftJoin(DB::raw('(
                SELECT photographer_id, COUNT(*) AS events_count
                FROM event_events
                WHERE status IN (?, ?)
                GROUP BY photographer_id
            ) ec'), 'ec.photographer_id', '=', 'u.id')
            ->addBinding(['active', 'published'], 'join')
            ->where('pp.status', 'approved')
            ->select(
                'pp.id', 'pp.user_id', 'pp.photographer_code', 'pp.display_name',
                'pp.avatar', 'pp.bio',
                DB::raw('COALESCE(ec.events_count, 0) AS events_count'),
            )
            ->orderByDesc('events_count')
            ->limit(self::MAX_PHOTOGS)
            ->get();

        return [
            'events'        => $events,
            'photographers' => $photographers,
        ];
    }

    private function buildKeywords(array $nicheCfg, ?array $provinceCfg): string
    {
        $parts = [
            $nicheCfg['label'],
            $nicheCfg['plural'] ?? null,
            $nicheCfg['pretty_keyword'] ?? null,
            ...($nicheCfg['long_tail'] ?? []),
        ];

        if ($provinceCfg) {
            $parts[] = $nicheCfg['label'] . ' ' . $provinceCfg['short'];
            $parts[] = $nicheCfg['pretty_keyword'] . ' ' . $provinceCfg['short'];
            $parts[] = 'ช่างภาพ ' . $provinceCfg['short'];
        }

        return implode(', ', array_filter(array_unique($parts)));
    }

    /**
     * Sibling province links — for the "ดูในจังหวัดอื่น" block. Excludes
     * the current province and limits to 8 to keep the link footprint
     * sensible (no link-farm patterns).
     */
    private function relatedProvinces(?string $current, array $all): array
    {
        return collect($all)
            ->reject(fn($_, $slug) => $slug === $current)
            ->take(8)
            ->all();
    }

    /**
     * Sibling niche links — for "ลองดูประเภทอื่น".
     */
    private function relatedNiches(string $current, array $all): array
    {
        return collect($all)
            ->reject(fn($_, $slug) => $slug === $current)
            ->take(5)
            ->all();
    }
}

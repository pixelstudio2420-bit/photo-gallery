<?php

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Route & Page Health monitor.
 *
 * Fills the one gap the existing monitors leave open: nothing actually HITS
 * the app's routes to confirm they return 2xx. `app:smoke-test` only checks
 * Route::has() (registered, not reachable); `system:health` watches infra;
 * `seo:audit` analyses SEO meta. None catch the class of bug that has bitten
 * production here — e.g. /photographers?specialty=X 500 (JSONB ? vs PDO) and
 * the festival-popup ::interval 500. This service catches those automatically.
 *
 * How it works
 * ────────────
 * For each curated target it issues an INTERNAL sub-request through the HTTP
 * kernel (no network, works in CLI + tests), times it, and classifies:
 *   • 5xx / uncaught throw          → fail
 *   • 2xx/3xx but body has an error
 *     marker (page health)          → fail
 *   • unexpected 4xx                → warn
 *   • slow (> threshold) but 2xx    → warn
 *   • 2xx/3xx clean                 → ok
 *
 * Results persist to route_health_checks for uptime % + history, and the
 * latest run is cached for the dashboard. The scheduled `routes:health`
 * command pings admins on any fail.
 *
 * Re-entrancy: hit() saves + restores the container's `request` binding
 * around each sub-request so it is safe to call from within a web request
 * (the dashboard "Run now" button) as well as from CLI.
 */
class RouteHealthService
{
    private const CACHE_KEY = 'route_health.latest_snapshot.v1';
    private const CACHE_TTL = 900; // 15 min

    /** Body markers that mean "this 200 actually rendered an error". */
    private const ERROR_MARKERS = [
        'Whoops, looks like something went wrong',  // Laravel error page
        'SQLSTATE[',
        'Fatal error',
        'Uncaught',
        'stack trace:',
        'ErrorException',
        'class="exception"',
    ];

    /**
     * Curated check targets. Dynamic ones resolve a real slug/id from the DB
     * and are skipped (not failed) when no row exists.
     *
     * @return array<int, array{key:string,label:string,kind:string,path:?string,intended_status:?int}>
     */
    public function targets(): array
    {
        $targets = [
            // ── Static public routes ──
            ['key' => 'home',            'label' => 'หน้าแรก',                'kind' => 'page',  'path' => '/'],
            ['key' => 'photographers',   'label' => 'รายชื่อช่างภาพ',          'kind' => 'page',  'path' => '/photographers'],
            // Regression guard for the JSONB ?-operator 500 we fixed.
            ['key' => 'photographers_specialty', 'label' => 'ช่างภาพ + filter specialty', 'kind' => 'page', 'path' => '/photographers?specialty=wedding'],
            ['key' => 'photographers_sort',      'label' => 'ช่างภาพ + sort + province', 'kind' => 'page', 'path' => '/photographers?sort=newest&province=1'],
            ['key' => 'events',          'label' => 'รายการอีเวนต์',           'kind' => 'page',  'path' => '/events'],
            ['key' => 'blog',            'label' => 'บล็อก',                   'kind' => 'page',  'path' => '/blog'],
            ['key' => 'blog_search',     'label' => 'ค้นหาบล็อก',              'kind' => 'page',  'path' => '/blog/search?q=photo'],
            ['key' => 'blog_feed',       'label' => 'RSS feed',                'kind' => 'route', 'path' => '/blog/feed'],
            ['key' => 'contact',         'label' => 'ติดต่อเรา',               'kind' => 'page',  'path' => '/contact'],
            ['key' => 'sitemap',         'label' => 'sitemap.xml',             'kind' => 'route', 'path' => '/sitemap.xml'],
            // Intentional 404 — proves the error path renders cleanly (not a 500).
            ['key' => 'unknown_404',     'label' => '404 (ตั้งใจ)',            'kind' => 'route', 'path' => '/this-route-should-not-exist-xyz', 'intended_status' => 404],
        ];

        // ── Dynamic public routes (resolve a real row, else skip) ──
        if ($slug = $this->firstValue('photographer_profiles', 'slug', ['status' => 'approved'])) {
            $targets[] = ['key' => 'photographer_show', 'label' => 'โปรไฟล์ช่างภาพ', 'kind' => 'page', 'path' => '/photographers/p/' . $slug];
        }
        if ($slug = $this->firstValue('event_events', 'slug', ['status' => 'active', 'visibility' => 'public'])) {
            $targets[] = ['key' => 'event_show', 'label' => 'หน้าอีเวนต์', 'kind' => 'page', 'path' => '/events/' . $slug];
        }
        if ($id = $this->firstValue('event_events', 'id', ['status' => 'active', 'visibility' => 'public', 'face_search_enabled' => true])) {
            $targets[] = ['key' => 'event_face_search', 'label' => 'ค้นหาด้วยใบหน้า', 'kind' => 'page', 'path' => '/events/' . $id . '/face-search'];
        }
        if ($slug = $this->firstValue('blog_posts', 'slug', ['status' => 'published'])) {
            $targets[] = ['key' => 'blog_show', 'label' => 'บทความบล็อก', 'kind' => 'page', 'path' => '/blog/' . $slug];
        }
        if ($slug = $this->firstValue('marketing_landing_pages', 'slug', ['status' => 'published'])) {
            $targets[] = ['key' => 'landing_show', 'label' => 'Landing page', 'kind' => 'page', 'path' => '/lp/' . $slug];
        }

        return $targets;
    }

    /**
     * Run every target, persist the results, cache the snapshot, return it.
     *
     * @return array{run_id:string, checked_at:string, results:array, summary:array}
     */
    public function runAll(bool $persist = true): array
    {
        $runId     = substr((string) Str::uuid()->getHex(), 0, 16);
        $checkedAt = now();
        $results   = [];

        foreach ($this->targets() as $t) {
            $results[] = $this->runOne($t);
        }

        $summary = $this->summarize($results);

        if ($persist) {
            $this->persist($runId, $checkedAt, $results);
        }

        $snapshot = [
            'run_id'     => $runId,
            'checked_at' => $checkedAt->toIso8601String(),
            'results'    => $results,
            'summary'    => $summary,
        ];

        Cache::put(self::CACHE_KEY, $snapshot, self::CACHE_TTL);

        return $snapshot;
    }

    /** Run a single target → classified result row. */
    public function runOne(array $t): array
    {
        $intendedStatus = $t['intended_status'] ?? null;
        [$status, $ms, $body, $err] = $this->hit($t['path']);

        $result = 'ok';
        $reason = null;

        $slowMs = (int) AppSetting::get('route_health_slow_ms', 2000);

        if ($intendedStatus !== null) {
            // Target whose "healthy" outcome is a specific non-2xx (e.g. 404).
            if ($status === $intendedStatus) {
                $result = 'ok';
            } elseif ($status >= 500 || $status === 599) {
                $result = 'fail';
                $reason = "expected {$intendedStatus}, got {$status}";
            } else {
                $result = 'warn';
                $reason = "expected {$intendedStatus}, got {$status}";
            }
        } elseif ($status === 599) {
            $result = 'fail';
            $reason = 'uncaught exception: ' . Str::limit((string) $err, 180);
        } elseif ($status >= 500) {
            $result = 'fail';
            $reason = "HTTP {$status}";
        } elseif ($status >= 400) {
            $result = 'warn';
            $reason = "HTTP {$status}";
        } elseif (($t['kind'] ?? 'route') === 'page' && $this->bodyHasError($body)) {
            // 2xx but the page rendered an error/exception inline.
            $result = 'fail';
            $reason = 'page returned 2xx but body contains an error marker';
        } elseif ($ms > $slowMs) {
            $result = 'warn';
            $reason = "slow: {$ms}ms (> {$slowMs}ms)";
        }

        return [
            'key'         => $t['key'],
            'label'       => $t['label'],
            'kind'        => $t['kind'] ?? 'route',
            'path'        => $t['path'],
            'status'      => $status,
            'duration_ms' => $ms,
            'result'      => $result,
            'error'       => $reason,
        ];
    }

    /**
     * Issue an internal GET sub-request through the HTTP kernel.
     * Returns [status, durationMs, body, errorMessageOrNull].
     *
     * 599 is a synthetic status meaning "the kernel threw before producing a
     * response" — i.e. an unhandled exception, the worst kind of route health.
     */
    private function hit(string $path): array
    {
        /** @var HttpKernel $kernel */
        $kernel = app(HttpKernel::class);

        // Save the current request binding so a sub-request issued from inside
        // a web request (dashboard "Run now") doesn't clobber the outer one.
        $original = app()->bound('request') ? app('request') : null;

        $start = microtime(true);
        $status = 599;
        $body   = '';
        $err    = null;
        $response = null;
        $request  = Request::create($path, 'GET');

        try {
            $response = $kernel->handle($request);
            $status   = $response->getStatusCode();
            $body     = (string) $response->getContent();
        } catch (\Throwable $e) {
            $status = 599;
            $err    = $e->getMessage();
            Log::debug("RouteHealth: {$path} threw: " . $e->getMessage());
        } finally {
            $ms = (int) round((microtime(true) - $start) * 1000);
            try {
                if ($response !== null) {
                    $kernel->terminate($request, $response);
                }
            } catch (\Throwable) {
                // terminate side-effects must never fail the health check
            }
            // Restore the outer request binding.
            if ($original !== null) {
                app()->instance('request', $original);
            }
        }

        return [$status, $ms, $body, $err];
    }

    private function bodyHasError(string $body): bool
    {
        if ($body === '') return false;
        // Only scan the first ~40KB — error pages surface markers early and
        // this keeps a big gallery page cheap to scan.
        $head = substr($body, 0, 40000);
        foreach (self::ERROR_MARKERS as $marker) {
            if (stripos($head, $marker) !== false) {
                return true;
            }
        }
        return false;
    }

    private function summarize(array $results): array
    {
        $ok = $warn = $fail = 0;
        $slowest = 0;
        foreach ($results as $r) {
            match ($r['result']) {
                'fail' => $fail++,
                'warn' => $warn++,
                default => $ok++,
            };
            $slowest = max($slowest, (int) $r['duration_ms']);
        }
        return [
            'total'      => count($results),
            'ok'         => $ok,
            'warn'       => $warn,
            'fail'       => $fail,
            'slowest_ms' => $slowest,
            'healthy'    => $fail === 0,
        ];
    }

    private function persist(string $runId, Carbon $checkedAt, array $results): void
    {
        try {
            $rows = array_map(fn ($r) => [
                'run_id'      => $runId,
                'target_key'  => $r['key'],
                'label'       => mb_substr((string) $r['label'], 0, 120),
                'kind'        => $r['kind'],
                'path'        => mb_substr((string) $r['path'], 0, 512),
                'status'      => $r['status'],
                'duration_ms' => $r['duration_ms'],
                'result'      => $r['result'],
                'error'       => $r['error'] ? mb_substr((string) $r['error'], 0, 1000) : null,
                'checked_at'  => $checkedAt,
            ], $results);

            DB::table('route_health_checks')->insert($rows);
        } catch (\Throwable $e) {
            Log::warning('RouteHealth: persist failed: ' . $e->getMessage());
        }
    }

    /** Latest snapshot from cache; falls back to the most recent persisted run. */
    public function latestSnapshot(): ?array
    {
        $cached = Cache::get(self::CACHE_KEY);
        if ($cached) return $cached;

        try {
            $lastRun = DB::table('route_health_checks')->orderByDesc('checked_at')->value('run_id');
            if (!$lastRun) return null;

            $rows = DB::table('route_health_checks')->where('run_id', $lastRun)->orderBy('id')->get();
            $results = $rows->map(fn ($r) => [
                'key' => $r->target_key, 'label' => $r->label, 'kind' => $r->kind,
                'path' => $r->path, 'status' => $r->status, 'duration_ms' => $r->duration_ms,
                'result' => $r->result, 'error' => $r->error,
            ])->all();

            return [
                'run_id'     => $lastRun,
                'checked_at' => optional($rows->first()?->checked_at) ? (string) $rows->first()->checked_at : null,
                'results'    => $results,
                'summary'    => $this->summarize($results),
            ];
        } catch (\Throwable $e) {
            Log::warning('RouteHealth: latestSnapshot failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Uptime % over the trailing N days = ok-checks / total-checks.
     * Also returns per-result counts for the period.
     */
    public function uptime(int $days = 30): array
    {
        try {
            $since = now()->subDays($days);
            $row = DB::table('route_health_checks')
                ->where('checked_at', '>=', $since)
                ->selectRaw("
                    COUNT(*) AS total,
                    SUM(CASE WHEN result = 'ok'   THEN 1 ELSE 0 END) AS ok,
                    SUM(CASE WHEN result = 'warn' THEN 1 ELSE 0 END) AS warn,
                    SUM(CASE WHEN result = 'fail' THEN 1 ELSE 0 END) AS fail
                ")
                ->first();

            $total = (int) ($row->total ?? 0);
            $ok    = (int) ($row->ok ?? 0);
            $pct   = $total > 0 ? round($ok / $total * 100, 2) : null;

            return [
                'days'        => $days,
                'total'       => $total,
                'ok'          => $ok,
                'warn'        => (int) ($row->warn ?? 0),
                'fail'        => (int) ($row->fail ?? 0),
                'uptime_pct'  => $pct,
            ];
        } catch (\Throwable $e) {
            return ['days' => $days, 'total' => 0, 'ok' => 0, 'warn' => 0, 'fail' => 0, 'uptime_pct' => null];
        }
    }

    /**
     * Recent runs collapsed to one row each (run_id → counts + worst result).
     * Drives the dashboard's history timeline.
     */
    public function recentRuns(int $limit = 20): array
    {
        try {
            $runs = DB::table('route_health_checks')
                ->select('run_id', DB::raw('MAX(checked_at) AS checked_at'),
                    DB::raw('COUNT(*) AS total'),
                    DB::raw("SUM(CASE WHEN result='fail' THEN 1 ELSE 0 END) AS fail"),
                    DB::raw("SUM(CASE WHEN result='warn' THEN 1 ELSE 0 END) AS warn"),
                    DB::raw('MAX(duration_ms) AS slowest_ms'))
                ->groupBy('run_id')
                ->orderByDesc('checked_at')
                ->limit($limit)
                ->get();

            return $runs->map(fn ($r) => [
                'run_id'     => $r->run_id,
                'checked_at' => (string) $r->checked_at,
                'total'      => (int) $r->total,
                'fail'       => (int) $r->fail,
                'warn'       => (int) $r->warn,
                'slowest_ms' => (int) $r->slowest_ms,
                'result'     => $r->fail > 0 ? 'fail' : ($r->warn > 0 ? 'warn' : 'ok'),
            ])->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function firstValue(string $table, string $column, array $where = []): mixed
    {
        try {
            if (!\Schema::hasTable($table) || !\Schema::hasColumn($table, $column)) {
                return null;
            }
            $q = DB::table($table);
            foreach ($where as $col => $val) {
                if (\Schema::hasColumn($table, $col)) {
                    $q->where($col, $val);
                }
            }
            return $q->orderByDesc('id')->value($column);
        } catch (\Throwable $e) {
            return null;
        }
    }
}

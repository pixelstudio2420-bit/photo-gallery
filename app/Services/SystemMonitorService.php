<?php

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Unified system health + analytics provider.
 *
 * Surface four families of metrics to the admin dashboard:
 *   1. Server   — PHP/Laravel runtime, memory, disk, OS load
 *   2. Database — connection, version, table sizes, connections
 *   3. Storage  — per-driver usage, growth, photo counts
 *   4. Downloads + data volume (orders, events, users, tokens)
 *
 * Plus a production-readiness scorecard with actionable checks.
 *
 * Expensive aggregations are cached for 60s so the dashboard can poll
 * every few seconds without hammering the database.
 */
class SystemMonitorService
{
    /** Cache TTL for expensive aggregations (seconds) */
    protected const TTL = 60;

    /**
     * Full snapshot — dashboard calls this once per refresh.
     */
    public function snapshot(): array
    {
        return [
            'generated_at' => now()->toIso8601String(),
            'server'       => $this->server(),
            'database'     => $this->database(),
            'cache'        => $this->cacheHealth(),
            'queue'        => $this->queue(),
            'storage'      => $this->storage(),
            'downloads'    => $this->downloads(),
            'data'         => $this->dataVolume(),
        ];
    }

    // ═══ Server ═══════════════════════════════════════════════════════

    public function server(): array
    {
        $loadAvg = function_exists('sys_getloadavg') ? sys_getloadavg() : null;
        $rootPath = base_path();

        return [
            'php_version'     => PHP_VERSION,
            'laravel_version' => \Illuminate\Foundation\Application::VERSION,
            'app_env'         => config('app.env'),
            'app_debug'       => (bool) config('app.debug'),
            'timezone'        => config('app.timezone'),
            'os'              => PHP_OS_FAMILY,
            'hostname'        => gethostname() ?: 'unknown',
            'memory' => [
                'current'       => memory_get_usage(true),
                'peak'          => memory_get_peak_usage(true),
                'limit_display' => ini_get('memory_limit') ?: '—',
                'limit_bytes'   => $this->parseMemoryLimit((string) ini_get('memory_limit')),
            ],
            'disk' => [
                'free'  => @disk_free_space($rootPath) ?: 0,
                'total' => @disk_total_space($rootPath) ?: 0,
            ],
            'load_avg'      => $loadAvg, // [1m, 5m, 15m] — Linux only
            'max_execution' => (int) ini_get('max_execution_time'),
            'upload_max'    => ini_get('upload_max_filesize') ?: '—',
            'post_max'      => ini_get('post_max_size') ?: '—',
            'opcache'       => $this->opcacheInfo(),
        ];
    }

    protected function opcacheInfo(): array
    {
        if (!function_exists('opcache_get_status')) {
            return ['enabled' => false, 'reason' => 'extension missing'];
        }
        try {
            $status = @opcache_get_status(false);
            if (!$status || !($status['opcache_enabled'] ?? false)) {
                return ['enabled' => false, 'reason' => 'disabled'];
            }
            $mem = $status['memory_usage'] ?? [];
            return [
                'enabled'    => true,
                'used'       => (int) ($mem['used_memory'] ?? 0),
                'free'       => (int) ($mem['free_memory'] ?? 0),
                'hit_rate'   => round((float) ($status['opcache_statistics']['opcache_hit_rate'] ?? 0), 2),
                'hits'       => (int) ($status['opcache_statistics']['hits'] ?? 0),
                'misses'     => (int) ($status['opcache_statistics']['misses'] ?? 0),
            ];
        } catch (\Throwable $e) {
            return ['enabled' => false, 'reason' => $e->getMessage()];
        }
    }

    // ═══ Database ═════════════════════════════════════════════════════

    public function database(): array
    {
        return Cache::remember('sysmon:db', self::TTL, function () {
            $connected = false;
            $version = null;
            try {
                $connected = (bool) DB::connection()->getPdo();
                $version = DB::selectOne('SELECT VERSION() as v')?->v;
            } catch (\Throwable $e) {
                return ['connected' => false, 'driver' => config('database.default'), 'error' => $e->getMessage()];
            }

            $defaultConn = config('database.default');
            $dbName = config("database.connections.{$defaultConn}.database");

            $driver = DB::connection()->getDriverName();

            $tables = [];
            $totalSize = 0;
            try {
                if ($driver === 'pgsql') {
                    // Postgres: pg_class + pg_namespace expose table sizes natively
                    $rows = DB::select("
                        SELECT relname AS name,
                               reltuples::bigint AS row_count,
                               pg_total_relation_size(C.oid) AS size_bytes
                        FROM pg_class C
                        LEFT JOIN pg_namespace N ON N.oid = C.relnamespace
                        WHERE nspname = 'public' AND relkind = 'r'
                        ORDER BY pg_total_relation_size(C.oid) DESC
                        LIMIT 15
                    ");
                    foreach ($rows as $r) {
                        $tables[] = [
                            'name'  => $r->name,
                            'rows'  => (int) $r->row_count,
                            'bytes' => (int) $r->size_bytes,
                        ];
                        $totalSize += (int) $r->size_bytes;
                    }

                    $grand = DB::selectOne("
                        SELECT COALESCE(SUM(pg_total_relation_size(C.oid)), 0) AS s
                        FROM pg_class C
                        LEFT JOIN pg_namespace N ON N.oid = C.relnamespace
                        WHERE nspname = 'public' AND relkind = 'r'
                    ");
                    $totalSize = (int) ($grand->s ?? $totalSize);
                } else {
                    // MySQL / MariaDB: information_schema.TABLES
                    $rows = DB::select("
                        SELECT TABLE_NAME as name,
                               TABLE_ROWS as row_count,
                               (DATA_LENGTH + INDEX_LENGTH) as size_bytes
                        FROM information_schema.TABLES
                        WHERE TABLE_SCHEMA = ?
                        ORDER BY size_bytes DESC
                        LIMIT 15
                    ", [$dbName]);
                    foreach ($rows as $r) {
                        $tables[] = [
                            'name'  => $r->name,
                            'rows'  => (int) $r->row_count,
                            'bytes' => (int) $r->size_bytes,
                        ];
                        $totalSize += (int) $r->size_bytes;
                    }

                    // Get grand total across all tables
                    $grand = DB::selectOne("
                        SELECT SUM(DATA_LENGTH + INDEX_LENGTH) as s
                        FROM information_schema.TABLES
                        WHERE TABLE_SCHEMA = ?
                    ", [$dbName]);
                    $totalSize = (int) ($grand->s ?? $totalSize);
                }
            } catch (\Throwable $e) {}

            $threads = 0;
            $slow = 0;
            $uptime = 0;
            try {
                if ($driver === 'pgsql') {
                    // Postgres equivalents
                    $threads = (int) (DB::selectOne("SELECT count(*) AS c FROM pg_stat_activity")?->c ?? 0);
                    $uptime  = (int) (DB::selectOne("SELECT extract(epoch from now() - pg_postmaster_start_time())::int AS u")?->u ?? 0);
                    // PG doesn't track slow queries by default — try pg_stat_statements, fall back to 0
                    try {
                        $slow = (int) (DB::selectOne("SELECT COALESCE(SUM(calls), 0)::int AS c FROM pg_stat_statements WHERE mean_exec_time > 1000")?->c ?? 0);
                    } catch (\Throwable $e) {
                        $slow = 0;
                    }
                } else {
                    $threads = (int) (DB::selectOne("SHOW STATUS LIKE 'Threads_connected'")?->Value ?? 0);
                    $slow    = (int) (DB::selectOne("SHOW STATUS LIKE 'Slow_queries'")?->Value ?? 0);
                    $uptime  = (int) (DB::selectOne("SHOW STATUS LIKE 'Uptime'")?->Value ?? 0);
                }
            } catch (\Throwable $e) {}

            return [
                'connected'    => $connected,
                'driver'       => $defaultConn,
                'version'      => $version,
                'database'     => $dbName,
                'tables_top15' => $tables,
                'total_bytes'  => $totalSize,
                'connections'  => $threads,
                'slow_queries' => $slow,
                'uptime_sec'   => $uptime,
            ];
        });
    }

    // ═══ Cache ════════════════════════════════════════════════════════

    public function cacheHealth(): array
    {
        $driver = config('cache.default');
        $ok = false;
        try {
            Cache::put('sysmon:ping', 1, 5);
            $ok = Cache::get('sysmon:ping') === 1;
        } catch (\Throwable $e) {}

        $redisMemory = null;
        if ($driver === 'redis') {
            try {
                $info = app('redis')->connection()->info('memory');
                $redisMemory = (int) ($info['used_memory'] ?? 0);
            } catch (\Throwable $e) {}
        }

        return [
            'driver'       => $driver,
            'ok'           => $ok,
            'redis_memory' => $redisMemory,
        ];
    }

    // ═══ Queue ════════════════════════════════════════════════════════

    public function queue(): array
    {
        $driver = config('queue.default');
        $pending = 0;
        $failed = 0;
        $byQueue = [];
        $oldestPendingSec = 0;

        try {
            if (Schema::hasTable('jobs')) {
                $pending = DB::table('jobs')->count();
                $byQueue = DB::table('jobs')
                    ->select('queue', DB::raw('COUNT(*) as count'))
                    ->groupBy('queue')
                    ->pluck('count', 'queue')
                    ->toArray();

                $oldest = DB::table('jobs')->min('available_at');
                if ($oldest) {
                    $oldestPendingSec = max(0, time() - (int) $oldest);
                }
            }
            if (Schema::hasTable('failed_jobs')) {
                $failed = DB::table('failed_jobs')->count();
            }
        } catch (\Throwable $e) {}

        return [
            'driver'          => $driver,
            'pending'         => $pending,
            'failed'          => $failed,
            'by_queue'        => $byQueue,
            'oldest_pending_s' => $oldestPendingSec,
        ];
    }

    // ═══ Storage ══════════════════════════════════════════════════════

    public function storage(): array
    {
        return Cache::remember('sysmon:storage', self::TTL, function () {
            $manager = app(StorageManager::class);
            $out = [
                'resolved' => [
                    'primary' => $manager->primaryDriver(),
                    'upload'  => $manager->uploadDriver(),
                    'zip'     => $manager->zipDisk(),
                    'mirrors' => $manager->mirrorTargets(),
                ],
                'drivers' => [],
            ];

            foreach ([StorageManager::DRIVER_R2, StorageManager::DRIVER_S3, StorageManager::DRIVER_DRIVE, StorageManager::DRIVER_PUBLIC] as $d) {
                $out['drivers'][$d] = $this->storageForDriver($d, $manager);
            }

            // Local-disk free space
            $rootPath = storage_path('app');
            $out['local_disk'] = [
                'free'  => @disk_free_space($rootPath) ?: 0,
                'total' => @disk_total_space($rootPath) ?: 0,
            ];

            return $out;
        });
    }

    protected function storageForDriver(string $driver, StorageManager $manager): array
    {
        $enabled = $manager->driverIsEnabled($driver);

        $photoCount = 0;
        $totalBytes = 0;
        $growth24h = 0;
        $growth7d = 0;

        try {
            if ($driver === StorageManager::DRIVER_DRIVE) {
                // Drive photos don't carry file_size consistently; count only
                $photoCount = DB::table('event_photos')
                    ->where(function ($q) {
                        $q->where('storage_disk', 'drive')->orWhere('source', 'drive')->orWhereNotNull('drive_file_id');
                    })->count();
            } else {
                $base = DB::table('event_photos')->where('storage_disk', $driver);
                $photoCount = (clone $base)->count();
                $totalBytes = (int) (clone $base)->sum('file_size');
                $growth24h = (int) (clone $base)->where('created_at', '>=', now()->subDay())->sum('file_size');
                $growth7d  = (int) (clone $base)->where('created_at', '>=', now()->subDays(7))->sum('file_size');
            }
        } catch (\Throwable $e) {}

        return [
            'enabled'     => $enabled,
            'photo_count' => $photoCount,
            'total_bytes' => $totalBytes,
            'growth_24h'  => $growth24h,
            'growth_7d'   => $growth7d,
        ];
    }

    // ═══ Downloads ════════════════════════════════════════════════════

    public function downloads(): array
    {
        return Cache::remember('sysmon:downloads', self::TTL, function () {
            $tokens = DB::table('download_tokens')->count();
            $active = DB::table('download_tokens')
                ->where(function ($q) {
                    $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
                })->count();

            // download_count is incremented by processDownload — sum the relevant window
            // download_tokens has no updated_at, so we use created_at as a proxy for window buckets.
            $today = (int) DB::table('download_tokens')
                ->whereDate('created_at', today())
                ->sum('download_count');

            $week = (int) DB::table('download_tokens')
                ->where('created_at', '>=', now()->subDays(7))
                ->sum('download_count');

            $allTime = (int) DB::table('download_tokens')->sum('download_count');

            $zipPending = 0;
            try {
                if (Schema::hasTable('jobs')) {
                    $zipPending = DB::table('jobs')->where('queue', 'downloads')->count();
                }
            } catch (\Throwable $e) {}

            // Top 5 events by order count (proxy for popularity)
            $topEvents = [];
            try {
                $rows = DB::table('orders as o')
                    ->leftJoin('event_events as e', 'e.id', '=', 'o.event_id')
                    ->select('e.id', 'e.name', DB::raw('COUNT(o.id) as orders_count'))
                    ->groupBy('e.id', 'e.name')
                    ->orderByDesc('orders_count')
                    ->limit(5)
                    ->get();
                foreach ($rows as $r) {
                    $topEvents[] = [
                        'event_id' => $r->id,
                        'name'     => $r->name ?: '— unknown —',
                        'orders'   => (int) $r->orders_count,
                    ];
                }
            } catch (\Throwable $e) {}

            return [
                'tokens_total'    => $tokens,
                'tokens_active'   => $active,
                'downloads_today' => $today,
                'downloads_7d'    => $week,
                'downloads_all'   => $allTime,
                'zip_pending'     => $zipPending,
                'top_events'      => $topEvents,
            ];
        });
    }

    // ═══ Data Volume ══════════════════════════════════════════════════

    public function dataVolume(): array
    {
        return Cache::remember('sysmon:data', self::TTL, function () {
            $data = [];

            $data['events']        = $this->safeCount('event_events');
            $data['events_active'] = $this->safeCount('event_events', fn($q) => $q->where('status', 'active'));
            $data['photos']        = $this->safeCount('event_photos');
            $data['photos_active'] = $this->safeCount('event_photos', fn($q) => $q->where('status', 'active'));
            $data['orders']        = $this->safeCount('orders');
            $data['orders_paid']   = $this->safeCount('orders', fn($q) => $q->whereIn('status', ['paid', 'completed']));
            $data['users']         = $this->safeCount('auth_users');

            // 24h windows
            $data['new_events_24h'] = $this->safeCount('event_events', fn($q) => $q->where('created_at', '>=', now()->subDay()));
            $data['new_photos_24h'] = $this->safeCount('event_photos', fn($q) => $q->where('created_at', '>=', now()->subDay()));
            $data['new_orders_24h'] = $this->safeCount('orders', fn($q) => $q->where('created_at', '>=', now()->subDay()));
            $data['new_users_24h']  = $this->safeCount('auth_users', fn($q) => $q->where('created_at', '>=', now()->subDay()));

            return $data;
        });
    }

    protected function safeCount(string $table, ?callable $apply = null): int
    {
        try {
            $q = DB::table($table);
            if ($apply) $apply($q);
            return (int) $q->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    // ═══ Production Readiness ═════════════════════════════════════════

    /**
     * Run every readiness check and return a score + checklist.
     */
    public function readiness(): array
    {
        $checks = [];

        // ── Environment ──
        $env = config('app.env');
        $checks[] = $this->mkCheck(
            'APP_ENV = production',
            $env === 'production',
            $env !== 'production' ? "ตอนนี้ใช้ {$env} — set APP_ENV=production ก่อน go live" : '',
            'env'
        );

        $debug = (bool) config('app.debug');
        $checks[] = $this->mkCheck(
            'APP_DEBUG is off',
            !$debug,
            $debug ? 'ปิด APP_DEBUG=false ใน .env เพื่อไม่ให้ stack trace หลุดไปลูกค้า' : '',
            'env'
        );

        $appUrl = (string) config('app.url');
        $checks[] = $this->mkCheck(
            'APP_URL uses HTTPS',
            str_starts_with($appUrl, 'https://'),
            str_starts_with($appUrl, 'https://') ? '' : 'ใช้ HTTPS เสมอใน production — ตั้ง APP_URL=https://...',
            'env'
        );

        // ── Caching/Queue ──
        $cacheDriver = config('cache.default');
        $goodCache = in_array($cacheDriver, ['redis', 'memcached', 'dynamodb']);
        $checks[] = $this->mkCheck(
            'Cache driver is Redis/Memcached',
            $goodCache,
            $goodCache ? '' : "ปัจจุบัน: {$cacheDriver} — แนะนำ Redis สำหรับ multi-server",
            'perf'
        );

        $queueDriver = config('queue.default');
        $goodQueue = in_array($queueDriver, ['redis', 'database', 'sqs', 'beanstalkd']);
        $checks[] = $this->mkCheck(
            'Queue driver supports workers',
            $goodQueue,
            $goodQueue ? '' : "ปัจจุบัน: {$queueDriver} — sync queue ไม่รัน async jobs",
            'perf'
        );

        $sessionDriver = config('session.driver');
        $goodSession = in_array($sessionDriver, ['redis', 'database', 'memcached']);
        $checks[] = $this->mkCheck(
            'Session driver scales',
            $goodSession,
            $goodSession ? '' : "ปัจจุบัน: {$sessionDriver} — file sessions ไม่ share ข้าม server",
            'perf'
        );

        // ── Build/Cache ──
        $configCached = file_exists(base_path('bootstrap/cache/config.php'));
        $checks[] = $this->mkCheck(
            'Config cached',
            $configCached,
            $configCached ? '' : 'รัน: php artisan config:cache',
            'perf'
        );

        $routesCached = file_exists(base_path('bootstrap/cache/routes-v7.php'))
            || glob(base_path('bootstrap/cache/routes*.php'));
        $routesCached = is_array($routesCached) ? !empty($routesCached) : $routesCached;
        $checks[] = $this->mkCheck(
            'Routes cached',
            (bool) $routesCached,
            $routesCached ? '' : 'รัน: php artisan route:cache',
            'perf'
        );

        $viewsCached = count(glob(storage_path('framework/views/*.php')) ?: []) > 0;
        $checks[] = $this->mkCheck(
            'Views compiled',
            $viewsCached,
            $viewsCached ? '' : 'รัน: php artisan view:cache',
            'perf'
        );

        $opc = $this->opcacheInfo();
        $checks[] = $this->mkCheck(
            'OPcache enabled',
            (bool) ($opc['enabled'] ?? false),
            ($opc['enabled'] ?? false) ? '' : 'เปิด opcache.enable=1 ใน php.ini — เร่ง PHP ได้ 30-80%',
            'perf'
        );

        // ── Storage ──
        $manager = app(StorageManager::class);
        $hasCloud = $manager->isCloudEnabled();
        $checks[] = $this->mkCheck(
            'Cloud storage configured (R2/S3)',
            $hasCloud,
            $hasCloud ? '' : 'เปิด R2 หรือ S3 ใน Admin → Settings → Storage เพื่อรองรับ 5k+ concurrent downloads',
            'storage'
        );

        $useSigned = AppSetting::get('storage_use_signed_urls', '1') === '1';
        $checks[] = $this->mkCheck(
            'Signed URLs enabled',
            $useSigned,
            $useSigned ? '' : 'เปิด storage_use_signed_urls เพื่อ redirect ตรงไป CDN',
            'storage'
        );

        // ── Mailer ──
        $mailer = config('mail.default');
        $goodMailer = !in_array($mailer, ['log', 'array']);
        $checks[] = $this->mkCheck(
            'Mailer configured',
            $goodMailer,
            $goodMailer ? '' : "Mailer = {$mailer} — ไม่ส่งอีเมลจริง",
            'features'
        );

        // ── Scheduler / Workers ──
        $schedOk = $this->schedulerLooksActive();
        $checks[] = $this->mkCheck(
            'Scheduler running recently',
            $schedOk,
            $schedOk ? '' : 'ตั้ง cron: * * * * * cd /path && php artisan schedule:run >> /dev/null 2>&1',
            'ops'
        );

        $workerOk = $this->queueWorkerLooksActive();
        $checks[] = $this->mkCheck(
            'Queue worker running',
            $workerOk,
            $workerOk ? '' : 'รัน queue worker ผ่าน Supervisor (ดูคู่มือ SCALING.md)',
            'ops'
        );

        // ── Rate limiting & auth hardening ──
        $rateCount = $this->countRateLimitedRoutes();
        $checks[] = $this->mkCheck(
            'Rate limiters active on auth/download routes',
            $rateCount >= 4,
            $rateCount < 4 ? "พบ rate limit แค่ {$rateCount} route — ควร ≥ 4 (login/register/download/upload)" : '',
            'security'
        );

        // ── DB replica ──
        $hasReplica = !empty(config('database.connections.mysql.read'));
        $checks[] = $this->mkCheck(
            'DB read replica configured',
            $hasReplica,
            $hasReplica ? '' : 'ตั้ง DB_READ_HOST ใน .env (optional, สำหรับ scale 10k+ req/s)',
            'scaling'
        );

        $passed = 0;
        $warn = 0;
        foreach ($checks as $c) {
            if ($c['status'] === 'ok') $passed++;
            elseif ($c['status'] === 'warn') $warn++;
        }
        $total = count($checks);
        $score = $total > 0 ? (int) round((($passed + $warn * 0.5) / $total) * 100) : 0;

        return [
            'score'   => $score,
            'passed'  => $passed,
            'warn'    => $warn,
            'failed'  => $total - $passed - $warn,
            'total'   => $total,
            'tier'    => $this->readinessTier($score),
            'checks'  => $checks,
        ];
    }

    protected function mkCheck(string $name, bool $pass, string $note = '', string $category = 'general'): array
    {
        return [
            'name'     => $name,
            'status'   => $pass ? 'ok' : 'fail',
            'note'     => $note,
            'category' => $category,
        ];
    }

    protected function readinessTier(int $score): string
    {
        return match (true) {
            $score >= 90 => 'production-ready',
            $score >= 75 => 'staging',
            $score >= 50 => 'development',
            default      => 'early-dev',
        };
    }

    protected function schedulerLooksActive(): bool
    {
        // Laravel writes the last schedule run time to cache.
        try {
            $last = Cache::get('laravel-schedule-last-run')
                ?? Cache::get('illuminate:schedule:last-run');
            if ($last) {
                $ts = is_numeric($last) ? (int) $last : Carbon::parse($last)->timestamp;
                return $ts > (time() - 300); // within 5 min
            }
        } catch (\Throwable $e) {}

        // Fallback heuristic: any scheduled task that wrote to `jobs` or activity_logs in last 5 min
        try {
            if (Schema::hasTable('activity_logs')) {
                return DB::table('activity_logs')->where('created_at', '>=', now()->subMinutes(10))->exists();
            }
        } catch (\Throwable $e) {}
        return false;
    }

    protected function queueWorkerLooksActive(): bool
    {
        // Heuristic: if any job has been processed in the last 10 min,
        // the worker is alive. We check the delta in total failed/successful via activity.
        try {
            if (Schema::hasTable('failed_jobs')) {
                $recentFail = DB::table('failed_jobs')->where('failed_at', '>=', now()->subMinutes(10))->exists();
                if ($recentFail) return true; // worker ran, just failed
            }
            if (Schema::hasTable('activity_logs')) {
                return DB::table('activity_logs')
                    ->where('created_at', '>=', now()->subMinutes(10))
                    ->where(function ($q) {
                        $q->where('action', 'ilike', '%job%')
                          ->orWhere('action', 'ilike', '%queue%')
                          ->orWhere('action', 'ilike', '%purge%');
                    })->exists();
            }
        } catch (\Throwable $e) {}
        // If there are no pending jobs either, assume idle worker = ok.
        try {
            $pending = Schema::hasTable('jobs') ? DB::table('jobs')->count() : 0;
            return $pending === 0;
        } catch (\Throwable $e) {}
        return false;
    }

    protected function countRateLimitedRoutes(): int
    {
        $count = 0;
        try {
            foreach (app('router')->getRoutes() as $route) {
                $middleware = $route->middleware();
                foreach ($middleware as $m) {
                    $mStr = is_string($m) ? $m : (string) $m;
                    if (str_contains($mStr, 'rate.limit') || str_contains($mStr, 'throttle')) {
                        $count++;
                        break;
                    }
                }
            }
        } catch (\Throwable $e) {}
        return $count;
    }

    // ═══ Helpers ══════════════════════════════════════════════════════

    protected function parseMemoryLimit(string $limit): int
    {
        $limit = trim($limit);
        if ($limit === '-1' || $limit === '') return PHP_INT_MAX;
        $num = (int) $limit;
        $unit = strtolower(substr($limit, -1));
        return match ($unit) {
            'g' => $num * 1024 * 1024 * 1024,
            'm' => $num * 1024 * 1024,
            'k' => $num * 1024,
            default => $num,
        };
    }

    /**
     * Format bytes into human-readable (B / KB / MB / GB / TB).
     */
    public static function formatBytes(int $bytes, int $precision = 1): string
    {
        if ($bytes < 0) return '—';
        if ($bytes === 0) return '0 B';
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        $pow = min((int) floor(log($bytes, 1024)), count($units) - 1);
        return round($bytes / (1024 ** $pow), $precision) . ' ' . $units[$pow];
    }
}

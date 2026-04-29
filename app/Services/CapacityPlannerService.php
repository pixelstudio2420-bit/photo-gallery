<?php

namespace App\Services;

use App\Models\BusinessExpense;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * CapacityPlannerService
 * ─────────────────────────────────────────────────────────────────────
 * Answers three questions admins actually care about:
 *
 *   1. "เซิร์ฟเวอร์นี้รองรับผู้ใช้พร้อมกันกี่คน?"
 *      → capacityEstimate()   returns max concurrent per tier + bottleneck
 *
 *   2. "ตอนนี้ใกล้เต็มยัง? อีกกี่เดือนต้อง scale?"
 *      → currentLoad() + growthProjection()
 *
 *   3. "ต้นทุนต่อผู้ใช้เท่าไหร่?"
 *      → costPerUser() ties into business_expenses
 *
 * The math is INTENTIONALLY conservative — we'd rather underestimate
 * capacity and have admins pleasantly surprised than overestimate and
 * have the site fall over during a marathon photo upload rush.
 *
 * Numbers are orders-of-magnitude accurate; for exact tuning an admin
 * should still run a proper load test (k6, Apache Bench, etc.).
 */
class CapacityPlannerService
{
    /** Default workload profile — overridable via query string. */
    public const DEFAULTS = [
        // How many HTTP reqs each active user generates per minute while browsing
        'avg_req_per_user_per_min' => 15,
        // Average request handler duration (includes DB + template render)
        'avg_req_duration_ms'      => 150,
        // How much RAM a single PHP-FPM worker occupies at steady state
        'ram_per_worker_mb'        => 80,
        // Ratio of peak traffic to average traffic (e.g. 3× at race morning)
        'peak_multiplier'          => 3,
        // Queries an average request fires
        'db_queries_per_request'   => 8,
        // Avg query duration
        'db_query_avg_ms'          => 20,
        // Safety buffer — keep this much capacity unused as headroom
        'safety_headroom_pct'      => 30,
        // Assumed avg photo size for upload concurrency math
        'avg_photo_size_mb'        => 4,
        // Effective disk write throughput (NVMe ≈ 400, SSD ≈ 150, HDD ≈ 50)
        'disk_write_mbps'          => 200,
    ];

    public function __construct(private SystemMonitorService $monitor) {}

    // ═══════════════════════════════════════════════════════════════════
    // 1. Server specs  (what hardware do we have?)
    // ═══════════════════════════════════════════════════════════════════

    public function serverSpecs(): array
    {
        return Cache::remember('capacity:specs', 300, function () {
            $srv = $this->monitor->server();
            $db  = $this->monitor->database();

            $cpuCores    = $this->detectCpuCores();
            $totalRamMb  = $this->detectTotalRamMb();
            $phpLimitB   = (int) ($srv['memory']['limit_bytes'] ?? 0);

            return [
                'cpu_cores'           => $cpuCores,
                'total_ram_mb'        => $totalRamMb,
                'total_ram_gb'        => round($totalRamMb / 1024, 1),
                'php_memory_limit_mb' => intdiv($phpLimitB, 1024 * 1024),
                'php_version'         => $srv['php_version'],
                'laravel_version'     => $srv['laravel_version'],
                'mysql_version'       => $db['version'] ?? null,
                'mysql_max_conn'      => $this->detectMysqlMaxConn(),
                'mysql_innodb_pool_mb' => $this->detectInnodbBufferMb(),
                'disk_total_gb'       => round(($srv['disk']['total'] ?? 0) / 1024 ** 3, 1),
                'disk_free_gb'        => round(($srv['disk']['free']  ?? 0) / 1024 ** 3, 1),
                'disk_used_pct'       => $this->diskUsagePct($srv),
                'os'                  => $srv['os'],
                'hostname'            => $srv['hostname'],
                'opcache_enabled'     => $srv['opcache']['enabled'] ?? false,
                'opcache_hit_rate'    => $srv['opcache']['hit_rate'] ?? null,
                'max_exec_sec'        => $srv['max_execution'],
                'upload_max'          => $srv['upload_max'],
                'post_max'            => $srv['post_max'],
                'cache_driver'        => config('cache.default'),
                'queue_driver'        => config('queue.default'),
                'session_driver'      => config('session.driver'),
            ];
        });
    }

    // ═══════════════════════════════════════════════════════════════════
    // 2. Capacity estimate  (max concurrent users this box can handle)
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Returns a breakdown of capacity per tier. The weakest tier is the
     * bottleneck — that's how many concurrent users we can actually serve.
     *
     * @param array $profileOverride  partial profile to override defaults
     */
    public function capacityEstimate(array $profileOverride = []): array
    {
        $p = array_merge(self::DEFAULTS, array_filter($profileOverride, fn ($v) => $v !== null && $v !== ''));
        $specs = $this->serverSpecs();

        // ─── Tier 1: PHP-FPM worker pool (web tier) ───
        // Rule of thumb: allocate 60% of box RAM to PHP workers, rest to OS + MySQL
        $usableRamMb = max(256, (int) ($specs['total_ram_mb'] * 0.6));
        $maxWorkers  = max(1, intdiv($usableRamMb, $p['ram_per_worker_mb']));
        $reqPerSec   = $maxWorkers * (1000 / max(1, $p['avg_req_duration_ms']));
        $reqPerMin   = $reqPerSec * 60;
        $webConc     = (int) ($reqPerMin / max(1, $p['avg_req_per_user_per_min'] * $p['peak_multiplier']));

        // ─── Tier 2: CPU cores ───
        // Each core handles ~(1000 / req_ms) req/s concurrently
        $cpuReqPerSec = $specs['cpu_cores'] * (1000 / max(1, $p['avg_req_duration_ms']));
        $cpuConc = (int) (($cpuReqPerSec * 60) / max(1, $p['avg_req_per_user_per_min'] * $p['peak_multiplier']));

        // ─── Tier 3: Database connections ───
        $dbConn    = $specs['mysql_max_conn'] ?: 150;
        $dbSafe    = (int) ($dbConn * 0.7);
        $dbReqPerSec = $dbSafe * (1000 / max(1, $p['db_queries_per_request'] * $p['db_query_avg_ms']));
        $dbConc    = (int) (($dbReqPerSec * 60) / max(1, $p['avg_req_per_user_per_min'] * $p['peak_multiplier']));

        // ─── Tier 4: Memory headroom (session + page cache + misc) ───
        // Roughly: sessions cost ~50KB each, cache ~100KB per active user
        $memBudgetMb = max(64, (int) ($specs['total_ram_mb'] * 0.1));
        $memConc    = intdiv($memBudgetMb * 1024, 150); // 150KB/user

        // ─── Tier 5: Disk I/O (matters for uploads) ───
        $diskUploadsPerSec = $p['disk_write_mbps'] / max(0.1, $p['avg_photo_size_mb']);
        $uploadConc        = (int) ($diskUploadsPerSec / max(1, $p['peak_multiplier']));

        $tiers = [
            'web_workers' => $webConc,
            'cpu_cores'   => $cpuConc,
            'database'    => $dbConc,
            'memory'      => $memConc,
        ];

        asort($tiers);
        $bottleneckTier   = array_key_first($tiers);
        $bottleneckValue  = reset($tiers);

        $safe = (int) ($bottleneckValue * (1 - $p['safety_headroom_pct'] / 100));

        return [
            'profile'           => $p,
            'max_workers'       => $maxWorkers,
            'req_per_sec'       => round($reqPerSec, 1),
            'tiers'             => $tiers,
            'bottleneck_tier'   => $bottleneckTier,
            'bottleneck_value'  => $bottleneckValue,
            'recommended_max'   => $bottleneckValue,
            'safe_concurrent'   => $safe,
            'upload_concurrent' => $uploadConc,
            'usable_ram_mb'     => $usableRamMb,
        ];
    }

    // ═══════════════════════════════════════════════════════════════════
    // 3. Current load  (what's happening right now)
    // ═══════════════════════════════════════════════════════════════════

    public function currentLoad(): array
    {
        $online = 0;
        try {
            if (\Schema::hasTable('user_sessions')) {
                $online = (int) DB::table('user_sessions')
                    ->where('last_activity_at', '>=', now()->subMinutes(5))
                    ->count();
            }
        } catch (\Throwable $e) {}

        $dbThreads = 0;
        try {
            $driver = DB::connection()->getDriverName();
            if ($driver === 'pgsql') {
                $dbThreads = (int) (DB::selectOne("SELECT count(*) AS c FROM pg_stat_activity")?->c ?? 0);
            } else {
                $dbThreads = (int) (DB::selectOne("SHOW STATUS LIKE 'Threads_connected'")?->Value ?? 0);
            }
        } catch (\Throwable $e) {}

        $queuePending = 0;
        try {
            $queuePending = (int) DB::table('jobs')->count();
        } catch (\Throwable $e) {}

        $loadAvg = function_exists('sys_getloadavg') ? sys_getloadavg() : null;
        $cpuCores = $this->detectCpuCores();

        // Memory %
        $memLimitB = $this->parseMemoryLimit((string) ini_get('memory_limit'));
        $memPct = $memLimitB > 0 ? round(memory_get_usage(true) / $memLimitB * 100, 1) : 0;

        // CPU % from load avg (1-min)
        $cpuPct = $loadAvg
            ? round(min(100, $loadAvg[0] / max(1, $cpuCores) * 100), 1)
            : null;

        $srv = $this->monitor->server();
        $diskPct = $this->diskUsagePct($srv);

        return [
            'online_users'     => $online,
            'db_connections'   => $dbThreads,
            'queue_pending'    => $queuePending,
            'load_avg_1m'      => $loadAvg[0] ?? null,
            'load_avg_5m'      => $loadAvg[1] ?? null,
            'cpu_pct'          => $cpuPct,
            'memory_pct'       => $memPct,
            'disk_pct'         => $diskPct,
            'cpu_cores'        => $cpuCores,
        ];
    }

    // ═══════════════════════════════════════════════════════════════════
    // 4. What-if scenario  (hit 5k concurrent — what breaks?)
    // ═══════════════════════════════════════════════════════════════════

    public function whatIf(int $targetConcurrent, array $profileOverride = []): array
    {
        $cap = $this->capacityEstimate($profileOverride);

        $utilization = [];
        foreach ($cap['tiers'] as $tier => $max) {
            $pct = $max > 0 ? min(200, round($targetConcurrent / $max * 100, 1)) : 0;
            $status = $pct < 50 ? 'ok' : ($pct < 80 ? 'warn' : ($pct < 100 ? 'hot' : 'critical'));
            $utilization[$tier] = [
                'target' => $targetConcurrent,
                'max'    => $max,
                'pct'    => $pct,
                'status' => $status,
            ];
        }

        // Recommendations for any tier that breaks
        $recos = [];
        foreach ($utilization as $tier => $u) {
            if ($u['status'] === 'critical' || $u['status'] === 'hot') {
                $recos[] = [
                    'tier'    => $tier,
                    'urgent'  => $u['status'] === 'critical',
                    'message' => $this->scaleRec($tier, $u['max'], $targetConcurrent, $cap['profile']),
                ];
            }
        }

        return [
            'target'           => $targetConcurrent,
            'utilization'      => $utilization,
            'capacity_now'     => $cap['recommended_max'],
            'safe_now'         => $cap['safe_concurrent'],
            'reaches_limit'    => $targetConcurrent > $cap['safe_concurrent'],
            'exceeds_max'      => $targetConcurrent > $cap['recommended_max'],
            'recommendations'  => $recos,
        ];
    }

    // ═══════════════════════════════════════════════════════════════════
    // 5. Growth projection  (when will we need to upgrade?)
    // ═══════════════════════════════════════════════════════════════════

    public function growthProjection(): array
    {
        $now = now();
        $d30 = $now->copy()->subDays(30);
        $d60 = $now->copy()->subDays(60);
        $d90 = $now->copy()->subDays(90);

        $users30 = $this->safeCount('auth_users', fn ($q) => $q->where('created_at', '>=', $d30));
        $users60 = $this->safeCount('auth_users', fn ($q) => $q->whereBetween('created_at', [$d60, $d30]));
        $users90 = $this->safeCount('auth_users', fn ($q) => $q->whereBetween('created_at', [$d90, $d60]));

        $photos30 = $this->safeCount('event_photos', fn ($q) => $q->where('created_at', '>=', $d30));
        $orders30 = $this->safeCount('orders',       fn ($q) => $q->where('created_at', '>=', $d30));
        $events30 = $this->safeCount('event_events', fn ($q) => $q->where('created_at', '>=', $d30));

        $avgNewPerDay = round($users30 / 30, 2);
        $growthPct    = $users60 > 0 ? round(($users30 - $users60) / $users60 * 100, 1) : null;

        // Extrapolate: current online vs safe capacity
        $safe   = $this->capacityEstimate()['safe_concurrent'];
        $online = $this->currentLoad()['online_users'];
        $slack  = max(0, $safe - $online);

        // Rough: if online grows proportional to total users, project when online hits safe
        // (assumes online ≈ 1-3% of registered, but we just use the slack delta)
        $daysUntilCap = $avgNewPerDay > 0 ? (int) floor($slack / $avgNewPerDay) : null;
        $hitDate      = $daysUntilCap !== null ? $now->copy()->addDays($daysUntilCap)->format('Y-m-d') : null;

        return [
            'users_last_30d'        => $users30,
            'users_prev_30d'        => $users60,
            'users_prev2_30d'       => $users90,
            'photos_last_30d'       => $photos30,
            'orders_last_30d'       => $orders30,
            'events_last_30d'       => $events30,
            'avg_new_users_per_day' => $avgNewPerDay,
            'growth_rate_pct'       => $growthPct,
            'current_online'        => $online,
            'safe_capacity'         => $safe,
            'capacity_slack'        => $slack,
            'days_until_capacity'   => $daysUntilCap,
            'projected_hit_date'    => $hitDate,
        ];
    }

    // ═══════════════════════════════════════════════════════════════════
    // 6. Cost per user  (ties into BusinessExpense)
    // ═══════════════════════════════════════════════════════════════════

    public function costPerUser(?int $usersOverride = null): array
    {
        $users  = $usersOverride ?? max(1, $this->safeCount('auth_users'));
        $photos = max(1, $this->safeCount('event_photos'));
        $orders = max(1, $this->safeCount('orders'));
        $events = max(1, $this->safeCount('event_events'));

        // Active users (logged in last 30 days)
        $active = $this->safeCount('auth_users', fn ($q) => $q->where('last_login_at', '>=', now()->subDays(30)));
        $active = max(1, $active);

        $totalMonthly = 0.0;
        try {
            $totalMonthly = BusinessExpense::active()->get()
                ->sum(fn ($e) => $e->monthlyCost());
        } catch (\Throwable $e) {
            // model or table may not exist yet
        }

        return [
            'total_monthly'      => round($totalMonthly, 2),
            'total_yearly'       => round($totalMonthly * 12, 2),
            'cost_per_user'      => round($totalMonthly / $users, 2),
            'cost_per_active'    => round($totalMonthly / $active, 2),
            'cost_per_photo'     => round($totalMonthly / $photos, 4),
            'cost_per_order'     => round($totalMonthly / $orders, 2),
            'cost_per_event'     => round($totalMonthly / $events, 2),
            'users'              => $users,
            'active_users'       => $active,
            'photos'             => $photos,
            'orders'             => $orders,
            'events'             => $events,
        ];
    }

    // ═══════════════════════════════════════════════════════════════════
    //  ── Internal helpers ─────────────────────────────────────────────
    // ═══════════════════════════════════════════════════════════════════

    protected function scaleRec(string $tier, int $currentMax, int $target, array $profile): string
    {
        $multiplier = $currentMax > 0 ? ceil($target / $currentMax) : 2;

        return match ($tier) {
            'web_workers' => "เพิ่ม RAM ×{$multiplier} (หรือเพิ่ม PHP-FPM workers) — ปัจจุบันพอรองรับ " . number_format($currentMax) . " คน",
            'cpu_cores'   => "เพิ่ม CPU cores เป็น ×{$multiplier} — งานจะรอคิวมากขึ้นถ้ามีผู้ใช้ " . number_format($target) . " คน",
            'database'    => "เพิ่ม MySQL max_connections หรือเพิ่ม read replica — ปัจจุบันรับได้ " . number_format($currentMax) . " คน",
            'memory'      => "Upgrade RAM หรือย้าย cache/session ไป Redis — cap ปัจจุบัน " . number_format($currentMax) . " คน",
            default       => "Scale tier: {$tier}",
        };
    }

    protected function detectCpuCores(): int
    {
        return Cache::remember('capacity:cpu', 3600, function () {
            if (PHP_OS_FAMILY === 'Linux' && function_exists('shell_exec')) {
                $out = @shell_exec('nproc 2>/dev/null');
                if (is_numeric(trim((string) $out))) return (int) trim($out);
            }
            if (PHP_OS_FAMILY === 'Windows') {
                $env = getenv('NUMBER_OF_PROCESSORS');
                if (is_numeric($env)) return (int) $env;
            }
            if (is_readable('/proc/cpuinfo')) {
                $count = substr_count((string) @file_get_contents('/proc/cpuinfo'), 'processor');
                if ($count > 0) return $count;
            }
            return 2; // safe fallback
        });
    }

    protected function detectTotalRamMb(): int
    {
        return Cache::remember('capacity:ram', 3600, function () {
            if (is_readable('/proc/meminfo')) {
                $meminfo = (string) @file_get_contents('/proc/meminfo');
                if (preg_match('/MemTotal:\s+(\d+)\s+kB/', $meminfo, $m)) {
                    return intdiv((int) $m[1], 1024);
                }
            }
            if (PHP_OS_FAMILY === 'Windows' && function_exists('shell_exec')) {
                // wmic is deprecated but still ships on most Windows 10/11 boxes
                $out = @shell_exec('wmic ComputerSystem get TotalPhysicalMemory /format:value 2>nul');
                if ($out && preg_match('/=(\d+)/', $out, $m)) {
                    return intdiv((int) $m[1], 1024 * 1024);
                }
                // PowerShell fallback
                $ps = @shell_exec('powershell -NoProfile -Command "(Get-CimInstance Win32_ComputerSystem).TotalPhysicalMemory" 2>nul');
                if (is_numeric(trim((string) $ps))) {
                    return intdiv((int) trim($ps), 1024 * 1024);
                }
            }
            return 4096; // 4GB fallback
        });
    }

    protected function detectMysqlMaxConn(): int
    {
        try {
            $driver = DB::connection()->getDriverName();
            if ($driver === 'pgsql') {
                // Postgres exposes max_connections via pg_settings
                $v = DB::selectOne("SELECT setting::int AS val FROM pg_settings WHERE name = 'max_connections'");
                return (int) ($v->val ?? 150);
            }
            $v = DB::selectOne("SHOW VARIABLES LIKE 'max_connections'");
            return (int) ($v->Value ?? 150);
        } catch (\Throwable $e) {
            return 150;
        }
    }

    protected function detectInnodbBufferMb(): int
    {
        try {
            $driver = DB::connection()->getDriverName();
            if ($driver === 'pgsql') {
                // Postgres equivalent of InnoDB buffer pool is shared_buffers,
                // stored in 8KB pages — convert to MB.
                $v = DB::selectOne("SELECT setting::bigint AS val FROM pg_settings WHERE name = 'shared_buffers'");
                $bytes = (int) ($v->val ?? 0) * 8192;
                return intdiv($bytes, 1024 * 1024);
            }
            $v = DB::selectOne("SHOW VARIABLES LIKE 'innodb_buffer_pool_size'");
            return intdiv((int) ($v->Value ?? 0), 1024 * 1024);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    protected function diskUsagePct(array $srv): float
    {
        $total = (int) ($srv['disk']['total'] ?? 0);
        $free  = (int) ($srv['disk']['free']  ?? 0);
        if ($total <= 0) return 0;
        return round(($total - $free) / $total * 100, 1);
    }

    protected function parseMemoryLimit(string $v): int
    {
        if ($v === '' || $v === '-1') return PHP_INT_MAX;
        $unit = strtoupper(substr(trim($v), -1));
        $num  = (int) $v;
        return match ($unit) {
            'G' => $num * 1024 * 1024 * 1024,
            'M' => $num * 1024 * 1024,
            'K' => $num * 1024,
            default => $num,
        };
    }

    protected function safeCount(string $table, ?\Closure $query = null): int
    {
        try {
            if (!\Schema::hasTable($table)) return 0;
            $q = DB::table($table);
            if ($query) $query($q);
            return (int) $q->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }
}

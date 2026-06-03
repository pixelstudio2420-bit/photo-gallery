<?php

namespace App\Console\Commands;

use App\Models\AdminNotification;
use App\Services\RouteHealthService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Route & Page Health check.
 *
 * Hits a curated set of public routes through the internal HTTP kernel and
 * flags any that 5xx (or render an error inline despite a 2xx). On failure it
 * pings the admin bell so ops sees broken pages before customers do.
 *
 * This is the regression net for the class of bug that's bitten production
 * here: /photographers?specialty=X 500 (JSONB ? vs PDO) and the festival-popup
 * ::interval 500 — both would have been caught by this on the next run.
 *
 * Scheduled daily 06:10. Safe to run anytime; GET-only, no state mutation.
 *
 *   php artisan routes:health
 *   php artisan routes:health --quiet-if-clean    # only output on warn/fail
 *   php artisan routes:health --no-persist         # don't write history rows
 */
class RouteHealthCommand extends Command
{
    protected $signature = 'routes:health
        {--quiet-if-clean : Suppress output when every target is OK}
        {--no-persist     : Do not write history rows (still alerts)}
        {--no-alert       : Do not ping the admin bell on failures}';

    protected $description = 'ยิงเช็ค public routes จริง จับ 5xx / error page เพื่อกัน bug หลุดถึงลูกค้า';

    public function handle(RouteHealthService $svc): int
    {
        // Cap execution so a hung route can't wedge the scheduler.
        @set_time_limit(120);

        $snapshot = $svc->runAll(persist: !$this->option('no-persist'));
        $summary  = $snapshot['summary'];
        $clean    = $summary['fail'] === 0 && $summary['warn'] === 0;

        if ($this->option('quiet-if-clean') && $clean) {
            return self::SUCCESS;
        }

        $this->line('');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->line(' Route & Page Health');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        foreach ($snapshot['results'] as $r) {
            $color = match ($r['result']) {
                'fail' => 'red',
                'warn' => 'yellow',
                default => 'green',
            };
            $tag = strtoupper($r['result']);
            $this->line(sprintf(
                "  <fg=%s>%-4s</> %-26s <fg=gray>%3sms</> [%s] %s%s",
                $color,
                $tag,
                substr($r['key'], 0, 26),
                $r['duration_ms'],
                $r['status'],
                $r['path'],
                $r['error'] ? "  — {$r['error']}" : ''
            ));
        }

        $this->line('');
        $this->line(sprintf(
            ' Summary: <fg=green>%d ok</> · <fg=yellow>%d warn</> · <fg=%s>%d fail</> · slowest %dms',
            $summary['ok'],
            $summary['warn'],
            $summary['fail'] ? 'red' : 'gray',
            $summary['fail'],
            $summary['slowest_ms']
        ));
        $this->line('');

        if ($summary['fail'] > 0 && !$this->option('no-alert')) {
            $this->alert("{$summary['fail']} route(s) failing");
            $this->pingAdmins($snapshot);
        }

        // Non-zero exit on failure so CI / cron wrappers can detect it.
        return $summary['fail'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function pingAdmins(array $snapshot): void
    {
        try {
            $failed = array_filter($snapshot['results'], fn ($r) => $r['result'] === 'fail');
            $names  = implode(', ', array_map(fn ($r) => $r['key'], array_slice($failed, 0, 5)));
            $more   = count($failed) > 5 ? ' +' . (count($failed) - 5) . ' more' : '';

            // refId dedups to one bell entry per run so a flapping route doesn't
            // spam the bell within a single check cycle.
            AdminNotification::notify(
                type:    'route_health_fail',
                title:   "🚑 Route Health: {$snapshot['summary']['fail']} หน้าใช้งานไม่ได้",
                message: "หน้าที่ล้ม: {$names}{$more}",
                link:    'admin/health',
                refId:   'route_health:' . $snapshot['run_id'],
            );
        } catch (\Throwable $e) {
            Log::warning('RouteHealth: admin ping failed: ' . $e->getMessage());
        }
    }
}

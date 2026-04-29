<?php

namespace App\Console\Commands;

use App\Services\SystemMonitorService;
use Illuminate\Console\Command;

/**
 * CLI wrapper around SystemMonitorService.
 *
 * Prints a formatted health snapshot + optional readiness scorecard,
 * and returns a non-zero exit code if any readiness check fails — so it
 * can be wired into CI/CD or cron-based alerting.
 *
 * Usage:
 *   php artisan system:health                 Full snapshot + readiness (pretty)
 *   php artisan system:health --readiness     Only the readiness scorecard
 *   php artisan system:health --snapshot      Only the metrics snapshot
 *   php artisan system:health --json          Machine-readable JSON output
 *   php artisan system:health --fail-under=90 Exit 1 if score < threshold
 */
class SystemHealthCommand extends Command
{
    protected $signature = 'system:health
                            {--readiness : Only show the readiness scorecard}
                            {--snapshot : Only show the metrics snapshot}
                            {--json : Emit machine-readable JSON}
                            {--fail-under=0 : Exit with code 1 if score < N (0 = never)}';

    protected $description = 'Print system health metrics and production-readiness scorecard';

    public function handle(SystemMonitorService $mon): int
    {
        $wantReadiness = $this->option('readiness') || !$this->option('snapshot');
        $wantSnapshot  = $this->option('snapshot')  || !$this->option('readiness');

        $data = [];
        if ($wantSnapshot)  $data['snapshot']  = $mon->snapshot();
        if ($wantReadiness) $data['readiness'] = $mon->readiness();

        if ($this->option('json')) {
            $this->line(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return $this->exitCode($data);
        }

        if ($wantSnapshot) {
            $this->renderSnapshot($data['snapshot']);
        }
        if ($wantReadiness) {
            $this->renderReadiness($data['readiness']);
        }

        return $this->exitCode($data);
    }

    // ═══ Snapshot rendering ═════════════════════════════════════════

    protected function renderSnapshot(array $s): void
    {
        $this->newLine();
        $this->line('<bg=blue;fg=white> SYSTEM SNAPSHOT </> <fg=gray>' . ($s['generated_at'] ?? '') . '</>');
        $this->newLine();

        // Server
        $sv = $s['server'];
        $this->components->twoColumnDetail('<fg=cyan>Server</>', '');
        $this->components->twoColumnDetail('  PHP / Laravel', $sv['php_version'] . ' / ' . $sv['laravel_version']);
        $this->components->twoColumnDetail('  Environment', $sv['app_env'] . ($sv['app_debug'] ? ' <fg=red>(debug on)</>' : ''));
        $this->components->twoColumnDetail('  Memory (current / peak / limit)',
            SystemMonitorService::formatBytes($sv['memory']['current']) . ' / '
            . SystemMonitorService::formatBytes($sv['memory']['peak']) . ' / '
            . ($sv['memory']['limit_display'] ?: '—')
        );
        $this->components->twoColumnDetail('  Disk (free / total)',
            SystemMonitorService::formatBytes((int) $sv['disk']['free']) . ' / '
            . SystemMonitorService::formatBytes((int) $sv['disk']['total'])
        );
        if (!empty($sv['load_avg'])) {
            $this->components->twoColumnDetail('  Load avg (1m, 5m, 15m)',
                number_format($sv['load_avg'][0], 2) . ', '
                . number_format($sv['load_avg'][1], 2) . ', '
                . number_format($sv['load_avg'][2], 2)
            );
        }
        $this->components->twoColumnDetail('  OPcache',
            ($sv['opcache']['enabled'] ?? false)
                ? 'enabled — hit rate ' . ($sv['opcache']['hit_rate'] ?? 0) . '%'
                : '<fg=yellow>disabled</>'
        );

        // Database
        $db = $s['database'];
        $this->newLine();
        $this->components->twoColumnDetail('<fg=cyan>Database</>', '');
        if ($db['connected'] ?? false) {
            $this->components->twoColumnDetail('  Driver / Version', $db['driver'] . ' / ' . ($db['version'] ?? '—'));
            $this->components->twoColumnDetail('  Total size', SystemMonitorService::formatBytes((int) ($db['total_bytes'] ?? 0)));
            $this->components->twoColumnDetail('  Connections / Slow queries', ($db['connections'] ?? 0) . ' / ' . ($db['slow_queries'] ?? 0));
            $this->components->twoColumnDetail('  Uptime', $this->humanDuration((int) ($db['uptime_sec'] ?? 0)));
        } else {
            $this->components->twoColumnDetail('  <fg=red>Connection</>', 'FAILED — ' . ($db['error'] ?? 'unknown'));
        }

        // Cache + Queue
        $cache = $s['cache'];
        $q = $s['queue'];
        $this->newLine();
        $this->components->twoColumnDetail('<fg=cyan>Cache / Queue</>', '');
        $this->components->twoColumnDetail('  Cache driver', $cache['driver'] . ($cache['ok'] ? ' <fg=green>✓</>' : ' <fg=red>✗</>'));
        $this->components->twoColumnDetail('  Queue driver', $q['driver']);
        $this->components->twoColumnDetail('  Jobs (pending / failed)', ($q['pending'] ?? 0) . ' / ' . ($q['failed'] ?? 0));
        if (($q['oldest_pending_s'] ?? 0) > 0) {
            $this->components->twoColumnDetail('  Oldest pending job', $this->humanDuration((int) $q['oldest_pending_s']) . ' ago');
        }

        // Storage
        $st = $s['storage'];
        $this->newLine();
        $this->components->twoColumnDetail('<fg=cyan>Storage</>', '');
        $this->components->twoColumnDetail('  Primary / Upload / Zip',
            $st['resolved']['primary'] . ' / ' . $st['resolved']['upload'] . ' / ' . $st['resolved']['zip']);
        if (!empty($st['resolved']['mirrors'])) {
            $this->components->twoColumnDetail('  Mirrors', implode(', ', $st['resolved']['mirrors']));
        }
        foreach ($st['drivers'] as $name => $d) {
            $icon = $d['enabled'] ? '<fg=green>●</>' : '<fg=gray>○</>';
            $label = '  ' . $icon . ' ' . str_pad(strtoupper($name), 8);
            $val = $d['photo_count'] . ' photos';
            if ($d['total_bytes']) $val .= ' · ' . SystemMonitorService::formatBytes($d['total_bytes']);
            if ($d['growth_24h']) $val .= ' · +' . SystemMonitorService::formatBytes($d['growth_24h']) . ' /24h';
            $this->components->twoColumnDetail($label, $val);
        }
        $this->components->twoColumnDetail('  Local disk (free / total)',
            SystemMonitorService::formatBytes((int) $st['local_disk']['free']) . ' / '
            . SystemMonitorService::formatBytes((int) $st['local_disk']['total'])
        );

        // Downloads
        $dl = $s['downloads'];
        $this->newLine();
        $this->components->twoColumnDetail('<fg=cyan>Downloads</>', '');
        $this->components->twoColumnDetail('  Tokens (total / active)', ($dl['tokens_total'] ?? 0) . ' / ' . ($dl['tokens_active'] ?? 0));
        $this->components->twoColumnDetail('  Downloads (24h / 7d / all)',
            ($dl['downloads_today'] ?? 0) . ' / ' . ($dl['downloads_7d'] ?? 0) . ' / ' . ($dl['downloads_all'] ?? 0)
        );
        $this->components->twoColumnDetail('  ZIP jobs pending', (string) ($dl['zip_pending'] ?? 0));

        // Data
        $data = $s['data'];
        $this->newLine();
        $this->components->twoColumnDetail('<fg=cyan>Data volume</>', '');
        $this->components->twoColumnDetail('  Events (active / total)', ($data['events_active'] ?? 0) . ' / ' . ($data['events'] ?? 0));
        $this->components->twoColumnDetail('  Photos (active / total)', ($data['photos_active'] ?? 0) . ' / ' . ($data['photos'] ?? 0));
        $this->components->twoColumnDetail('  Orders (paid / total)', ($data['orders_paid'] ?? 0) . ' / ' . ($data['orders'] ?? 0));
        $this->components->twoColumnDetail('  Users', (string) ($data['users'] ?? 0));
        $this->components->twoColumnDetail('  New in 24h (events/photos/orders/users)',
            ($data['new_events_24h'] ?? 0) . ' / '
            . ($data['new_photos_24h'] ?? 0) . ' / '
            . ($data['new_orders_24h'] ?? 0) . ' / '
            . ($data['new_users_24h'] ?? 0)
        );
    }

    // ═══ Readiness rendering ════════════════════════════════════════

    protected function renderReadiness(array $r): void
    {
        $this->newLine();
        $tierColor = match ($r['tier']) {
            'production-ready' => 'green',
            'staging'          => 'cyan',
            'development'      => 'yellow',
            default            => 'red',
        };
        $this->line('<bg=' . $tierColor . ';fg=white> READINESS SCORECARD </> '
            . '<fg=' . $tierColor . '>Score: ' . $r['score'] . '/100 · Tier: ' . strtoupper($r['tier']) . '</>');
        $this->line("<fg=green>✓ {$r['passed']}</>  <fg=yellow>⚠ {$r['warn']}</>  <fg=red>✗ {$r['failed']}</>  /  {$r['total']} checks");
        $this->newLine();

        // Group checks by category
        $byCat = [];
        foreach ($r['checks'] as $c) $byCat[$c['category']][] = $c;

        foreach ($byCat as $cat => $items) {
            $this->line('<fg=cyan;options=bold>' . strtoupper($cat) . '</>');
            foreach ($items as $c) {
                [$icon, $color] = match ($c['status']) {
                    'ok'   => ['✓', 'green'],
                    'warn' => ['⚠', 'yellow'],
                    default => ['✗', 'red'],
                };
                $line = "  <fg={$color}>{$icon}</> " . $c['name'];
                if (!empty($c['note'])) {
                    $line .= "\n    <fg=gray>→ " . $c['note'] . '</>';
                }
                $this->line($line);
            }
            $this->newLine();
        }
    }

    // ═══ Helpers ════════════════════════════════════════════════════

    protected function humanDuration(int $seconds): string
    {
        if ($seconds < 60)    return $seconds . 's';
        if ($seconds < 3600)  return floor($seconds / 60) . 'm ' . ($seconds % 60) . 's';
        if ($seconds < 86400) return floor($seconds / 3600) . 'h ' . floor(($seconds % 3600) / 60) . 'm';
        return floor($seconds / 86400) . 'd ' . floor(($seconds % 86400) / 3600) . 'h';
    }

    protected function exitCode(array $data): int
    {
        $threshold = (int) $this->option('fail-under');
        if ($threshold <= 0) return self::SUCCESS;

        $score = $data['readiness']['score'] ?? 100;
        if ($score < $threshold) {
            $this->error("Readiness score {$score} is below threshold {$threshold} — failing.");
            return self::FAILURE;
        }
        return self::SUCCESS;
    }
}

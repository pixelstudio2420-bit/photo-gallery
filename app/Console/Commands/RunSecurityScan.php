<?php

namespace App\Console\Commands;

use App\Models\AdminNotification;
use App\Services\SecurityScannerService;
use Illuminate\Console\Command;

class RunSecurityScan extends Command
{
    protected $signature = 'security:scan {--quiet-if-clean : Suppress output when score == 100}';

    protected $description = 'รัน security scanner เต็มชุด (14 checks) เก็บคะแนน + ปั๊มแจ้งเตือนเมื่อเจอ critical/high';

    public function handle(SecurityScannerService $scanner): int
    {
        $quiet = (bool) $this->option('quiet-if-clean');

        try {
            $result = $scanner->runFullScan();
        } catch (\Throwable $e) {
            $this->error('Security scan failed: ' . $e->getMessage());
            return self::FAILURE;
        }

        $score    = (int) ($result['score'] ?? 0);
        $findings = $result['findings'] ?? [];

        // Tally fails by severity for the admin bell.
        $bySeverity = ['critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0];
        $criticalNames = [];
        foreach ($findings as $f) {
            if (($f['status'] ?? '') !== 'fail') {
                continue;
            }
            $sev = $f['severity'] ?? 'low';
            if (isset($bySeverity[$sev])) {
                $bySeverity[$sev]++;
            }
            if ($sev === 'critical') {
                $criticalNames[] = $f['name'] ?? 'Unknown';
            }
        }

        // Push an admin notification if anything critical/high turned up.
        if ($bySeverity['critical'] > 0 || $bySeverity['high'] > 0) {
            $severity = $bySeverity['critical'] > 0 ? 'critical' : 'high';
            $title    = "Security scan: คะแนน {$score}/100";
            $message  = sprintf(
                'พบ %d critical, %d high, %d medium %s',
                $bySeverity['critical'],
                $bySeverity['high'],
                $bySeverity['medium'],
                $criticalNames ? ' — ' . implode(', ', array_slice($criticalNames, 0, 3)) : ''
            );
            AdminNotification::securityAlert($title, $message, $severity);
        }

        if ($quiet && $score === 100) {
            return self::SUCCESS;
        }

        $this->info("Security scan finished — score {$score}/100");
        $this->line(sprintf(
            '  critical: %d   high: %d   medium: %d   low: %d',
            $bySeverity['critical'],
            $bySeverity['high'],
            $bySeverity['medium'],
            $bySeverity['low']
        ));

        return self::SUCCESS;
    }
}

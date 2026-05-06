<?php

namespace App\Console\Commands;

use App\Services\Payment\PaymentReadinessService;
use Illuminate\Console\Command;

/**
 * `php artisan payment:readiness` — print a checklist of every condition
 * the subscription purchase flow needs to be live.
 *
 * Designed to run on production (Laravel Cloud's console) so an operator
 * can answer "ระบบซื้อแผนใช้งานได้แล้วใช่ไหม?" with one command. Mirrors
 * the admin web page at /admin/payment-readiness — both call the same
 * PaymentReadinessService.
 *
 * Exit codes:
 *   0 — every CRITICAL check passes (warnings allowed)
 *   1 — at least one critical check fails
 *
 * Useful in CI / health probes:
 *   php artisan payment:readiness --quiet ; echo $?
 */
class PaymentReadinessCommand extends Command
{
    protected $signature = 'payment:readiness
                            {--json : Emit machine-readable JSON instead of a human report}';

    protected $description = 'Diagnose whether the subscription purchase flow is live (gateways, plans, automation)';

    public function handle(PaymentReadinessService $svc): int
    {
        $report = $svc->run();

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            return $report['ready'] ? self::SUCCESS : self::FAILURE;
        }

        $this->printHumanReport($report);

        return $report['ready'] ? self::SUCCESS : self::FAILURE;
    }

    private function printHumanReport(array $report): void
    {
        $this->newLine();
        $this->line('─── ระบบซื้อแผน · Readiness Check ───');
        $this->newLine();

        // Top-level status banner
        if ($report['ready']) {
            $this->info('✅ READY — ลูกค้าซื้อแผนได้');
        } elseif ($report['ready_for_free_only']) {
            $this->warn('⚠️  PARTIAL — Free plan ใช้ได้ แต่ paid plan ยังซื้อไม่ได้');
        } else {
            $this->error('❌ NOT READY — ลูกค้าซื้อแผนไม่ได้');
        }
        $this->newLine();

        $this->line(sprintf(
            'รวม %d checks · ผ่าน %d · critical fail %d · warn %d',
            $report['total'],
            $report['passed'],
            $report['critical_failed'],
            $report['warn_failed']
        ));
        $this->newLine();

        // Detailed checklist
        $rows = [];
        foreach ($report['checks'] as $c) {
            $icon = $c['pass']
                ? '<fg=green>✓</>'
                : ($c['level'] === 'critical' ? '<fg=red>✗</>' : '<fg=yellow>!</>');
            $rows[] = [
                'icon'   => $icon,
                'label'  => $c['label'],
                'level'  => $c['level'] === 'critical' ? '<fg=red>CRITICAL</>' : '<fg=yellow>warn</>',
                'detail' => $c['detail'],
            ];
        }
        $this->table(['', 'Check', 'Severity', 'Detail'], $rows);
        $this->newLine();

        // Per-gateway summary
        $this->line('─── Gateway summary ───');
        $gwRows = [];
        foreach ($report['gateway_summary'] as $g) {
            $gwRows[] = [
                'icon'   => $g['ready'] ? '<fg=green>✓</>' : '<fg=red>✗</>',
                'type'   => $g['type'],
                'label'  => $g['label'],
                'reason' => $g['reason'],
            ];
        }
        $this->table(['', 'method_type', 'Label', 'Status / Blocker'], $gwRows);
        $this->newLine();

        // Action items — only failed checks, sorted critical first
        $failed = collect($report['checks'])->where('pass', false)->sortBy(fn($c) => $c['level'] === 'critical' ? 0 : 1);
        if ($failed->isNotEmpty()) {
            $this->line('─── สิ่งที่ต้องแก้ ───');
            foreach ($failed as $c) {
                $marker = $c['level'] === 'critical' ? '<fg=red>BLOCK</>' : '<fg=yellow>fix</>';
                $this->line("  {$marker}  {$c['label']}");
                $this->line("       → {$c['fix']}");
                if (!empty($c['fix_url'])) {
                    $this->line("       <fg=cyan>{$c['fix_url']}</>");
                }
                $this->newLine();
            }
        }
    }
}

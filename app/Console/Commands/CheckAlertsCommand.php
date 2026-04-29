<?php

namespace App\Console\Commands;

use App\Services\AlertEvaluatorService;
use Illuminate\Console\Command;

class CheckAlertsCommand extends Command
{
    protected $signature = 'alerts:check {--quiet-if-none : Only output if something triggered}';

    protected $description = 'Evaluate every active alert rule and fire notifications when conditions match.';

    public function handle(AlertEvaluatorService $svc): int
    {
        $result = $svc->run();

        if ($this->option('quiet-if-none') && $result['triggered'] === 0) {
            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Alerts checked=%d triggered=%d skipped_cooldown=%d',
            $result['checked'],
            $result['triggered'],
            $result['skipped_cooldown']
        ));

        foreach ($result['details'] as $d) {
            $this->line(' - [' . ($d['status'] ?? '?') . '] ' . ($d['rule'] ?? '?')
                . (isset($d['value']) ? ' (value=' . $d['value'] . ')' : '')
                . (isset($d['channels']) ? ' → ' . implode(',', $d['channels']) : ''));
        }

        return self::SUCCESS;
    }
}

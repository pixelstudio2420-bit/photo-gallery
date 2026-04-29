<?php

namespace App\Console\Commands;

use App\Services\CreditService;
use Illuminate\Console\Command;

/**
 * Nightly sweep: zero out photographer credit bundles whose `expires_at`
 * has passed and write an EXPIRE row to the ledger for each one.
 *
 * Safe to run repeatedly — the service only touches bundles with
 * credits_remaining > 0 AND expires_at in the past, so subsequent runs
 * on the same calendar day are no-ops.
 *
 * Kept as an artisan command (rather than a queued Job) so it shows up
 * in `artisan list`, is easy to dry-run from the shell during incident
 * response, and can be invoked ad-hoc by ops without dispatching to a
 * worker.
 */
class ExpireCreditsCommand extends Command
{
    protected $signature   = 'credits:expire-due';
    protected $description = 'Zero out expired photographer credit bundles and log EXPIRE ledger entries.';

    public function handle(CreditService $credits): int
    {
        $result = $credits->expireExpired();

        $this->info(sprintf(
            'Credits expired: %d bundles, %d credits voided.',
            $result['bundles'] ?? 0,
            $result['credits'] ?? 0,
        ));

        return self::SUCCESS;
    }
}

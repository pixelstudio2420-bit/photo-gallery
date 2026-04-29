<?php

namespace App\Jobs;

use App\Models\AppSetting;
use App\Models\PhotographerDisbursement;
use App\Services\Payout\PayoutEngine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Scheduled "which photographers should be paid RIGHT NOW" job.
 *
 * Entry point for the payout engine. Runs on the Laravel scheduler (once
 * per hour is enough — the engine itself enforces schedule/threshold
 * semantics, this job just asks "is it time yet?").
 *
 * Flow:
 *   1. Bail if payouts are globally disabled in settings (admin panic button).
 *   2. Ask PayoutEngine to evaluate every photographer and create
 *      PhotographerDisbursement rows for anyone who qualifies.
 *   3. Dispatch one ProcessPayoutJob per new disbursement so the actual
 *      provider calls happen on the worker (not inside the scheduler tick).
 *
 * Idempotency: the engine's unique idempotency_key means if two schedulers
 * race (dev + prod, double-deployed cron, etc.) only one set of
 * disbursements gets created.
 */
class CheckPayoutTriggersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** The scheduler should never queue another copy on top of a still-running one. */
    public $uniqueFor = 600;

    public function uniqueId(): string
    {
        return 'payout-check-singleton';
    }

    public function handle(PayoutEngine $engine): void
    {
        $enabled = (bool) AppSetting::get('payout_enabled', false);
        if (!$enabled) {
            // Silent no-op — the admin toggle being off is normal during
            // early setup; we don't want the log noisy.
            return;
        }

        try {
            $created = $engine->runCycle(PhotographerDisbursement::TRIGGER_SCHEDULE);
        } catch (\Throwable $e) {
            Log::error('CheckPayoutTriggersJob failed', ['error' => $e->getMessage()]);
            throw $e;
        }

        if (empty($created)) {
            Log::debug('CheckPayoutTriggersJob: no disbursements to create this cycle');
            return;
        }

        foreach ($created as $disbursement) {
            ProcessPayoutJob::dispatch($disbursement->id)
                ->onQueue('payouts'); // Dedicated queue so ops can isolate payout workers.
        }

        Log::info('CheckPayoutTriggersJob dispatched disbursements', [
            'count' => count($created),
            'total_thb' => array_sum(array_map(fn($d) => (float) $d->amount_thb, $created)),
        ]);
    }
}

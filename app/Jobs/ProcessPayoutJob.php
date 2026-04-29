<?php

namespace App\Jobs;

use App\Models\PhotographerDisbursement;
use App\Services\Payout\PayoutProviderFactory;
use App\Services\Payout\RetryablePayoutException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Execute a single PhotographerDisbursement against the configured provider.
 *
 * Lifecycle:
 *   1. Load the disbursement row. Bail if it's already terminal (idempotent —
 *      a retry after a successful run should no-op).
 *   2. Flip to 'processing' to stop a parallel worker racing us.
 *   3. Call provider.transfer() with the row's idempotency_key.
 *   4. On success: flip disbursement + all its payouts to paid, stamp
 *      provider_txn_id + settled_at.
 *   5. On permanent failure: mark disbursement 'failed', RELEASE the
 *      attached payouts back to 'pending' with disbursement_id=null so a
 *      later cycle can retry with a fresh batch.
 *   6. On retryable failure: throw — Laravel queue retries with backoff.
 *
 * Retry policy: 3 attempts, exponential backoff via the queue driver.
 * Network blips shouldn't fail a payout, but we don't want infinite retries
 * hammering the provider either.
 */
class ProcessPayoutJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60; // seconds — exponential with jitter would be nicer; keep simple for now.

    public function __construct(public int $disbursementId) {}

    public function handle(PayoutProviderFactory $factory): void
    {
        $disbursement = PhotographerDisbursement::find($this->disbursementId);
        if (!$disbursement) {
            Log::warning('ProcessPayoutJob: disbursement not found', ['id' => $this->disbursementId]);
            return;
        }

        // Idempotency guard: if a previous attempt already settled this
        // disbursement, don't fire a second provider call.
        if ($disbursement->isTerminal()) {
            Log::debug('ProcessPayoutJob: already terminal', ['id' => $disbursement->id, 'status' => $disbursement->status]);
            return;
        }

        $profile = $disbursement->photographerProfile;
        if (!$profile) {
            $disbursement->markFailed('missing_profile', 'Photographer profile no longer exists');
            return;
        }

        // Mark processing + bump attempt counter before the provider call —
        // if the server dies mid-call we want to see that state in the DB
        // rather than have a "stuck pending" row no one notices.
        $disbursement->update([
            'status'       => PhotographerDisbursement::STATUS_PROCESSING,
            'attempted_at' => now(),
            'attempts'     => $disbursement->attempts + 1,
        ]);

        $provider = $factory->make($disbursement->provider);

        try {
            $result = $provider->transfer(
                recipient: $profile,
                amount: (float) $disbursement->amount_thb,
                idempotencyKey: $disbursement->idempotency_key,
                meta: [
                    'disbursement_id' => $disbursement->id,
                    'payout_count'    => $disbursement->payout_count,
                ],
            );
        } catch (RetryablePayoutException $e) {
            // Leave the row in 'processing' — the queue will retry. On final
            // attempt, Laravel calls failed() which we use to land the row
            // in a terminal 'failed' state.
            throw $e;
        }

        if ($result->ok && $result->status === 'succeeded') {
            $disbursement->markSucceeded($result->providerTxnId, $result->raw);
            return;
        }

        if ($result->ok && $result->status === 'pending') {
            // Accepted by the provider but not yet settled. Keep it in
            // 'processing' and let the webhook (or a poller job) flip it
            // to succeeded when the provider settles.
            $disbursement->update([
                'provider_txn_id' => $result->providerTxnId,
                'raw_response'    => $result->raw,
            ]);
            Log::info('ProcessPayoutJob: provider returned pending', [
                'id' => $disbursement->id, 'provider_txn_id' => $result->providerTxnId,
            ]);
            return;
        }

        // Permanent failure (ok=false). Release the payouts so a later run
        // can retry with a fresh batch under (possibly) a different provider.
        $disbursement->markFailed(
            $result->errorCode ?? 'unknown',
            $result->errorMessage ?? 'Transfer failed',
            $result->raw,
        );
    }

    /**
     * Called by the framework after the final retry fails. Landing the row
     * in a terminal 'failed' state means the admin sees it in the payout
     * dashboard + the attached payouts are released.
     */
    public function failed(\Throwable $e): void
    {
        $disbursement = PhotographerDisbursement::find($this->disbursementId);
        if (!$disbursement || $disbursement->isTerminal()) return;

        $disbursement->markFailed(
            statusReason: 'retries_exhausted',
            errorMessage: 'Giving up after ' . $this->tries . ' attempts: ' . $e->getMessage(),
        );
    }
}

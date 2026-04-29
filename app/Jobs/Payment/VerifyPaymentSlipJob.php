<?php

namespace App\Jobs\Payment;

use App\Models\AppSetting;
use App\Models\PaymentSlip;
use App\Services\Payment\SlipOKService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Async re-verification of a payment slip via the SlipOK API.
 *
 * Why async?
 * ──────────
 * The slip-upload controller already runs SlipVerifier inline so the user
 * gets an immediate answer. But the SlipOK external API can be slow,
 * rate-limited, or temporarily down — when that happens, the inline call
 * times out and the slip lands in `pending` state without the strongest
 * signal (SlipOK transRef). This job retries the SlipOK call out-of-band
 * with exponential backoff so transient failures self-heal.
 *
 * Idempotency
 * ───────────
 *   - `uniqueFor` keyed by slip_id + the current verify_status snapshot
 *     prevents two workers re-running the same job concurrently.
 *   - The job exits early if the slip is no longer in `pending` state
 *     (admin already decided / another retry already won the race).
 *   - At most one attempt mutates verify_status, gated by a row-level
 *     `WHERE verify_status = 'pending'` clause on the UPDATE.
 *
 * Retry policy
 * ────────────
 *   tries=3, backoff = [60, 300, 900] seconds (1min, 5min, 15min).
 *   On final failure the slip stays `pending` and the admin queue picks
 *   it up — we never auto-reject just because SlipOK was unreachable.
 */
class VerifyPaymentSlipJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Total attempts before the job is moved to failed_jobs. */
    public int $tries = 3;

    /** Per-attempt timeout — the SlipOK HTTP call has its own 15s. */
    public int $timeout = 60;

    /**
     * Per-attempt backoff (seconds). Exponential to give SlipOK time to
     * recover between attempts when the entire service is down.
     *
     * @return array<int>
     */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    /** uniqueFor lock duration (seconds). */
    public int $uniqueFor = 1200;

    public function __construct(
        public readonly int $slipId,
    ) {}

    /**
     * Lock key — only one re-verify per slip can be queued at a time.
     * Combined with the early-exit on terminal status, this prevents
     * thundering-herd on a slip the admin manually decided.
     */
    public function uniqueId(): string
    {
        return "verify-payment-slip-{$this->slipId}";
    }

    public function handle(SlipOKService $slipok): void
    {
        // Bail fast if SlipOK isn't enabled — without it this job has
        // nothing useful to do (it's the SlipOK signal we're after).
        if (!$slipok->isEnabled() || !$slipok->isConfigured()) {
            Log::info("VerifyPaymentSlipJob: SlipOK disabled, skipping slip #{$this->slipId}");
            return;
        }

        $slip = PaymentSlip::find($this->slipId);
        if (!$slip) {
            Log::info("VerifyPaymentSlipJob: slip #{$this->slipId} not found, skipping");
            return;
        }

        // State guard: don't retry once the slip has been decided.
        if ($slip->verify_status !== 'pending') {
            Log::info("VerifyPaymentSlipJob: slip #{$this->slipId} no longer pending ({$slip->verify_status}), skipping");
            return;
        }

        // If we already have a transRef from the inline verifier, the SlipOK
        // call did succeed once and we don't need to retry.
        if (!empty($slip->slipok_trans_ref)) {
            Log::info("VerifyPaymentSlipJob: slip #{$this->slipId} already has transRef, skipping");
            return;
        }

        // Resolve a usable local file path. Slips live on the configured
        // disk (R2 in prod, local in tests). We download to a temp file so
        // SlipOK gets a real local path for the multipart upload.
        $tempPath = $this->materialiseSlipFile($slip->slip_path);
        if (!$tempPath) {
            Log::warning("VerifyPaymentSlipJob: could not access slip file for #{$this->slipId}", [
                'path' => $slip->slip_path,
            ]);
            // Throw so the job retries on the configured backoff.
            throw new \RuntimeException("slip file unavailable: {$slip->slip_path}");
        }

        try {
            $result = $slipok->verify($tempPath);
        } finally {
            // Always clean up the temp file.
            if (is_file($tempPath)) {
                @unlink($tempPath);
            }
        }

        if (!$result['success']) {
            // Soft failure (rate-limited, network glitch). Throw to retry.
            $code = $result['error_code'] ?? 'UNKNOWN';
            throw new \RuntimeException("SlipOK verify failed: {$code}");
        }

        $normalised = $slipok->normaliseResult($result['data']);
        $transRef   = $normalised['trans_ref'] ?? null;

        // Recompute approval gates (these are cheap; no need to re-run the
        // full SlipVerifier since the inline pass already wrote a score).
        $duplicateRef    = $slipok->isDuplicateTransRef($transRef, $slip->id);
        $matchesReceiver = $slipok->matchesOurBankAccount($normalised);

        $autoMode  = AppSetting::get('slip_verify_mode', 'manual') === 'auto';
        $threshold = (int) AppSetting::get('slip_auto_approve_threshold', 80);
        $strictRecv = AppSetting::get('slip_require_receiver_match', '0') === '1';
        $score      = (int) ($slip->verify_score ?? 0);

        $shouldApprove = $autoMode
            && !$duplicateRef
            && $score >= $threshold
            && (!$strictRecv || $matchesReceiver);

        // Atomic update with state guard — only flips pending → terminal.
        $rowsUpdated = DB::table('payment_slips')
            ->where('id', $slip->id)
            ->where('verify_status', 'pending')   // ← race guard
            ->update([
                'slipok_trans_ref' => $transRef,
                'receiver_account' => $normalised['receiver_account'] ?? $slip->receiver_account,
                'receiver_name'    => $normalised['receiver_name']    ?? $slip->receiver_name,
                'sender_name'      => $normalised['sender_name']      ?? $slip->sender_name,
                // Only auto-flip on success — if SlipOK detected a duplicate
                // transRef or bad receiver, leave for admin review (we just
                // tagged the slip with the data so admin sees the evidence).
                'verify_status'    => $shouldApprove ? 'approved' : 'pending',
                'verified_at'      => $shouldApprove ? now() : null,
                'verified_by'      => $shouldApprove ? 'slipok_async' : null,
                'updated_at'       => now(),
            ]);

        if ($rowsUpdated === 0) {
            Log::info("VerifyPaymentSlipJob: slip #{$slip->id} state changed during retry, skipping");
            return;
        }

        // Audit log — async approvals matter.
        try {
            DB::table('payment_audit_log')->insert([
                'transaction_id' => null,
                'order_id'       => $slip->order_id,
                'action'         => 'slip_async_reverified',
                'actor_type'     => 'job',
                'actor_id'       => null,
                'ip_address'     => null,
                'old_values'     => json_encode(['verify_status' => 'pending'], JSON_UNESCAPED_UNICODE),
                'new_values'     => json_encode([
                    'verify_status' => $shouldApprove ? 'approved' : 'pending',
                    'trans_ref'     => $transRef,
                    'duplicate_ref' => $duplicateRef,
                    'receiver_match'=> $matchesReceiver,
                    'attempt'       => $this->attempts(),
                ], JSON_UNESCAPED_UNICODE),
                'signature'      => null,
                'created_at'     => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('VerifyPaymentSlipJob: audit insert failed', ['error' => $e->getMessage()]);
        }

        if ($shouldApprove && $slip->order_id) {
            // Reuse the existing order-status flip used by the webhook path.
            $controller = new \App\Http\Controllers\Api\PaymentWebhookController();
            $reflection = new \ReflectionMethod($controller, 'updateOrderStatus');
            $reflection->setAccessible(true);
            $reflection->invoke($controller, (int) $slip->order_id, 'paid', "SlipOK async verified: ref {$transRef}");
        }

        Log::info("VerifyPaymentSlipJob: slip #{$slip->id} async-verified, status={$shouldApprove}");
    }

    /**
     * Pull the slip image from whatever disk it lives on (R2 in prod, local
     * in tests) into a temp file so the SlipOK SDK can read it.
     */
    private function materialiseSlipFile(?string $slipPath): ?string
    {
        if (empty($slipPath)) return null;

        // Already a local absolute path?
        if (is_file($slipPath)) return $slipPath;

        // Try the configured payment-slips disk first, then a couple of
        // common fallbacks. We don't crash on a missing disk — just return
        // null so the caller throws a retryable exception.
        $disks = ['payment-slips', 'r2', 'local', 'public'];
        foreach ($disks as $disk) {
            try {
                if (Storage::disk($disk)->exists($slipPath)) {
                    $stream = Storage::disk($disk)->readStream($slipPath);
                    if (!$stream) continue;
                    $temp = tempnam(sys_get_temp_dir(), 'slipok_');
                    $out  = fopen($temp, 'w');
                    stream_copy_to_stream($stream, $out);
                    fclose($out);
                    if (is_resource($stream)) fclose($stream);
                    return $temp;
                }
            } catch (\Throwable) {
                continue;
            }
        }
        return null;
    }

    /**
     * Final-failure hook — surfaces in failed_jobs if all retries fail.
     * We don't auto-reject the slip; admin queue handles it.
     */
    public function failed(\Throwable $e): void
    {
        Log::error("VerifyPaymentSlipJob: final failure for slip #{$this->slipId}", [
            'error' => $e->getMessage(),
        ]);
    }
}

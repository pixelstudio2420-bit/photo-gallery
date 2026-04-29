<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Log;
// UserNotification + AdminNotification are same-namespace so no use-statements
// needed. Keeping the FQN-less references below for readability.

/**
 * Per-provider-transfer ledger — one row per PromptPay call we fire.
 *
 * See the migration header for the full why-not-roll-into-payouts rationale.
 * TL;DR: photographer_payouts is per-order (earnings), this table is
 * per-transfer (disbursement).
 */
class PhotographerDisbursement extends Model
{
    protected $table = 'photographer_disbursements';

    protected $fillable = [
        'photographer_id', 'amount_thb', 'payout_count',
        'provider', 'idempotency_key', 'provider_txn_id',
        'status', 'status_reason', 'error_message', 'raw_response',
        'trigger_type', 'window_start_at', 'window_end_at',
        'attempted_at', 'settled_at', 'attempts',
    ];

    protected $casts = [
        'amount_thb'      => 'decimal:2',
        'payout_count'    => 'integer',
        'attempts'        => 'integer',
        'raw_response'    => 'array',
        'window_start_at' => 'datetime',
        'window_end_at'   => 'datetime',
        'attempted_at'    => 'datetime',
        'settled_at'      => 'datetime',
    ];

    public const STATUS_PENDING    = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_SUCCEEDED  = 'succeeded';
    public const STATUS_FAILED     = 'failed';

    public const TRIGGER_SCHEDULE  = 'schedule';
    public const TRIGGER_THRESHOLD = 'threshold';
    public const TRIGGER_MANUAL    = 'manual';

    public function photographer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'photographer_id');
    }

    public function photographerProfile(): BelongsTo
    {
        return $this->belongsTo(PhotographerProfile::class, 'photographer_id', 'user_id');
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(PhotographerPayout::class, 'disbursement_id');
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, [self::STATUS_SUCCEEDED, self::STATUS_FAILED], true);
    }

    /**
     * Settle this disbursement successfully — flip all attached payouts to
     * paid and stamp the provider transaction ID. Idempotent: calling it on
     * an already-succeeded row is a no-op.
     *
     * Also captures the ITMX-verified bank-account name from the provider
     * response (for PromptPay transfers via Omise) and backfills it onto
     * the photographer's profile. This is THE moment the system learns the
     * authoritative name — we never ask ITMX otherwise, since that requires
     * a real transfer. See PromptPayService header for the full rationale.
     *
     * Shared by ProcessPayoutJob (synchronous provider response) and the
     * Omise transfer webhook (async settlement). Returns true when state
     * actually changed so callers can skip side effects on a no-op.
     */
    public function markSucceeded(?string $txnId = null, array $raw = []): bool
    {
        if ($this->status === self::STATUS_SUCCEEDED) {
            return false;
        }

        $this->update([
            'status'          => self::STATUS_SUCCEEDED,
            'provider_txn_id' => $txnId ?? $this->provider_txn_id,
            'settled_at'      => now(),
            'raw_response'    => $raw ?: $this->raw_response,
        ]);

        PhotographerPayout::where('disbursement_id', $this->id)
            ->update([
                'status'  => 'paid',
                'paid_at' => now(),
            ]);

        // Tell admins that a payout went through. Failed disbursements
        // already notify (see handle()); successes did not until now.
        // Best-effort — never breaks the settlement.
        try {
            \App\Models\AdminNotification::disbursementSuccess($this);
        } catch (\Throwable $e) {
            \Log::warning('disbursement.success_notify_failed', [
                'id' => $this->id, 'error' => $e->getMessage(),
            ]);
        }

        // Backfill the ITMX-verified bank account name onto the profile —
        // this is how the "ยืนยันกับธนาคารแล้ว" badge in the UI becomes
        // true. Best-effort; failure here never reverts the settlement.
        try {
            $this->captureVerifiedNameFromResponse($raw ?: (array) $this->raw_response);
        } catch (\Throwable $e) {
            Log::warning('Verified-name capture failed (non-fatal)', [
                'disbursement_id' => $this->id,
                'error'           => $e->getMessage(),
            ]);
        }

        Log::info('Disbursement settled succeeded', [
            'disbursement_id' => $this->id,
            'photographer_id' => $this->photographer_id,
            'amount'          => (float) $this->amount_thb,
            'provider'        => $this->provider,
            'txn_id'          => $txnId,
        ]);

        // Notify the photographer — in-app + LINE push. Wrapped so a
        // notification failure (e.g. LINE outage) can never reverse the
        // successful settlement above. Users get the money either way;
        // they just might not get the push.
        try {
            $this->notifyPhotographer(success: true);
        } catch (\Throwable $e) {
            Log::warning('Disbursement success notify failed (non-fatal)', [
                'disbursement_id' => $this->id,
                'error'           => $e->getMessage(),
            ]);
        }

        return true;
    }

    /**
     * Extract the account holder's name from a successful provider response
     * and cache it on the photographer's profile as the authoritative,
     * ITMX-verified name.
     *
     * Omise transfer responses include a `bank_account` object with the
     * name ITMX returned. For PromptPay, this is the real registered
     * account holder — the one source of truth we get without having an
     * ITMX member license ourselves.
     *
     * Precedence:
     *   1. raw.bank_account.name           ← top-level on Omise transfer
     *   2. raw.recipient.bank_account.name ← when recipient is expanded
     *
     * Skips the backfill if the profile already has a verified name AND
     * it matches the response (nothing to do), or if the raw response
     * doesn't carry a name at all (the provider didn't include it, e.g.
     * mock provider in tests).
     */
    private function captureVerifiedNameFromResponse(array $raw): void
    {
        $name = $raw['bank_account']['name']
             ?? $raw['recipient']['bank_account']['name']
             ?? null;

        $name = is_string($name) ? trim($name) : '';
        if ($name === '') {
            return; // provider didn't share it — nothing to cache
        }

        $profile = PhotographerProfile::where('user_id', $this->photographer_id)->first();
        if (!$profile) return;

        // Already cached the same name? No-op.
        if ($profile->promptpay_verified_name === $name && !empty($profile->promptpay_verified_at)) {
            return;
        }

        $profile->update([
            'promptpay_verified_name' => $name,
            'promptpay_verified_at'   => now(),
        ]);

        Log::info('PromptPay name verified via provider response', [
            'photographer_id' => $this->photographer_id,
            'verified_name'   => $name,
            'source'          => $this->provider,
            'disbursement_id' => $this->id,
        ]);
    }

    /**
     * Settle this disbursement as failed — mark terminal and RELEASE the
     * attached payouts back to the pool so a later cycle can retry them.
     * The earnings themselves aren't forfeited; only this particular attempt
     * is.
     *
     * When the failure is because ITMX rejected the account name (the typed
     * `bank_account_name` doesn't match the PromptPay registration), we
     * additionally clear the photographer's verification flags and surface
     * an actionable message — auto-retrying the same bad name wastes API
     * credits AND burns Omise idempotency keys without making progress.
     */
    public function markFailed(string $statusReason, string $errorMessage, array $raw = []): bool
    {
        if ($this->status === self::STATUS_FAILED) {
            return false;
        }

        $this->update([
            'status'        => self::STATUS_FAILED,
            'status_reason' => $statusReason,
            'error_message' => $errorMessage,
            'raw_response'  => $raw ?: $this->raw_response,
            'settled_at'    => now(),
        ]);

        PhotographerPayout::where('disbursement_id', $this->id)
            ->where('status', 'pending')
            ->update(['disbursement_id' => null]);

        // Name-mismatch or recipient-invalid failures are ACTIONABLE by the
        // photographer — they need to fix the bank-account name on their
        // setup-bank page. Clear the verification flags so the UI shows
        // "กรุณาตรวจสอบชื่อบัญชี" instead of pretending everything is fine,
        // and null out the stale Omise recipient so the next save rebuilds
        // the recipient with whatever the user corrects to.
        $nameFailure = $this->looksLikeNameFailure($statusReason);
        if ($nameFailure) {
            try {
                PhotographerProfile::where('user_id', $this->photographer_id)
                    ->update([
                        'promptpay_verified_name' => null,
                        'promptpay_verified_at'   => null,
                        'omise_recipient_id'      => null,
                    ]);
            } catch (\Throwable $e) {
                Log::warning('Verification clear on name-failure failed (non-fatal)', [
                    'disbursement_id' => $this->id,
                    'error'           => $e->getMessage(),
                ]);
            }
        }

        Log::warning('Disbursement settled failed', [
            'disbursement_id' => $this->id,
            'photographer_id' => $this->photographer_id,
            'amount'          => (float) $this->amount_thb,
            'provider'        => $this->provider,
            'reason'          => $statusReason,
            'error'           => $errorMessage,
            'name_failure'    => $nameFailure,
        ]);

        // Best-effort notify. A failed transfer is ops-visible via admin
        // dashboard regardless; this just tips off the photographer that
        // the release was undone so they know to expect another attempt.
        try {
            $this->notifyPhotographer(
                success: false,
                errorMessage: $errorMessage,
                nameFailure: $nameFailure,
            );
        } catch (\Throwable $e) {
            Log::warning('Disbursement failure notify failed (non-fatal)', [
                'disbursement_id' => $this->id,
                'error'           => $e->getMessage(),
            ]);
        }

        // Also ping admins — a failed auto-payout is the kind of thing that
        // should show up in the admin notification bell without digging.
        try {
            AdminNotification::create([
                'type'    => 'payout.failed',
                'title'   => 'การจ่ายเงินอัตโนมัติล้มเหลว',
                'message' => 'ช่างภาพ #' . $this->photographer_id . ' จำนวน ฿' . number_format((float) $this->amount_thb, 2)
                             . ' — เหตุผล: ' . $errorMessage,
                'link'    => 'admin/payments/payouts/automation',
                'ref_id'  => (string) $this->id,
                'is_read' => false,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Admin payout-failure notify failed (non-fatal)', ['error' => $e->getMessage()]);
        }

        return true;
    }

    /**
     * Fire user-facing notifications (in-app + LINE push) for a settled
     * disbursement. Centralised here so both success and failure paths emit
     * consistent messaging and respect the user's notification preferences.
     *
     * Kept private so the state-transition methods above are the only
     * entry point — you can't "notify" without also landing the row in a
     * terminal state, which keeps the audit trail honest.
     *
     * The `nameFailure` flag routes failures to a different message
     * template: instead of "we'll retry automatically" we tell the
     * photographer they need to fix their bank-account name manually,
     * and deep-link them into the setup-bank page.
     */
    private function notifyPhotographer(
        bool $success,
        string $errorMessage = '',
        bool $nameFailure = false,
    ): void {
        $amount     = (float) $this->amount_thb;
        $amountStr  = number_format($amount, 2);
        $payoutCnt  = (int) $this->payout_count;

        // Build the canonical message ONCE so every channel says the
        // same thing. Without this, in-app, LINE, and email each used to
        // render slightly different phrasing/numbers — confusing for
        // photographers comparing across channels. See the formatter
        // class header for the full rationale.
        $formatter = app(\App\Services\Notifications\PayoutMessageFormatter::class);
        $message   = $success
            ? $formatter->success($this)
            : $formatter->failure($this, $errorMessage, $nameFailure);

        if ($success) {
            // In-app notification — reuse the existing payoutProcessed
            // helper so the type string matches what the preference center
            // already knows about. The amount is the same number the
            // formatter rendered (defensive: the helper formats too, but
            // both go through number_format(amount, 2) so they agree).
            UserNotification::payoutProcessed($this->photographer_id, $amount);

            // LINE push — flex bubble first (rich UI), text fallback.
            // Both content paths come from the same PayoutMessage so a
            // photographer who sees the text version (older LINE clients
            // / quick reply preview) gets the same numbers and same CTA.
            try {
                $line = app(\App\Services\LineNotifyService::class);
                $line->pushPayoutMessage($this->photographer_id, $message);
            } catch (\Throwable $e) {
                Log::debug('LINE payout push skipped: ' . $e->getMessage());
            }

            // Email notification — feeds the formatted PayoutMessage to
            // MailService so the email subject/body matches what LINE
            // and the in-app notification said. The existing
            // payout-notification.blade.php template still receives its
            // legacy data shape (amount/order_count/etc.) for compatibility,
            // but the SUBJECT now comes verbatim from PayoutMessage so
            // photographers see the same headline in their inbox preview
            // as in their LINE notification.
            try {
                $profile = PhotographerProfile::where('user_id', $this->photographer_id)->first();
                $user    = User::find($this->photographer_id);
                if ($user && $user->email && $profile) {
                    $accountLast4 = $profile->bank_account_number
                        ? substr(preg_replace('/\D/', '', (string) $profile->bank_account_number), -4)
                        : null;
                    app(\App\Services\MailService::class)->sendTemplate(
                        $user->email,
                        $message->subject,   // ← unified subject
                        'emails.photographer.payout-notification',
                        [
                            'name'            => $profile->display_name ?? trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')),
                            'amount'          => $amount,
                            'orderCount'      => $payoutCnt,
                            'transferDate'    => optional($this->settled_at ?? now())->format('d/m/Y H:i'),
                            'referenceNumber' => $this->provider_txn_id,
                            'bankName'        => $profile->bank_name,
                            'accountLast4'    => $accountLast4,
                            'statementUrl'    => url('/photographer/earnings'),
                            // PayoutMessage-derived blocks — the template
                            // can render these alongside the legacy fields,
                            // or we use them for consistency on the
                            // headline-area copy.
                            'unifiedHeadline' => $message->headline,
                            'unifiedBody'     => $message->body,
                            'unifiedBullets'  => $message->bullets,
                            'unifiedCta'      => $message->cta,
                        ],
                        'photographer_payout',
                    );
                }
            } catch (\Throwable $e) {
                Log::debug('Payout success mail skipped (non-fatal)', [
                    'disbursement_id' => $this->id,
                    'error'           => $e->getMessage(),
                ]);
            }
            return;
        }

        // Failure branch. Split by whether the photographer can act on it.
        if ($nameFailure) {
            // ITMX rejected the account name → the photographer MUST fix
            // their bank_account_name before any retry can succeed. Deep
            // link to the setup page and use a distinct emoji so the
            // message stands out from generic payout retries.
            $message = "ยอด ฿{$amountStr} ยังไม่ได้โอน — ชื่อบัญชีไม่ตรงกับทะเบียน PromptPay ที่ธนาคาร "
                     . "กรุณาแก้ไขชื่อบัญชีในหน้าตั้งค่าการรับเงินแล้วระบบจะลองโอนใหม่อัตโนมัติ";

            UserNotification::notify(
                userId:    $this->photographer_id,
                type:      'payout_failed',
                title:     '❗ กรุณาแก้ไขชื่อบัญชี',
                message:   $message,
                actionUrl: 'photographer/profile/setup-bank',
            );

            try {
                $line = app(\App\Services\LineNotifyService::class);
                $line->pushText(
                    $this->photographer_id,
                    "❗ โอนเงิน ฿{$amountStr} ไม่สำเร็จ — ชื่อบัญชีไม่ตรง กรุณาเข้าหน้าตั้งค่าการรับเงินเพื่อแก้ไข"
                );
            } catch (\Throwable $e) {
                Log::debug('LINE name-fail push skipped: ' . $e->getMessage());
            }
            return;
        }

        // Generic failure — plain-text notification. Kept short because
        // the photographer can't act on most failure codes directly; the
        // admin dashboard has the full detail.
        UserNotification::notify(
            userId:    $this->photographer_id,
            type:      'payout_failed',
            title:     '⚠️ การจ่ายเงินอัตโนมัติไม่สำเร็จ',
            message:   "ยอด ฿{$amountStr} ยังไม่ได้โอน — ระบบจะลองใหม่ในรอบถัดไป",
            actionUrl: 'photographer/earnings',
        );

        try {
            $line = app(\App\Services\LineNotifyService::class);
            $line->pushText(
                $this->photographer_id,
                "⚠️ การโอนเงิน ฿{$amountStr} ไม่สำเร็จ — ระบบจะลองใหม่อัตโนมัติในรอบถัดไป"
            );
        } catch (\Throwable $e) {
            Log::debug('LINE payout-fail push skipped: ' . $e->getMessage());
        }

        // LINE flex+text via formatter — same content as the in-app and
        // email branches, just rendered for the LINE channel.
        try {
            $line = app(\App\Services\LineNotifyService::class);
            $line->pushPayoutMessage($this->photographer_id, $message);
        } catch (\Throwable $e) {
            Log::debug('LINE payout-fail flex skipped: ' . $e->getMessage());
        }

        // Email notification — `emails.photographer.payout-failed` template.
        // Pass the unified headline/body/bullets so the email matches
        // exactly what LINE said. Same best-effort discipline as the
        // success branch.
        try {
            $profile = PhotographerProfile::where('user_id', $this->photographer_id)->first();
            $user    = User::find($this->photographer_id);
            if ($user && $user->email && $profile) {
                $reason = $errorMessage !== ''
                    ? $errorMessage
                    : ($nameFailure
                        ? 'ชื่อบัญชีไม่ตรงกับทะเบียน PromptPay'
                        : 'การโอนเงินถูก provider ปฏิเสธ ระบบจะลองใหม่ในรอบถัดไป');
                app(\App\Services\MailService::class)->sendTemplate(
                    $user->email,
                    $message->subject,
                    'emails.photographer.payout-failed',
                    [
                        'name'            => $profile->display_name ?? trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')),
                        'amount'          => $amount,
                        'reason'          => $reason,
                        'updateUrl'       => url('/photographer/profile/setup-bank'),
                        'unifiedHeadline' => $message->headline,
                        'unifiedBody'     => $message->body,
                        'unifiedBullets'  => $message->bullets,
                        'unifiedCta'      => $message->cta,
                    ],
                    'photographer_payout_failed',
                );
            }
        } catch (\Throwable $e) {
            Log::debug('Payout failure mail skipped (non-fatal)', [
                'disbursement_id' => $this->id,
                'error'           => $e->getMessage(),
            ]);
        }
    }

    /**
     * Does this failure status/reason indicate a name-mismatch with ITMX?
     *
     * We check against the union of Omise's documented codes and the
     * substring heuristics that cover the free-form `failure_message`
     * cases. Kept liberal on purpose — treating a non-name failure as a
     * name failure only costs a one-time "please check your bank name"
     * nudge; the opposite (missing a real name failure) would have the
     * engine retrying forever against a number that will never work.
     */
    private function looksLikeNameFailure(?string $statusReason): bool
    {
        $needle = strtolower((string) $statusReason);
        if ($needle === '') return false;

        // Known Omise/ITMX codes
        $knownCodes = [
            'recipient_name_invalid',
            'invalid_recipient_name',
            'recipient_not_found',
            'promptpay_not_found',
            'bank_account_not_found',
            'name_mismatch',
        ];
        foreach ($knownCodes as $code) {
            if ($needle === $code) return true;
        }

        // Substring heuristics for free-form messages
        $substrings = ['name_mismatch', 'name does not match', 'account not found', 'ไม่ตรง'];
        foreach ($substrings as $s) {
            if (str_contains($needle, strtolower($s))) return true;
        }

        return false;
    }
}

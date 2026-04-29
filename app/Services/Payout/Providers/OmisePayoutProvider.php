<?php

namespace App\Services\Payout\Providers;

use App\Models\AppSetting;
use App\Models\PhotographerProfile;
use App\Services\Payout\PayoutProviderInterface;
use App\Services\Payout\PayoutResult;
use App\Services\Payout\RetryablePayoutException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Omise Transfers API integration — Thailand's most battle-tested real-time
 * PromptPay payout provider.
 *
 * Flow:
 *   1. First payout for a photographer: lazily create an Omise recipient
 *      via `POST /recipients` and cache the id on photographer_profiles.
 *   2. Every payout: `POST /transfers` with the cached recipient and an
 *      `Omise-Idempotency-Key` header so retries are money-safe.
 *   3. Response status → PayoutResult mapping:
 *        paid=true         → succeeded (money landed synchronously)
 *        sent=true/pending → pending (async; wait for transfer.* webhook)
 *        any other state   → failed (permanent; release the payouts)
 *
 * Out-of-band settlement is handled by PaymentWebhookController::omiseTransfer,
 * which matches on `provider_txn_id` and calls `markSucceeded()`/`markFailed()`
 * on the disbursement.
 *
 * Security:
 *   • Secret key prefers env (`OMISE_SECRET_KEY`), falls back to AppSetting
 *     (`omise_secret_key`) for staging/dev convenience.
 *   • Auth via HTTP Basic (secret as username, blank password).
 *   • 5xx/429 = retryable; 4xx = permanent failure with provider's code.
 */
class OmisePayoutProvider implements PayoutProviderInterface
{
    private const API_BASE = 'https://api.omise.co';
    private const TIMEOUT  = 15; // seconds — Omise targets < 3s p99, 15s is a safe ceiling.

    private ?string $secretKey;

    public function __construct()
    {
        // Secret key: prefer env (proper deployment), fall back to AppSetting
        // for dev/staging convenience so admins can swap keys without a
        // deploy. Null means "provider not configured" → healthCheck() returns
        // false and the engine will refuse to route to this provider.
        $fromEnv     = (string) config('services.omise.secret_key', env('OMISE_SECRET_KEY'));
        $fromSetting = $fromEnv === '' ? (string) AppSetting::get('omise_secret_key', '') : '';
        $key         = $fromEnv !== '' ? $fromEnv : $fromSetting;
        $this->secretKey = $key !== '' ? $key : null;
    }

    public function transfer(
        PhotographerProfile $recipient,
        float $amount,
        string $idempotencyKey,
        array $meta = []
    ): PayoutResult {
        if (!$this->secretKey) {
            return PayoutResult::failed(
                errorCode: 'provider_not_configured',
                message: 'OMISE_SECRET_KEY is not set — set it in .env to enable Omise payouts.'
            );
        }
        if (empty($recipient->promptpay_number)) {
            return PayoutResult::failed(
                errorCode: 'missing_promptpay',
                message: 'Recipient has no PromptPay number on file.'
            );
        }

        // Omise expects amounts in satang (฿1 = 100 satang). Guard against
        // floating-point drift by rounding half-up before casting.
        $amountSatang = (int) round($amount * 100);
        if ($amountSatang < 2000) {
            // Omise's minimum transfer is ฿20. If our engine ever accepts a
            // smaller threshold this would surface as a loud permanent error
            // rather than a silent drop.
            return PayoutResult::failed(
                errorCode: 'amount_below_minimum',
                message: 'Omise requires transfers of at least ฿20.00.'
            );
        }

        // 1. Ensure we have a cached Omise recipient_id. Omise recipients
        //    cannot be idempotently created, so we lazily materialise one
        //    per photographer and cache the id on the profile row.
        $recipientId = $recipient->omise_recipient_id;
        if (empty($recipientId)) {
            $recipientId = $this->ensureRecipient($recipient);
            if ($recipientId instanceof PayoutResult) {
                // ensureRecipient returns the failure early-return.
                return $recipientId;
            }
            $recipient->omise_recipient_id = $recipientId;
            $recipient->save();
        }

        // 2. POST /transfers — the actual payout request. Omise honours an
        //    `Omise-Idempotency-Key` header, so a retry of the same logical
        //    transfer never double-sends money.
        try {
            $response = Http::withBasicAuth($this->secretKey, '')
                ->withHeaders([
                    'Omise-Idempotency-Key' => $idempotencyKey,
                ])
                ->timeout(self::TIMEOUT)
                ->acceptJson()
                ->asForm()
                ->post(self::API_BASE . '/transfers', [
                    'recipient' => $recipientId,
                    'amount'    => $amountSatang,
                    // Metadata is serialized as `metadata[key]=value` pairs.
                    // Omise dashboard displays these verbatim so ops can trace
                    // a transfer back to our disbursement id in one click.
                    // Caller-provided meta flows through FIRST so our canonical
                    // keys (disbursement_id, photographer_id, payout_count)
                    // can never be shadowed by a renegade caller.
                    'metadata'  => array_merge($meta, [
                        'disbursement_id'  => (string) ($meta['disbursement_id'] ?? ''),
                        'photographer_id'  => (string) $recipient->user_id,
                        'payout_count'     => (string) ($meta['payout_count'] ?? ''),
                    ]),
                ]);
        } catch (\Throwable $e) {
            throw new RetryablePayoutException(
                'Omise /transfers network error: ' . $e->getMessage(),
                previous: $e,
            );
        }

        // 5xx + 429 = Omise's fault; retry on our side.
        if ($response->serverError() || $response->status() === 429) {
            throw new RetryablePayoutException(
                "Omise /transfers returned {$response->status()}",
                providerCode: (string) $response->status(),
            );
        }

        $data = $response->json() ?? [];

        // 4xx = our fault; permanent failure with the provider's error code.
        if ($response->clientError()) {
            return PayoutResult::failed(
                errorCode: $data['code'] ?? ('http_' . $response->status()),
                message: $data['message'] ?? 'Omise rejected the transfer',
                raw: $data,
            );
        }

        // 3. Map Omise's response status. `paid=true` is the authoritative
        //    "money landed" signal; `sent=true` with `paid=false` means the
        //    transfer is enroute and we'll hear about the final state via
        //    webhook. Anything else is a failure.
        $transferId = $data['id'] ?? null;
        $status     = $data['status'] ?? '';
        $paid       = (bool) ($data['paid'] ?? false);
        $sent       = (bool) ($data['sent'] ?? false);

        if ($paid || $status === 'paid') {
            return PayoutResult::succeeded($transferId, $data);
        }

        if ($sent || in_array($status, ['sent', 'pending'], true)) {
            return PayoutResult::pending($transferId, $data);
        }

        return PayoutResult::failed(
            errorCode: $data['failure_code'] ?? 'unknown_status',
            message: $data['failure_message'] ?? 'Omise returned an unexpected status',
            raw: $data,
        );
    }

    /**
     * Create (and return) the Omise recipient for this photographer. Returns
     * a PayoutResult failure instead of an id when the recipient couldn't be
     * created — caller should bail out of transfer().
     *
     * The recipient is created as type=individual with a PromptPay
     * bank_account so transfers auto-route through Omise's PromptPay rail.
     * Name preference is the verified PromptPay name → account name → display
     * name, mirroring the dashboard's tier-up nudge.
     */
    private function ensureRecipient(PhotographerProfile $recipient): string|PayoutResult
    {
        $name = $recipient->promptpay_verified_name
            ?: $recipient->bank_account_name
            ?: $recipient->display_name
            ?: ('PG-' . $recipient->user_id);

        try {
            $response = Http::withBasicAuth($this->secretKey, '')
                ->timeout(self::TIMEOUT)
                ->acceptJson()
                ->asForm()
                ->post(self::API_BASE . '/recipients', [
                    'name'           => $name,
                    'type'           => 'individual',
                    'bank_account'   => [
                        'brand'  => 'promptpay',
                        'number' => $recipient->promptpay_number,
                        'name'   => $name,
                    ],
                ]);
        } catch (\Throwable $e) {
            throw new RetryablePayoutException(
                'Omise /recipients network error: ' . $e->getMessage(),
                previous: $e,
            );
        }

        if ($response->serverError() || $response->status() === 429) {
            throw new RetryablePayoutException(
                "Omise /recipients returned {$response->status()}",
                providerCode: (string) $response->status(),
            );
        }

        $data = $response->json() ?? [];

        if ($response->clientError() || empty($data['id'])) {
            return PayoutResult::failed(
                errorCode: $data['code'] ?? 'recipient_create_failed',
                message: $data['message'] ?? 'Could not create Omise recipient',
                raw: $data,
            );
        }

        Log::info('Omise recipient created', [
            'photographer_id' => $recipient->user_id,
            'recipient_id'    => $data['id'],
        ]);

        return $data['id'];
    }

    public function pollStatus(string $providerTxnId): PayoutResult
    {
        if (!$this->secretKey) {
            return PayoutResult::failed('provider_not_configured', 'OMISE_SECRET_KEY missing');
        }

        try {
            $response = Http::withBasicAuth($this->secretKey, '')
                ->timeout(self::TIMEOUT)
                ->acceptJson()
                ->get(self::API_BASE . '/transfers/' . $providerTxnId);

            if ($response->serverError() || $response->status() === 429) {
                throw new RetryablePayoutException(
                    "Omise poll failed (status={$response->status()})",
                    providerCode: (string) $response->status()
                );
            }

            $data = $response->json() ?? [];
            $status = $data['status'] ?? 'unknown';

            return match ($status) {
                'paid', 'successful' => PayoutResult::succeeded($providerTxnId, $data),
                'sent', 'pending'    => PayoutResult::pending($providerTxnId, $data),
                default              => PayoutResult::failed(
                    errorCode: $data['failure_code'] ?? 'unknown',
                    message: $data['failure_message'] ?? 'Transfer failed or unknown state',
                    raw: $data
                ),
            };
        } catch (RetryablePayoutException $e) {
            throw $e;
        } catch (\Throwable $e) {
            // Network failures, DNS errors, timeouts — always retryable.
            throw new RetryablePayoutException(
                'Omise poll network error: ' . $e->getMessage(),
                previous: $e,
            );
        }
    }

    public function name(): string
    {
        return 'omise';
    }

    public function healthCheck(): bool
    {
        if (!$this->secretKey) return false;

        try {
            // Account endpoint is the cheapest auth-probe — no mutation, no cost.
            $response = Http::withBasicAuth($this->secretKey, '')
                ->timeout(self::TIMEOUT)
                ->acceptJson()
                ->get(self::API_BASE . '/account');
            return $response->successful();
        } catch (\Throwable) {
            return false;
        }
    }
}

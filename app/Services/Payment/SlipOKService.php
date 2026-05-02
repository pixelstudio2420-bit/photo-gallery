<?php

namespace App\Services\Payment;

use App\Models\AppSetting;
use App\Models\BankAccount;
use App\Models\PaymentSlip;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * SlipOK third-party slip verification.
 *
 * Wraps the SlipOK REST endpoint and normalises its response into a predictable
 * shape so callers don't have to care about the exact wire format. Also exposes
 * helpers the verifier needs:
 *
 *   - normalisedResult() — extract amount / transRef / receiver / sender from
 *                          whichever nesting SlipOK returned.
 *   - matchesOurBankAccount() — true if the receiver on the slip matches one
 *                               of our configured bank accounts. Strong signal
 *                               against "user sent to the wrong bank" fraud.
 *   - isDuplicateTransRef() — checks payment_slips for a prior slip with the
 *                             same transRef (absolute proof of reuse — the
 *                             transRef is unique per real bank transaction).
 */
class SlipOKService
{
    /**
     * Default URL pattern when admin only saved the legacy branch_id.
     * The full URL admin pastes from the SlipOK dashboard takes precedence.
     */
    private const API_BASE = 'https://api.slipok.com/api/line/apikey';

    /**
     * Check if SlipOK integration is enabled via app_settings.
     */
    public function isEnabled(): bool
    {
        return AppSetting::get('slipok_enabled', '0') === '1';
    }

    /**
     * Resolve the SlipOK endpoint URL, honouring (in order):
     *   1. `slipok_api_url` — the FULL endpoint admin pasted from the SlipOK
     *      dashboard. This is the canonical config — SlipOK only gives users
     *      a URL + an API key, no separate "Branch ID" concept exists in
     *      their UI.
     *   2. `slipok_branch_id` — legacy field, kept for installs that set
     *      this before we switched to URL-only. We auto-build the full URL
     *      by appending it to API_BASE.
     *
     * Returns null when neither is set.
     */
    public function resolveApiUrl(): ?string
    {
        $url = trim((string) AppSetting::get('slipok_api_url', ''));
        if ($url !== '' && preg_match('#^https?://#i', $url)) {
            return rtrim($url, '/');
        }

        // Legacy fallback — admin saved branch_id back when the UI asked for it.
        $branchId = trim((string) AppSetting::get('slipok_branch_id', ''));
        if ($branchId !== '') {
            return self::API_BASE . '/' . $branchId;
        }
        return null;
    }

    /**
     * Are credentials configured (so we can actually call the API)?
     * Need an API key + a resolvable endpoint URL.
     */
    public function isConfigured(): bool
    {
        return !empty(AppSetting::get('slipok_api_key', ''))
            && $this->resolveApiUrl() !== null;
    }

    /**
     * Verify slip via SlipOK external API.
     *
     * POST {slipok_api_url}     ← full URL from SlipOK dashboard
     * Headers: x-authorization: {api_key}
     * Body:    multipart file upload (files[])
     *
     * @return array{success: bool, data: array, error_code: string|null, raw: array}
     */
    public function verify(string $imagePath): array
    {
        $apiKey = AppSetting::get('slipok_api_key', '');
        $apiUrl = $this->resolveApiUrl();

        // Every diagnostic log line carries enough context that an admin
        // can answer "why didn't this slip go to SlipOK?" by greping
        // production logs for the slip filename. Keep PII out (no API key).
        $logCtx = [
            'image'         => basename($imagePath),
            'has_api_key'   => $apiKey !== '',
            'api_url_set'   => $apiUrl !== null,
        ];

        if (empty($apiKey) || $apiUrl === null) {
            Log::warning('SlipOK verify SKIPPED — credentials missing', $logCtx);
            return ['success' => false, 'data' => [], 'error_code' => 'MISSING_CREDENTIALS', 'raw' => []];
        }

        if (!file_exists($imagePath)) {
            Log::warning('SlipOK verify SKIPPED — file not found', $logCtx + ['path' => $imagePath]);
            return ['success' => false, 'data' => [], 'error_code' => 'FILE_NOT_FOUND', 'raw' => []];
        }

        $start = microtime(true);
        try {
            // SlipOK reads $_FILES['files'] directly (not an array) — using
            // 'files[]' makes PHP parse the field as $_FILES['files'][0],
            // which SlipOK ignores and then returns code:1000
            // ("กรุณาใส่ข้อมูล QR Code ให้ครบใน field data, files หรือ url").
            // The field name MUST be the literal string 'files'.
            //
            // Pass the binary content directly (not a resource handle) so
            // Laravel's HTTP client doesn't accidentally close the stream
            // before the multipart writer reads it.
            $response = Http::timeout(15)
                ->withHeaders(['x-authorization' => $apiKey])
                ->attach('files', file_get_contents($imagePath), basename($imagePath))
                ->post($apiUrl);

            $elapsed = (int) ((microtime(true) - $start) * 1000);
            $body    = $response->json() ?? [];

            if ($response->successful() && ($body['success'] ?? false)) {
                Log::info('SlipOK verify SUCCESS', $logCtx + [
                    'elapsed_ms' => $elapsed,
                    'trans_ref'  => $body['data']['transRef'] ?? null,
                    'amount'     => $body['data']['amount']   ?? null,
                ]);
                return [
                    'success'    => true,
                    'data'       => $body['data'] ?? $body,
                    'error_code' => null,
                    'raw'        => $body,
                ];
            }

            // Distinguish API-level rejection (200 with success=false)
            // from HTTP-level error (4xx/5xx). The error_code captured in
            // both cases helps admin spot patterns ("seeing 1012 a lot →
            // API key got rotated").
            $errorCode = (string) ($body['code'] ?? $body['error'] ?? $response->status());
            $errorMsg  = (string) ($body['message'] ?? $body['error'] ?? '');
            Log::warning('SlipOK verify FAILED — API returned non-success', $logCtx + [
                'http_status' => $response->status(),
                'error_code'  => $errorCode,
                'message'     => $errorMsg,
                'elapsed_ms'  => $elapsed,
            ]);
            return [
                'success'    => false,
                'data'       => $body,
                'error_code' => $errorCode,
                'error_msg'  => $errorMsg,
                'raw'        => $body,
            ];
        } catch (\Throwable $e) {
            $elapsed = (int) ((microtime(true) - $start) * 1000);
            Log::error('SlipOK verify EXCEPTION', $logCtx + [
                'error'      => $e->getMessage(),
                'elapsed_ms' => $elapsed,
            ]);
            return [
                'success'    => false,
                'data'       => [],
                'error_code' => 'EXCEPTION',
                'error_msg'  => $e->getMessage(),
                'raw'        => [],
            ];
        }
    }

    /**
     * Normalise SlipOK's response into a flat shape.
     *
     * SlipOK has historically returned slightly different structures depending
     * on the bank (receiver.displayName vs receiver.name vs receiver.account.name).
     * This merges the common fields we care about.
     *
     * @return array{
     *     amount: float,
     *     trans_ref: string|null,
     *     transfer_date: string|null,
     *     sender_name: string|null,
     *     receiver_name: string|null,
     *     receiver_account: string|null,
     *     receiver_bank: string|null,
     * }
     */
    public function normaliseResult(array $data): array
    {
        $amount = (float) ($data['amount']
            ?? $data['transAmount']
            ?? $data['transferAmount']
            ?? 0);

        $transRef = $data['transRef']
            ?? $data['transactionRef']
            ?? $data['ref']
            ?? null;
        if ($transRef !== null) $transRef = (string) $transRef;

        $transferDate = $data['transDate']
            ?? $data['transTimestamp']
            ?? $data['date']
            ?? $data['transferDate']
            ?? null;
        if ($transferDate !== null) $transferDate = (string) $transferDate;

        $sender = $data['sender'] ?? [];
        $recv   = $data['receiver'] ?? [];

        $senderName = $this->pickName($sender);
        $recvName   = $this->pickName($recv);

        $recvAccount = $recv['account']['value']
            ?? $recv['account']['number']
            ?? $recv['accountNumber']
            ?? $recv['account']
            ?? null;
        if (is_array($recvAccount)) {
            $recvAccount = $recvAccount['value'] ?? $recvAccount['number'] ?? null;
        }
        if ($recvAccount !== null) $recvAccount = (string) $recvAccount;

        $recvBank = $recv['bank']['id']
            ?? $recv['bank']['code']
            ?? $recv['bankCode']
            ?? $recv['sendingBank']
            ?? null;
        if ($recvBank !== null) $recvBank = (string) $recvBank;

        return [
            'amount'           => $amount,
            'trans_ref'        => $transRef,
            'transfer_date'    => $transferDate,
            'sender_name'      => $senderName,
            'receiver_name'    => $recvName,
            'receiver_account' => $recvAccount,
            'receiver_bank'    => $recvBank,
        ];
    }

    /**
     * Does this slip's receiver match one of our configured bank accounts?
     *
     * Match logic:
     *   1. Compare last-4 digits of the account number (SlipOK often masks
     *      middle digits, so full-string compare fails).
     *   2. If that's inconclusive, fall back to account-holder-name fuzzy match.
     */
    public function matchesOurBankAccount(array $normalised): bool
    {
        $recvAccount = $normalised['receiver_account'] ?? null;
        $recvName    = $normalised['receiver_name'] ?? null;

        if (empty($recvAccount) && empty($recvName)) {
            return false;
        }

        $recvLast4 = $recvAccount ? substr(preg_replace('/\D/', '', $recvAccount), -4) : null;

        try {
            $accounts = BankAccount::where('is_active', 1)->get(['account_number', 'account_holder_name']);
        } catch (\Throwable $e) {
            return false;
        }

        foreach ($accounts as $acct) {
            $ourDigits = preg_replace('/\D/', '', (string) $acct->account_number);
            $ourLast4  = substr($ourDigits, -4);

            // Primary signal: matching last-4 digits.
            if ($recvLast4 && $ourLast4 && $recvLast4 === $ourLast4) {
                return true;
            }

            // Fallback: name match (case- and whitespace-insensitive).
            if ($recvName && $acct->account_holder_name) {
                $a = $this->normaliseName($recvName);
                $b = $this->normaliseName($acct->account_holder_name);
                if ($a !== '' && $b !== '' && ($a === $b || str_contains($a, $b) || str_contains($b, $a))) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if this SlipOK transRef has been submitted before (i.e. someone
     * re-uploaded the same real bank transaction). A duplicate transRef is
     * the strongest possible fraud signal — far more reliable than image-hash
     * duplicates, since the attacker can edit the image pixels but can't
     * fabricate a new transRef.
     */
    public function isDuplicateTransRef(?string $transRef, ?int $excludeSlipId = null): bool
    {
        if (empty($transRef)) {
            return false;
        }
        try {
            $q = PaymentSlip::where('slipok_trans_ref', $transRef)
                ->where('verify_status', '!=', 'rejected');
            if ($excludeSlipId) {
                $q->where('id', '!=', $excludeSlipId);
            }
            return $q->exists();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /*----------------------------------------------------------------------
    | Helpers
    |----------------------------------------------------------------------*/

    private function pickName(array $party): ?string
    {
        $candidates = [
            $party['displayName'] ?? null,
            $party['name'] ?? null,
            ($party['name']['th'] ?? null),
            ($party['name']['en'] ?? null),
            $party['account']['name']['th'] ?? null,
            $party['account']['name']['en'] ?? null,
            $party['account']['name'] ?? null,
        ];
        foreach ($candidates as $c) {
            if (is_string($c) && trim($c) !== '') {
                return trim($c);
            }
        }
        return null;
    }

    private function normaliseName(string $s): string
    {
        $s = mb_strtolower(trim($s));
        $s = preg_replace('/\s+/', ' ', $s);
        $s = preg_replace('/^(นาย|นาง|นางสาว|น\.ส\.|mr|ms|mrs|miss|mr\.|ms\.|mrs\.)\s+/u', '', $s);
        return $s ?? '';
    }
}

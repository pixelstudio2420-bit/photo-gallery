<?php

namespace App\Services;

/**
 * PromptPay identifier validation + masking.
 *
 * PromptPay is Thailand's national instant-transfer rail. A photographer binds
 * their payout account to either:
 *   • a mobile phone number (10 digits, normalised to 0XXXXXXXXX)
 *   • a national citizen ID (13 digits)
 *
 * Design decision — NO local name lookup:
 *   The only authoritative source for "whose name does this PromptPay belong
 *   to?" is Thailand's ITMX central switch. Access to the ITMX Name Inquiry
 *   API requires a bank/non-bank member license or a payment-gateway contract
 *   (Omise, GB PrimePay, KBank K-BIZ, SCB Easy, etc.) — we can't just do it
 *   client-side.
 *
 *   Rather than show a fake name from a mock generator (which was the old
 *   behaviour — deceptive and confusing when photographers saw a name that
 *   wasn't theirs), this service now does FORMAT VALIDATION ONLY. The
 *   photographer types their own bank-account name on the form. The ITMX
 *   name is then captured asynchronously from the Omise transfer response
 *   on the FIRST real payout — that's when ITMX actually returns it.
 *
 * Real verification flow:
 *   1. setup-bank form → photographer types PromptPay + bank account name
 *   2. This service validates format only
 *   3. Data saved; profile marked as "ข้อมูลบันทึกแล้ว, รอยืนยันกับธนาคาร"
 *   4. First payout fires → Omise calls ITMX → response's
 *      `bank_account.name` = ITMX's verified name
 *   5. PhotographerDisbursement::markSucceeded() stamps
 *      promptpay_verified_name + promptpay_verified_at from the provider
 *      response. If ITMX's name differs from what the photographer typed,
 *      the ITMX version wins (it's the legal source of truth).
 */
class PromptPayService
{
    /**
     * Normalise a user-typed PromptPay ID. Accepts hyphens, spaces, and the
     * leading "+66" country code; returns digits-only.
     *
     * "0812345678"         → "0812345678"
     * "+66 81 234 5678"    → "0812345678"   (+66 → 0)
     * "1-1020-04567-89-0"  → "1102004567890"
     */
    public function normalise(?string $raw): string
    {
        $raw = trim((string) $raw);
        if ($raw === '') return '';

        // Strip everything that isn't a digit or plus sign.
        $compact = preg_replace('/[^\d+]/', '', $raw);

        // +66xxxxxxxxx → 0xxxxxxxxx (Thai phone in international form)
        if (str_starts_with($compact, '+66')) {
            $compact = '0' . substr($compact, 3);
        }
        // 66xxxxxxxxx (some inputs forget the +) → only treat as phone if the
        // resulting length would be a 10-digit Thai number, otherwise leave
        // alone so a citizen ID starting with 66 isn't mangled.
        elseif (str_starts_with($compact, '66') && strlen($compact) === 11) {
            $compact = '0' . substr($compact, 2);
        }

        return preg_replace('/\D/', '', $compact);
    }

    /**
     * Is this a plausibly valid PromptPay ID? Format-only — does NOT check
     * the number actually exists at a bank. The real check happens at
     * payout time via the provider.
     *
     * Returns the identifier type for UI hints, or null if invalid.
     */
    public function classify(string $normalised): ?string
    {
        if ($normalised === '') return null;

        // Thai mobile numbers are 10 digits starting with 0 (subscriber range
        // 06/08/09 today; tolerate 07 for future allocations).
        if (strlen($normalised) === 10 && str_starts_with($normalised, '0')) {
            return 'phone';
        }

        // National ID is exactly 13 digits. We don't enforce the checksum here
        // because legacy test accounts and admin-entered values may not pass
        // it — the real bank lookup is the source of truth at payout time.
        if (strlen($normalised) === 13) {
            return 'citizen_id';
        }

        return null;
    }

    /**
     * Validate a PromptPay ID's FORMAT. No name is returned — the only way
     * to get an ITMX-verified name is to perform a real transfer via a
     * licensed provider (Omise). This is a deliberate anti-deception measure:
     * the old code used to return a deterministic fake name here, which
     * confused photographers into thinking the system knew their identity.
     *
     * Returns:
     *   ['ok' => true,  'normalised' => '0812345678', 'type' => 'phone',       'masked' => '081-XXX-5678']
     *   ['ok' => false, 'error' => 'invalid_format', 'message' => '...']
     *
     * Callers should pair this with a user-typed bank-account name (saved as
     * `photographer_profiles.bank_account_name`). The profile's verification
     * flags (`promptpay_verified_name`, `promptpay_verified_at`) remain null
     * until the first successful provider transfer confirms the ITMX name.
     */
    public function validateFormat(string $raw): array
    {
        $normalised = $this->normalise($raw);
        $type       = $this->classify($normalised);

        if (!$type) {
            return [
                'ok'      => false,
                'error'   => 'invalid_format',
                'message' => 'หมายเลข PromptPay ต้องเป็นเบอร์โทร 10 หลัก หรือเลขบัตรประชาชน 13 หลัก',
            ];
        }

        return [
            'ok'         => true,
            'normalised' => $normalised,
            'type'       => $type,
            'masked'     => $this->mask($normalised, $type),
        ];
    }

    /** Masked display form so logs + UI don't leak the full identifier. */
    public function mask(string $normalised, string $type): string
    {
        if ($type === 'phone' && strlen($normalised) === 10) {
            return substr($normalised, 0, 3) . '-XXX-' . substr($normalised, -4);
        }
        if ($type === 'citizen_id' && strlen($normalised) === 13) {
            return substr($normalised, 0, 1) . '-XXXX-XXXXX-' . substr($normalised, -2) . '-' . substr($normalised, -1);
        }
        return str_repeat('X', max(0, strlen($normalised) - 4)) . substr($normalised, -4);
    }
}

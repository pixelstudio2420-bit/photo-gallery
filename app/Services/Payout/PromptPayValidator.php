<?php

namespace App\Services\Payout;

/**
 * Validates PromptPay identifiers (Thai mobile or NID) for use as a
 * disbursement target on photographer_profiles.promptpay_number.
 *
 * Why this exists
 * ───────────────
 * Before this validator the payout flow blindly trusted whatever the
 * photographer typed into the form. Two failure modes that hit
 * production: (a) malformed numbers (with dashes/spaces) → bank rejected
 * the transfer with a generic error; (b) typos in the NID → silent
 * misroute to a stranger's account. Both required manual reversal.
 *
 * Strategy
 * ────────
 * We do format validation NOW + a manual receiver-name attestation
 * that admin checks against the bank-resolved account holder. A
 * full-bank-API verification (e.g. Omise's recipient creation) is
 * the right long-term answer; this service is shaped so callers can
 * swap in that path when the credentials are wired up.
 *
 * Two identifier shapes accepted:
 *   1. 10-digit Thai mobile (starts with 0). Stored normalised to
 *      66-prefixed without leading zero — that's the canonical
 *      PromptPay form for QR generation.
 *   2. 13-digit Thai national ID. Validated with the official Mod-11
 *      checksum (ฐานการคำนวณตัวเลขตรวจสอบ) so transposition typos
 *      get caught at form time.
 *
 * Returns a ValidationResult with the normalised value + a list of
 * reasons when invalid. Callers can persist normalisedValue verbatim.
 */
class PromptPayValidator
{
    public const TYPE_MOBILE = 'mobile';
    public const TYPE_NID    = 'nid';

    /**
     * @return array{
     *     valid: bool,
     *     type: ?string,
     *     normalised: ?string,
     *     errors: list<string>,
     * }
     */
    public function validate(?string $raw): array
    {
        if ($raw === null || trim($raw) === '') {
            return [
                'valid'      => false,
                'type'       => null,
                'normalised' => null,
                'errors'     => ['empty'],
            ];
        }

        // Strip any non-digit decoration (dashes, spaces, +66 prefix).
        $digits = preg_replace('/\D+/', '', (string) $raw);

        if ($digits === '') {
            return [
                'valid'      => false,
                'type'       => null,
                'normalised' => null,
                'errors'     => ['no_digits'],
            ];
        }

        // 10-digit mobile starting with 0
        if (strlen($digits) === 10 && str_starts_with($digits, '0')) {
            return [
                'valid'      => true,
                'type'       => self::TYPE_MOBILE,
                // PromptPay canonical form: 66 + drop the leading 0
                'normalised' => '66' . substr($digits, 1),
                'errors'     => [],
            ];
        }

        // 11-digit "66…" — already in PromptPay canonical form (e.g.
        // pasted from "+66 81 234 5678" after strip). Accept verbatim.
        if (strlen($digits) === 11 && str_starts_with($digits, '66')) {
            return [
                'valid'      => true,
                'type'       => self::TYPE_MOBILE,
                'normalised' => $digits,
                'errors'     => [],
            ];
        }

        // 13-digit Thai NID with checksum
        if (strlen($digits) === 13) {
            if (!$this->validNidChecksum($digits)) {
                return [
                    'valid'      => false,
                    'type'       => self::TYPE_NID,
                    'normalised' => null,
                    'errors'     => ['nid_checksum_failed'],
                ];
            }
            return [
                'valid'      => true,
                'type'       => self::TYPE_NID,
                'normalised' => $digits,
                'errors'     => [],
            ];
        }

        return [
            'valid'      => false,
            'type'       => null,
            'normalised' => null,
            'errors'     => ['unknown_format'],
        ];
    }

    /**
     * Mod-11 checksum used by Thai NIDs.
     *
     * Algorithm:
     *   sum = Σ digit[i] × (13 - i)   for i = 0..11
     *   check = (11 - (sum mod 11)) mod 10
     *   valid iff check == digit[12]
     */
    public function validNidChecksum(string $digits): bool
    {
        if (strlen($digits) !== 13 || !ctype_digit($digits)) {
            return false;
        }
        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $sum += (int) $digits[$i] * (13 - $i);
        }
        $check = (11 - ($sum % 11)) % 10;
        return $check === (int) $digits[12];
    }

    /**
     * Convenience — does this raw input look like a PromptPay identifier
     * we'd accept? (Just a yes/no without the structured result.)
     */
    public function isValid(?string $raw): bool
    {
        return $this->validate($raw)['valid'];
    }
}

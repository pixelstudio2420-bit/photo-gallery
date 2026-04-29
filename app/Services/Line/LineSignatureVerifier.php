<?php

namespace App\Services\Line;

use App\Models\AppSetting;

/**
 * Verifies the X-Line-Signature header on inbound webhook requests.
 *
 * The contract LINE specifies (per https://developers.line.biz/en/reference/messaging-api/#signature-validation):
 *
 *   signature = base64( HMAC-SHA256( channel_secret, raw_body ) )
 *
 * Critical implementation notes
 * -----------------------------
 *
 *  • The HMAC is over the RAW BODY BYTES, not the parsed JSON. We must
 *    not call $request->all() before reading $request->getContent() —
 *    Laravel's framework parsing is byte-faithful for JSON, but the
 *    safer pattern is `getContent()` first and pass that explicit string
 *    in.
 *
 *  • Signature comparison MUST use hash_equals() / constant-time
 *    comparison. A naïve `===` leaks signature bytes through timing.
 *
 *  • The secret comes from app_settings (not .env) because that's where
 *    every other LINE config lives in this codebase. Misalignment with
 *    what the admin UI shows would be a debugging nightmare.
 *
 *  • A missing signature header is ALWAYS a rejection. Production LINE
 *    always sends one; absence means "not LINE" — most likely a stray
 *    pentest probe or someone testing the URL.
 *
 *  • We deliberately do NOT log the actual signature in audit rows —
 *    it's not secret per se, but logging request headers in full has
 *    historically leaked auth tokens by accident in this codebase.
 */
class LineSignatureVerifier
{
    /**
     * Returns true if the signature matches the channel secret + body.
     * Caller is responsible for issuing the 401 response when this
     * returns false.
     *
     * Rejects on any of:
     *   • channel secret not configured (we won't accept anything if
     *     the gate is unset — fail closed),
     *   • signature header missing or empty,
     *   • signature does not match.
     */
    public function verify(string $rawBody, ?string $signatureHeader): bool
    {
        $secret = $this->channelSecret();
        if ($secret === '') {
            return false;
        }
        if ($signatureHeader === null || $signatureHeader === '') {
            return false;
        }

        $expected = base64_encode(hash_hmac('sha256', $rawBody, $secret, true));

        // Constant-time comparison defeats timing-side-channel attacks
        // that could otherwise leak the signature byte by byte.
        return hash_equals($expected, $signatureHeader);
    }

    /**
     * Pull the channel secret from app_settings. Cached at the app level
     * by AppSetting::get itself, so calling this on every webhook is
     * fine — no need for a per-request cache here.
     */
    public function channelSecret(): string
    {
        return (string) AppSetting::get('line_channel_secret', '');
    }

    /**
     * Compute the signature for outbound webhook tests / verification.
     * Public so tests can build valid payloads without duplicating the
     * formula.
     */
    public function sign(string $rawBody, ?string $secretOverride = null): string
    {
        $secret = $secretOverride ?? $this->channelSecret();
        return base64_encode(hash_hmac('sha256', $rawBody, $secret, true));
    }
}

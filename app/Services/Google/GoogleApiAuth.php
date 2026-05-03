<?php

namespace App\Services\Google;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * GoogleApiAuth — service-account OAuth 2.0 token issuance.
 *
 * Why we hand-roll JWT instead of using google/apiclient:
 *   • google/apiclient pulls in gRPC (~100MB) which doesn't deploy
 *     cleanly to shared hosting + Laravel Cloud
 *   • The actual auth flow is ~30 lines of straightforward code
 *     (build JWT → sign with RS256 → exchange for access token)
 *   • Smaller surface = easier to mock + audit
 *
 * Token caching:
 *   Access tokens are valid for 1 hour. We cache for 50 minutes (10
 *   min safety margin) so callers always get a fresh token without
 *   re-issuing on every request. Cache key includes the service
 *   account email so swapping accounts doesn't surface stale tokens.
 *
 * Service account JSON structure (downloaded from Cloud Console):
 *   {
 *     "type": "service_account",
 *     "project_id": "...",
 *     "private_key_id": "...",
 *     "private_key": "-----BEGIN PRIVATE KEY-----\n...",
 *     "client_email": "name@project.iam.gserviceaccount.com",
 *     ...
 *   }
 *
 * Required setup outside our app:
 *   1. Cloud Console → Create service account → download JSON
 *   2. Grant the service account email Viewer access to the GA4
 *      property (Admin → Property access management)
 *   3. Add the same email to Search Console (Settings → Users)
 *   4. Enable APIs: Analytics Data API + Search Console API
 *
 * On failure (missing creds / wrong key / network), getAccessToken()
 * throws \RuntimeException with an actionable message — callers (the
 * data services) catch + return empty results so dashboards degrade
 * gracefully.
 */
class GoogleApiAuth
{
    /** OAuth2 token exchange endpoint */
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    /** JWT bearer grant per RFC 7523 */
    private const GRANT_TYPE = 'urn:ietf:params:oauth:grant-type:jwt-bearer';

    /**
     * Get an access token for the requested scope. Uses cache if
     * a valid token exists; otherwise mints a new JWT, exchanges it,
     * and caches the result.
     *
     * Scopes are space-separated strings (or arrays which we join).
     * Common scopes:
     *   - https://www.googleapis.com/auth/analytics.readonly  (GA4 Data + Realtime)
     *   - https://www.googleapis.com/auth/webmasters.readonly (Search Console)
     */
    public function getAccessToken(string|array $scopes): string
    {
        $scope = is_array($scopes) ? implode(' ', $scopes) : $scopes;

        $credentials = $this->serviceAccountCredentials();
        if (!$credentials) {
            throw new \RuntimeException('Service account JSON not configured');
        }

        $email = $credentials['client_email'] ?? '';
        $cacheKey = 'google_api_token:' . md5($email . '|' . $scope);

        return Cache::remember($cacheKey, 60 * 50, function () use ($credentials, $scope) {
            return $this->exchangeJwtForToken($credentials, $scope);
        });
    }

    /**
     * Whether the admin has uploaded a valid-looking service account
     * JSON. Doesn't actually call Google — just structural check.
     * Used by feature toggles and admin settings UI.
     */
    public function isConfigured(): bool
    {
        $creds = $this->serviceAccountCredentials();
        return $creds
            && !empty($creds['client_email'])
            && !empty($creds['private_key'])
            && !empty($creds['type'])
            && $creds['type'] === 'service_account';
    }

    /**
     * Service account email — useful for the admin UI to display
     * "grant this email access to your GA4 property" instructions.
     */
    public function serviceAccountEmail(): ?string
    {
        return $this->serviceAccountCredentials()['client_email'] ?? null;
    }

    /**
     * Bust the cached access token — call after admin uploads new
     * credentials so the next API call doesn't use the stale token.
     */
    public function bustCache(): void
    {
        $creds = $this->serviceAccountCredentials();
        if (!$creds) return;
        $email = $creds['client_email'] ?? '';

        // Cache::flush() is overkill but Laravel doesn't expose pattern-
        // delete on file/array drivers. We could iterate known scopes
        // but flush is simpler and the impact is just one extra round-
        // trip on next call.
        try { Cache::flush(); } catch (\Throwable) {}
    }

    /* ─────────────────── internals ─────────────────── */

    /**
     * Read & parse the service account JSON from AppSetting (preferred)
     * or env var (fallback for local dev / CI). Returns array or null.
     *
     * Storage: `app_settings.google_service_account_json` holds the
     * raw JSON string. We parse on every call (cheap — JSON ~3KB) so
     * admin can rotate keys without restart.
     */
    private function serviceAccountCredentials(): ?array
    {
        $raw = (string) (AppSetting::get('google_service_account_json', '') ?: env('GOOGLE_SERVICE_ACCOUNT_JSON', ''));
        if ($raw === '') return null;

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) return null;

        return $decoded;
    }

    /**
     * Build a signed JWT and POST it to Google's token endpoint.
     * Returns the bare access_token string. Caches via getAccessToken.
     */
    private function exchangeJwtForToken(array $credentials, string $scope): string
    {
        $now = time();
        $payload = [
            'iss'   => $credentials['client_email'],
            'scope' => $scope,
            'aud'   => self::TOKEN_URL,
            'iat'   => $now,
            'exp'   => $now + 3600,
        ];

        $jwt = $this->signJwtRs256($payload, $credentials['private_key']);

        $response = Http::asForm()
            ->timeout(15)
            ->retry(2, 250)
            ->post(self::TOKEN_URL, [
                'grant_type' => self::GRANT_TYPE,
                'assertion'  => $jwt,
            ]);

        if (!$response->successful()) {
            $error = $response->json('error_description')
                  ?? $response->json('error')
                  ?? $response->body();
            throw new \RuntimeException("Token exchange failed: {$error}");
        }

        $token = $response->json('access_token');
        if (!$token) {
            throw new \RuntimeException('Token exchange returned no access_token');
        }

        return $token;
    }

    /**
     * Sign a JWT payload with RS256 using the service account private
     * key. Returns the dot-joined header.payload.signature string.
     *
     * Uses PHP's openssl_sign — no external crypto library needed.
     * Private key is PKCS#8 PEM as stored in Google's JSON.
     */
    private function signJwtRs256(array $payload, string $privateKey): string
    {
        $header = ['alg' => 'RS256', 'typ' => 'JWT'];

        $encodedHeader  = $this->base64UrlEncode(json_encode($header));
        $encodedPayload = $this->base64UrlEncode(json_encode($payload));

        $signingInput = $encodedHeader . '.' . $encodedPayload;
        $signature = '';

        $keyResource = openssl_pkey_get_private($privateKey);
        if (!$keyResource) {
            throw new \RuntimeException('Invalid private key in service account JSON');
        }

        $signed = openssl_sign($signingInput, $signature, $keyResource, 'sha256WithRSAEncryption');
        if (!$signed) {
            throw new \RuntimeException('JWT signing failed');
        }

        return $signingInput . '.' . $this->base64UrlEncode($signature);
    }

    /**
     * Base64-URL encoding per RFC 7515. Converts standard base64 by
     * replacing +/ with -_ and stripping = padding.
     */
    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}

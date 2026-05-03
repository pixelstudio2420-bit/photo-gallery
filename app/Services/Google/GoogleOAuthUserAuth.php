<?php

namespace App\Services\Google;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * GoogleOAuthUserAuth — OAuth 2.0 USER flow (not service account).
 *
 * Why we need this in addition to GoogleApiAuth:
 *   Search Console's "Add user" UI rejects service account emails
 *   ("ไม่พบอีเมล" / "email not found") — a known-broken validation
 *   in the Google UI that's been open for years. There's no
 *   programmatic way to add a service account to a Search Console
 *   property; it has to go through the Add User flow.
 *
 *   So instead of adding the service account, we use the ADMIN's
 *   own Google account (which already has owner permission on
 *   Search Console). Admin clicks "Connect" → standard Google
 *   OAuth consent screen → grants read-only access → we store the
 *   refresh token long-term and use it for all Search Console calls.
 *
 * The flow:
 *   1. Admin uploads OAuth Client ID + Secret
 *      (from same Cloud Console project as the service account)
 *   2. Admin clicks "Connect with Google"
 *      → we build authorization URL with offline access + the
 *        webmasters.readonly scope
 *      → redirect them to Google
 *   3. Google redirects back to /admin/settings/google-apis/oauth-callback
 *      with ?code=... in the URL
 *   4. We exchange the code for a refresh_token + access_token
 *   5. Store refresh_token in AppSetting (long-lived)
 *   6. From then on, getAccessToken() uses the refresh_token to
 *      mint short-lived access tokens on demand (cached 50 min)
 *
 * Refresh tokens:
 *   - Granted only when access_type=offline + prompt=consent
 *   - Don't expire (unless explicitly revoked or unused for 6 months)
 *   - Single user per stored token — admin disconnects to switch
 *
 * Note: We DO NOT use this for GA4 — service account works fine
 * for that. Service account is preferable when it works because
 * it doesn't depend on a single admin's Google account.
 */
class GoogleOAuthUserAuth
{
    /** OAuth 2.0 endpoints (Google) */
    private const AUTH_URL    = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_URL   = 'https://oauth2.googleapis.com/token';
    private const REVOKE_URL  = 'https://oauth2.googleapis.com/revoke';
    private const USERINFO    = 'https://www.googleapis.com/oauth2/v2/userinfo';

    /**
     * Whether admin has both:
     *   - configured OAuth credentials (client_id + secret)
     *   - completed the consent flow (refresh_token stored)
     */
    public function isConnected(): bool
    {
        return $this->clientId() !== ''
            && $this->clientSecret() !== ''
            && $this->refreshToken() !== '';
    }

    /**
     * Whether OAuth credentials exist (client_id + secret) but the
     * connection hasn't been completed yet. Used by UI to show the
     * "Connect with Google" CTA vs the "Already connected" state.
     */
    public function hasCredentials(): bool
    {
        return $this->clientId() !== '' && $this->clientSecret() !== '';
    }

    /**
     * The Google account that authorized us, if connected.
     * Cached separately so we don't hit Google every page render.
     */
    public function connectedEmail(): ?string
    {
        return AppSetting::get('google_oauth_user_email', '') ?: null;
    }

    /**
     * Build the authorization URL admin clicks to start consent.
     * State is a CSRF-style nonce we verify on callback.
     *
     * @param string[] $scopes  e.g. ['https://www.googleapis.com/auth/webmasters.readonly']
     * @param string $callbackUrl absolute URL we'll receive code at
     */
    public function buildAuthUrl(array $scopes, string $callbackUrl): string
    {
        $state = Str::random(40);
        AppSetting::set('google_oauth_pending_state', $state);

        // Always include userinfo.email so we can show "connected as X"
        $allScopes = array_unique(array_merge($scopes, [
            'https://www.googleapis.com/auth/userinfo.email',
        ]));

        $params = [
            'client_id'              => $this->clientId(),
            'redirect_uri'           => $callbackUrl,
            'response_type'          => 'code',
            'scope'                  => implode(' ', $allScopes),
            'access_type'            => 'offline',  // request refresh_token
            'prompt'                 => 'consent',  // force re-consent so we always get refresh_token
            'state'                  => $state,
            'include_granted_scopes' => 'true',
        ];

        return self::AUTH_URL . '?' . http_build_query($params);
    }

    /**
     * Handle the OAuth callback: validate state + exchange code for
     * refresh_token. Stores both refresh_token and the connected
     * email in AppSetting. Throws on any failure.
     */
    public function handleCallback(string $code, string $state, string $callbackUrl): array
    {
        $expected = AppSetting::get('google_oauth_pending_state', '');
        if ($state !== $expected || $expected === '') {
            throw new \RuntimeException('State mismatch — possible CSRF attempt or expired session');
        }
        AppSetting::set('google_oauth_pending_state', '');

        $response = Http::asForm()->timeout(15)->post(self::TOKEN_URL, [
            'code'          => $code,
            'client_id'     => $this->clientId(),
            'client_secret' => $this->clientSecret(),
            'redirect_uri'  => $callbackUrl,
            'grant_type'    => 'authorization_code',
        ]);

        if (!$response->successful()) {
            $err = $response->json('error_description') ?? $response->body();
            throw new \RuntimeException("Token exchange failed: {$err}");
        }

        $refreshToken = $response->json('refresh_token');
        $accessToken  = $response->json('access_token');

        if (!$refreshToken) {
            throw new \RuntimeException(
                'No refresh_token returned. Make sure the OAuth consent prompt is set to "consent" '
              . '(not "select_account") and access_type=offline. Try disconnecting at '
              . 'https://myaccount.google.com/permissions and reconnecting.'
            );
        }

        AppSetting::set('google_oauth_user_refresh_token', $refreshToken);

        // Fetch the connected email for display
        try {
            $userInfo = Http::withToken($accessToken)
                ->timeout(10)
                ->get(self::USERINFO)
                ->json();
            if (!empty($userInfo['email'])) {
                AppSetting::set('google_oauth_user_email', $userInfo['email']);
            }
        } catch (\Throwable) {
            // Email lookup is non-critical — refresh_token is stored,
            // we just won't show "connected as X" until next refresh.
        }

        // Bust the cached access token in case there was an old one
        Cache::forget('google_oauth_user_access_token');

        return [
            'refresh_token' => substr($refreshToken, 0, 12) . '…',  // truncated for safety
            'email'         => AppSetting::get('google_oauth_user_email', ''),
        ];
    }

    /**
     * Get a valid access token for API calls. Caches 50 min to dodge
     * the 1h expiry. If refresh fails (token revoked / credentials
     * changed), throws — callers should treat as "not connected" and
     * surface a reconnect prompt.
     */
    public function getAccessToken(): string
    {
        if (!$this->isConnected()) {
            throw new \RuntimeException('OAuth user not connected — admin must complete OAuth flow');
        }

        return Cache::remember('google_oauth_user_access_token', 60 * 50, function () {
            $response = Http::asForm()->timeout(15)->post(self::TOKEN_URL, [
                'client_id'     => $this->clientId(),
                'client_secret' => $this->clientSecret(),
                'refresh_token' => $this->refreshToken(),
                'grant_type'    => 'refresh_token',
            ]);

            if (!$response->successful()) {
                $err = $response->json('error_description') ?? $response->body();
                throw new \RuntimeException("Refresh token exchange failed: {$err}");
            }

            $token = $response->json('access_token');
            if (!$token) throw new \RuntimeException('No access_token returned from refresh');

            return $token;
        });
    }

    /**
     * Disconnect — revokes the token at Google + clears local storage.
     * Best-effort: even if the revoke call fails (e.g. already revoked
     * server-side), we still clear local state.
     */
    public function disconnect(): void
    {
        $token = $this->refreshToken();
        if ($token) {
            try {
                Http::asForm()->timeout(10)->post(self::REVOKE_URL, ['token' => $token]);
            } catch (\Throwable) { /* best effort */ }
        }
        AppSetting::set('google_oauth_user_refresh_token', '');
        AppSetting::set('google_oauth_user_email', '');
        Cache::forget('google_oauth_user_access_token');
    }

    /* ─────────────── internals ─────────────── */

    private function clientId(): string
    {
        return (string) AppSetting::get('google_oauth_client_id', '');
    }

    private function clientSecret(): string
    {
        return (string) AppSetting::get('google_oauth_client_secret', '');
    }

    private function refreshToken(): string
    {
        return (string) AppSetting::get('google_oauth_user_refresh_token', '');
    }
}

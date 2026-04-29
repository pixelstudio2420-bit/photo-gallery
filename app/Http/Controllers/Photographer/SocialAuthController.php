<?php

namespace App\Http\Controllers\Photographer;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\PhotographerProfile;
use App\Models\SocialLogin;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;

/**
 * Photographer Social Login (Google + LINE)
 * ─────────────────────────────────────────
 *
 * Replaces email-verification as the primary identity proof for
 * photographers. Two flows the same controller handles:
 *
 *   1. NEW photographer (not logged in)
 *      → social provider returns identity → if a SocialLogin row
 *        already exists for (provider, provider_id), log them in.
 *      → else create a User + PhotographerProfile + SocialLogin
 *        in one transaction. status='active' (no email-verify wait).
 *
 *   2. EXISTING photographer (logged in via password)
 *      → "link my account" flow. Social provider identity is bound
 *        to the current user's id. Future logins via that provider
 *        find the same user.
 *
 * Admin can pin a `photographer_primary_oauth_provider` setting
 * ('google' | 'line' | 'both') which the login page reads to display
 * the appropriate button(s) — but BOTH providers always work for
 * login if the photographer has linked them.
 *
 * Service config (config/services.php) needs:
 *   google.client_id / client_secret / redirect
 *   line.client_id   / client_secret / redirect
 * For LINE, set redirect to /photographer/auth/line/callback.
 */
class SocialAuthController extends Controller
{
    /** Whitelist of allowed providers — both `name = google|line`. */
    private const PROVIDERS = ['google', 'line'];

    public function redirect(string $provider): RedirectResponse
    {
        if (!in_array($provider, self::PROVIDERS, true)) {
            return redirect()->route('photographer.login')
                ->with('error', 'ผู้ให้บริการ OAuth ไม่รองรับ');
        }

        $this->hydrateConfig($provider);

        // Save the "intent" so the callback knows whether this is a
        // login flow or a link-existing-account flow.
        session()->put('photographer_oauth_intent', Auth::check() ? 'link' : 'login');

        // LINE has no Socialite provider in this codebase — hand-roll
        // the OAuth 2.0 redirect ourselves. Google goes through
        // Socialite (supported out of the box).
        if ($provider === 'line') {
            return $this->redirectToLine();
        }

        try {
            return Socialite::driver($provider)
                ->scopes($this->scopesFor($provider))
                ->redirect();
        } catch (\Throwable $e) {
            Log::warning('photographer.oauth.redirect_failed', [
                'provider' => $provider,
                'err'      => $e->getMessage(),
            ]);
            return redirect()->route('photographer.login')
                ->with('error', "เชื่อมต่อ {$provider} ไม่สำเร็จ — ตรวจสอบการตั้งค่า OAuth");
        }
    }

    /**
     * Hand-rolled LINE Login redirect. LINE's OAuth 2.0 endpoint:
     *   https://access.line.me/oauth2/v2.1/authorize
     * Required params: response_type=code, client_id, redirect_uri,
     *   state (CSRF), scope (openid profile email).
     */
    private function redirectToLine(): RedirectResponse
    {
        $clientId = AppSetting::get('line_login_channel_id');
        if (!$clientId) {
            return redirect()->route('photographer.login')
                ->with('error', 'ยังไม่ได้ตั้งค่า LINE Login (admin: line_login_channel_id)');
        }

        $state = bin2hex(random_bytes(16));
        session()->put('photographer_line_oauth_state', $state);

        $params = http_build_query([
            'response_type' => 'code',
            'client_id'     => $clientId,
            'redirect_uri'  => route('photographer.auth.callback', ['provider' => 'line']),
            'state'         => $state,
            'scope'         => 'profile openid email',
        ]);

        return redirect()->away('https://access.line.me/oauth2/v2.1/authorize?' . $params);
    }

    /**
     * Hydrate Socialite config keys from app_settings at runtime.
     * Mirrors the pattern in Public\AuthController::googleCreds().
     *
     * Settings keys (managed in /admin/settings):
     *   - google_client_id / google_client_secret
     *   - line_login_channel_id / line_login_channel_secret
     */
    private function hydrateConfig(string $provider): void
    {
        if ($provider === 'google') {
            config([
                'services.google.client_id'     => AppSetting::get('google_client_id'),
                'services.google.client_secret' => AppSetting::get('google_client_secret'),
                'services.google.redirect'      => route('photographer.auth.callback', ['provider' => 'google']),
            ]);
        } elseif ($provider === 'line') {
            // LINE Login (NOT messaging — separate channel in LINE Developer console).
            config([
                'services.line.client_id'     => AppSetting::get('line_login_channel_id'),
                'services.line.client_secret' => AppSetting::get('line_login_channel_secret'),
                'services.line.redirect'      => route('photographer.auth.callback', ['provider' => 'line']),
            ]);
        }
    }

    public function callback(string $provider, \Illuminate\Http\Request $request): RedirectResponse
    {
        if (!in_array($provider, self::PROVIDERS, true)) {
            return redirect()->route('photographer.login');
        }

        $this->hydrateConfig($provider);

        // Resolve the OAuth user identity. LINE goes through the
        // hand-rolled handler; Google through Socialite.
        try {
            if ($provider === 'line') {
                $oauthData = $this->handleLineCallback($request);
                if (!$oauthData) {
                    return redirect()->route('photographer.login')
                        ->with('error', 'เข้าสู่ระบบผ่าน LINE ไม่สำเร็จ');
                }
                $providerUid = (string) $oauthData['id'];
                $email       = $oauthData['email'] ?? null;
                $name        = $oauthData['name'] ?? 'LINE User';
                $avatar      = $oauthData['avatar'] ?? null;
            } else {
                $oauthUser = Socialite::driver($provider)->user();
                $providerUid = (string) $oauthUser->getId();
                $email       = $oauthUser->getEmail();
                $name        = $oauthUser->getName() ?: $oauthUser->getNickname() ?: 'OAuth User';
                $avatar      = $oauthUser->getAvatar();
            }
        } catch (\Throwable $e) {
            Log::warning('photographer.oauth.callback_failed', [
                'provider' => $provider,
                'err'      => $e->getMessage(),
            ]);
            return redirect()->route('photographer.login')
                ->with('error', "เข้าสู่ระบบผ่าน {$provider} ไม่สำเร็จ");
        }
        $intent      = session()->pull('photographer_oauth_intent', 'login');

        // ── Existing link? Log them in. ───────────────────────────────
        $existing = SocialLogin::where('provider', $provider)
            ->where('provider_id', $providerUid)
            ->first();

        if ($existing) {
            $user = User::find($existing->user_id);
            if (!$user) {
                return redirect()->route('photographer.login')
                    ->with('error', 'บัญชีที่เชื่อมไว้ถูกลบแล้ว — โปรดสมัครใหม่');
            }
            Auth::login($user, true);
            $user->update(['last_login_at' => now()]);

            $profile = PhotographerProfile::where('user_id', $user->id)->first();
            if (!$profile) {
                return redirect()->route('photographer.register')
                    ->with('success', 'เข้าสู่ระบบผ่าน ' . strtoupper($provider) . ' สำเร็จ');
            }
            // Photographer must have BOTH LINE + Google linked. Send to the
            // connect-google gate if Google still missing.
            return redirect()->to($this->postLoginDestination($user->id))
                ->with('success', 'เข้าสู่ระบบผ่าน ' . strtoupper($provider) . ' สำเร็จ');
        }

        // ── Link mode (logged-in user wants to add this provider) ────
        if ($intent === 'link' && Auth::check()) {
            SocialLogin::create([
                'user_id'     => Auth::id(),
                'provider'    => $provider,
                'provider_id' => $providerUid,
                'avatar'      => $avatar,
            ]);
            return redirect()->route('photographer.profile')
                ->with('success', 'เชื่อมต่อบัญชี ' . strtoupper($provider) . ' เรียบร้อย');
        }

        // ── No existing link + no logged-in user → register flow ─────
        // Email may be missing for LINE (LINE only returns email if the
        // user explicitly granted email scope). In that case we mint a
        // synthetic email so the User row is valid; admin can edit later.
        if (!$email) {
            $email = strtolower($provider) . '+' . substr($providerUid, 0, 12) . '@oauth.local';
        }

        // Existing user with this email? Auto-link instead of duplicating.
        $user = User::where('email', $email)->first();

        $createdNew = false;
        if (!$user) {
            // Brand-new photographer — create both User + Profile.
            $user = DB::transaction(function () use ($email, $name, $avatar, $provider, $providerUid) {
                $u = User::create([
                    'first_name'    => $name,
                    'last_name'     => '',
                    'email'         => $email,
                    'password_hash' => bcrypt(\Illuminate\Support\Str::random(32)), // unguessable; user uses OAuth
                    'status'        => 'active',
                    // No need for email_verified_at — OAuth provider already verified.
                    'email_verified_at' => now(),
                    'auth_provider' => $provider,
                ]);

                // Spawn a photographer profile in pending status so admin
                // can review. The display_name comes from the OAuth name.
                PhotographerProfile::create([
                    'user_id'      => $u->id,
                    'display_name' => $name,
                    'status'       => 'pending',
                ]);

                SocialLogin::create([
                    'user_id'     => $u->id,
                    'provider'    => $provider,
                    'provider_id' => $providerUid,
                    'avatar'      => $avatar,
                ]);

                return $u;
            });
            $createdNew = true;
        } else {
            // User exists — just attach this provider as a new link.
            SocialLogin::create([
                'user_id'     => $user->id,
                'provider'    => $provider,
                'provider_id' => $providerUid,
                'avatar'      => $avatar,
            ]);
        }

        Auth::login($user, true);
        $user->update(['last_login_at' => now()]);

        // Admin notification for fresh signups
        if ($createdNew) {
            try {
                \App\Models\AdminNotification::newPhotographer(
                    PhotographerProfile::where('user_id', $user->id)->first()
                );
            } catch (\Throwable $e) {}
        }

        // First-time photographer login — route through the Google-linking
        // gate so they hit the "Connect Google" page if not yet linked.
        return redirect()->to($this->postLoginDestination($user->id))
            ->with('success', 'ยินดีต้อนรับสู่ระบบช่างภาพ — เข้าสู่ระบบผ่าน ' . strtoupper($provider));
    }

    /**
     * Where should we send the photographer after a successful OAuth login?
     *
     *   • No Google linked yet AND admin hasn't disabled the requirement
     *     → /photographer/connect-google (mandatory step)
     *   • Otherwise → /photographer/dashboard
     *
     * Centralising this here keeps the two "post-login" code paths in this
     * controller (existing-link vs new-signup) producing the same routing
     * decisions.
     */
    private function postLoginDestination(int $userId): string
    {
        $required = \App\Models\AppSetting::get('photographer_require_google_link', '1') === '1';
        if (!$required) {
            return route('photographer.dashboard');
        }
        $hasGoogle = DB::table('auth_social_logins')
            ->where('user_id', $userId)
            ->where('provider', 'google')
            ->exists();
        return $hasGoogle
            ? route('photographer.dashboard')
            : route('photographer.connect-google');
    }

    /**
     * Scopes per provider. Both ask for profile + email.
     * LINE specifically needs `profile email openid`.
     */
    private function scopesFor(string $provider): array
    {
        return match ($provider) {
            'line'   => ['profile', 'openid', 'email'],
            'google' => ['openid', 'profile', 'email'],
            default  => ['email'],
        };
    }

    /**
     * Exchange the LINE auth code for an access token, then fetch the
     * user profile + email. Returns array{id, name, email, avatar}
     * or null on any failure.
     *
     * LINE Login API:
     *   POST https://api.line.me/oauth2/v2.1/token         (token exchange)
     *   POST https://api.line.me/oauth2/v2.1/verify        (id_token verify)
     *   GET  https://api.line.me/v2/profile                (with access_token)
     *
     * Email is included as a claim in the id_token, NOT in /v2/profile.
     */
    private function handleLineCallback(\Illuminate\Http\Request $request): ?array
    {
        // CSRF state check.
        $state = $request->query('state');
        $expected = session()->pull('photographer_line_oauth_state');
        if (!$state || !$expected || !hash_equals($expected, $state)) {
            Log::warning('photographer.line.oauth.state_mismatch');
            return null;
        }

        $code = $request->query('code');
        if (!$code) return null;

        $clientId     = AppSetting::get('line_login_channel_id');
        $clientSecret = AppSetting::get('line_login_channel_secret');
        if (!$clientId || !$clientSecret) {
            Log::warning('photographer.line.oauth.creds_missing');
            return null;
        }

        // 1) Token exchange
        try {
            $tokenResp = \Illuminate\Support\Facades\Http::asForm()
                ->timeout(10)
                ->post('https://api.line.me/oauth2/v2.1/token', [
                    'grant_type'    => 'authorization_code',
                    'code'          => $code,
                    'redirect_uri'  => route('photographer.auth.callback', ['provider' => 'line']),
                    'client_id'     => $clientId,
                    'client_secret' => $clientSecret,
                ]);
            if (!$tokenResp->ok()) {
                // Don't log the raw body — it may contain token fragments or
                // PII fields. Log only the status + error_description from
                // the parsed JSON (LINE uses these standard OAuth fields).
                $j = $tokenResp->json() ?: [];
                Log::warning('photographer.line.oauth.token_exchange_failed', [
                    'status'            => $tokenResp->status(),
                    'error'             => $j['error']             ?? null,
                    'error_description' => $j['error_description'] ?? null,
                ]);
                return null;
            }
            $tokens = $tokenResp->json();
            $accessToken = $tokens['access_token'] ?? null;
            $idToken     = $tokens['id_token']    ?? null;
            if (!$accessToken) return null;
        } catch (\Throwable $e) {
            Log::warning('photographer.line.oauth.token_exception: '.$e->getMessage());
            return null;
        }

        // 2) Decode id_token (no signature verify here — LINE's
        //    `/oauth2/v2.1/verify` endpoint can do that, but the API
        //    has rate limits; we trust the token because it came over
        //    HTTPS from the token endpoint we just hit).
        $email = null;
        $name = null;
        if ($idToken) {
            $parts = explode('.', $idToken);
            if (count($parts) === 3) {
                $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
                if (is_array($payload)) {
                    $email = $payload['email'] ?? null;
                    $name  = $payload['name']  ?? null;
                }
            }
        }

        // 3) Profile fetch (gives us userId + display name + avatar URL)
        try {
            $profileResp = \Illuminate\Support\Facades\Http::withToken($accessToken)
                ->timeout(10)
                ->get('https://api.line.me/v2/profile');
            if (!$profileResp->ok()) {
                Log::warning('photographer.line.oauth.profile_failed');
                return null;
            }
            $profile = $profileResp->json();
        } catch (\Throwable $e) {
            Log::warning('photographer.line.oauth.profile_exception: '.$e->getMessage());
            return null;
        }

        return [
            'id'     => (string) ($profile['userId'] ?? ''),
            'name'   => $name ?: ($profile['displayName'] ?? 'LINE User'),
            'email'  => $email, // may be null if user didn't grant email
            'avatar' => $profile['pictureUrl'] ?? null,
        ];
    }
}

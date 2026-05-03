<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Services\Google\GoogleAnalyticsService;
use App\Services\Google\GoogleApiAuth;
use App\Services\Google\GoogleSearchConsoleService;
use Illuminate\Http\Request;

/**
 * Admin controller for the Google APIs settings page.
 *
 * One service account JSON shared across:
 *   • GA4 Data API (analytics.readonly scope)
 *   • Search Console API (webmasters.readonly scope)
 *   • Calendar API (already configured separately via API key)
 *
 * Each service has its own property/site identifier:
 *   • GA4 needs property_id (numeric, e.g. 123456789)
 *   • Search Console needs the site URL (e.g. "https://loadroop.com/")
 *
 * The settings page renders a 2-section card (one per service) with
 * its own test-connection button + status badge so admin can verify
 * each setup independently.
 */
class GoogleApiController extends Controller
{
    public function index()
    {
        $auth          = app(GoogleApiAuth::class);
        $ga            = app(GoogleAnalyticsService::class);
        $searchConsole = app(GoogleSearchConsoleService::class);
        $oauth         = app(\App\Services\Google\GoogleOAuthUserAuth::class);

        $serviceAccountEmail = $auth->serviceAccountEmail();

        // Don't echo back the full JSON for security — show only an
        // indicator that it's set + the email (which admin needs to
        // grant property access to).
        $hasJson = (bool) AppSetting::get('google_service_account_json', '');

        return view('admin.settings.google-apis', [
            'has_json'              => $hasJson,
            'service_account_email' => $serviceAccountEmail,
            'ga_property_id'        => AppSetting::get('google_analytics_property_id', ''),
            'sc_site_url'           => AppSetting::get('google_search_console_site_url', ''),
            'ga_configured'         => $ga->isConfigured(),
            'sc_configured'         => $searchConsole->isConfigured(),

            // OAuth user flow (Search Console workaround)
            'oauth_client_id'       => AppSetting::get('google_oauth_client_id', ''),
            'oauth_client_secret'   => AppSetting::get('google_oauth_client_secret', ''),
            'oauth_has_credentials' => $oauth->hasCredentials(),
            'oauth_is_connected'    => $oauth->isConnected(),
            'oauth_connected_email' => $oauth->connectedEmail(),
            'oauth_callback_url'    => route('admin.settings.google-apis.oauth-callback'),
        ]);
    }

    /**
     * Save service account JSON. Admin uploads the file from Cloud
     * Console; we validate it parses + has the right shape, then
     * store the raw JSON in app_settings (encrypted at rest by
     * Postgres column-level encryption, not application-level).
     *
     * Note: we don't encrypt the JSON in PHP because admin needs
     * read+write access to it. If the DB is compromised, the keys are
     * already gone — they should be rotated anyway. The bigger risk is
     * accidentally logging the raw JSON, which we guard against by
     * never printing it back to the response.
     */
    public function saveServiceAccount(Request $request)
    {
        $request->validate([
            'json_file'   => 'nullable|file|max:32',     // 32KB max — JSON is ~3KB
            'json_paste'  => 'nullable|string|max:32768',
        ]);

        $raw = '';
        if ($request->hasFile('json_file')) {
            $raw = file_get_contents($request->file('json_file')->getRealPath());
        } elseif ($request->filled('json_paste')) {
            $raw = $request->input('json_paste');
        }

        if ($raw === '') {
            return back()->with('error', 'กรุณาอัปโหลดไฟล์ JSON หรือวางเนื้อหา');
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return back()->with('error', 'ไฟล์ JSON อ่านไม่ได้ — ตรวจรูปแบบ');
        }

        $required = ['type', 'client_email', 'private_key'];
        foreach ($required as $field) {
            if (empty($decoded[$field])) {
                return back()->with('error', "JSON ไม่ครบ field — ขาด: {$field}");
            }
        }

        if (($decoded['type'] ?? '') !== 'service_account') {
            return back()->with('error', 'JSON ไม่ใช่ service account credentials (type ไม่ใช่ service_account)');
        }

        AppSetting::set('google_service_account_json', $raw);
        app(GoogleApiAuth::class)->bustCache();

        return back()->with('success',
            "✓ บันทึก Service Account แล้ว — email: {$decoded['client_email']}\n"
            . "อย่าลืมเพิ่ม email นี้ใน GA4 Property + Search Console เพื่อให้ access ได้");
    }

    /**
     * Remove the stored service account JSON entirely. Doesn't touch
     * property_id / site_url so admin can swap accounts without
     * re-entering those.
     */
    public function clearServiceAccount()
    {
        AppSetting::set('google_service_account_json', '');
        app(GoogleApiAuth::class)->bustCache();
        return back()->with('success', '✓ ลบ Service Account แล้ว');
    }

    public function saveGaPropertyId(Request $request)
    {
        $validated = $request->validate([
            'google_analytics_property_id' => 'nullable|string|max:30|regex:/^[0-9]*$/',
        ]);
        AppSetting::set('google_analytics_property_id', $validated['google_analytics_property_id'] ?? '');
        app(GoogleAnalyticsService::class)->bustAllCaches();

        $msg = empty($validated['google_analytics_property_id'])
            ? '✓ ลบ GA4 Property ID แล้ว'
            : '✓ บันทึก GA4 Property ID แล้ว — กดทดสอบเชื่อมต่อ';
        return back()->with('success', $msg);
    }

    public function saveScSiteUrl(Request $request)
    {
        $validated = $request->validate([
            'google_search_console_site_url' => 'nullable|string|max:200',
        ]);
        AppSetting::set('google_search_console_site_url', $validated['google_search_console_site_url'] ?? '');
        app(GoogleSearchConsoleService::class)->bustAllCaches();

        $msg = empty($validated['google_search_console_site_url'])
            ? '✓ ลบ Search Console site URL แล้ว'
            : '✓ บันทึก Search Console site URL แล้ว — กดทดสอบเชื่อมต่อ';
        return back()->with('success', $msg);
    }

    /** AJAX: test GA4 connection. Returns JSON for admin UI. */
    public function testGa()
    {
        return response()->json(app(GoogleAnalyticsService::class)->testConnection());
    }

    /** AJAX: test Search Console connection. */
    public function testSc()
    {
        return response()->json(app(GoogleSearchConsoleService::class)->testConnection());
    }

    /**
     * AJAX: realtime active users count for the admin header badge.
     * Polled every 60s by sidebar — returns just an integer to keep
     * the response tiny + cacheable.
     */
    public function realtimeUsers()
    {
        $count = app(GoogleAnalyticsService::class)->realtimeActiveUsers();
        return response()->json(['count' => $count]);
    }

    /* ─────────────────────────────────────────────────────────────────
     * OAuth User flow — workaround for Search Console UI rejecting
     * service account emails. Admin connects their own Google account
     * which already has owner perms on Search Console property.
     * ───────────────────────────────────────────────────────────────── */

    /**
     * Save OAuth client_id + secret. These come from Cloud Console →
     * APIs & Services → Credentials → OAuth 2.0 Client IDs.
     */
    public function saveOauthCredentials(Request $request)
    {
        $validated = $request->validate([
            'google_oauth_client_id'     => 'nullable|string|max:200',
            'google_oauth_client_secret' => 'nullable|string|max:200',
        ]);
        AppSetting::set('google_oauth_client_id',     $validated['google_oauth_client_id'] ?? '');
        AppSetting::set('google_oauth_client_secret', $validated['google_oauth_client_secret'] ?? '');

        return back()->with('success', '✓ บันทึก OAuth credentials แล้ว — กดปุ่ม "Connect with Google" เพื่อ authorize');
    }

    /**
     * Start OAuth flow — redirect admin to Google consent screen.
     */
    public function oauthConnect()
    {
        $oauth = app(\App\Services\Google\GoogleOAuthUserAuth::class);

        if (!$oauth->hasCredentials()) {
            return back()->with('error', 'กรุณาบันทึก OAuth Client ID + Secret ก่อน');
        }

        $callbackUrl = route('admin.settings.google-apis.oauth-callback');
        $authUrl = $oauth->buildAuthUrl(
            ['https://www.googleapis.com/auth/webmasters.readonly'],
            $callbackUrl
        );

        return redirect()->away($authUrl);
    }

    /**
     * OAuth callback — Google redirects here with ?code=...
     * Exchange code for refresh_token and store.
     */
    public function oauthCallback(Request $request)
    {
        $code  = $request->query('code');
        $state = $request->query('state');
        $error = $request->query('error');

        if ($error) {
            return redirect()->route('admin.settings.google-apis.index')
                ->with('error', 'OAuth ถูกปฏิเสธ: ' . $error);
        }

        if (!$code || !$state) {
            return redirect()->route('admin.settings.google-apis.index')
                ->with('error', 'OAuth callback ขาด code หรือ state');
        }

        try {
            $callbackUrl = route('admin.settings.google-apis.oauth-callback');
            $result = app(\App\Services\Google\GoogleOAuthUserAuth::class)
                ->handleCallback($code, $state, $callbackUrl);

            return redirect()->route('admin.settings.google-apis.index')
                ->with('success', "✓ Connect Google สำเร็จ — connected as {$result['email']}");
        } catch (\Throwable $e) {
            return redirect()->route('admin.settings.google-apis.index')
                ->with('error', 'OAuth callback failed: ' . $e->getMessage());
        }
    }

    /**
     * Disconnect — revoke at Google + clear stored token.
     */
    public function oauthDisconnect()
    {
        app(\App\Services\Google\GoogleOAuthUserAuth::class)->disconnect();
        app(GoogleSearchConsoleService::class)->bustAllCaches();
        return back()->with('success', '✓ Disconnect Google account แล้ว');
    }
}

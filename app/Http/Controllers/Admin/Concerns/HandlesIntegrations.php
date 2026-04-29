<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Models\AppSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * THIRD-PARTY INTEGRATIONS (AWS / Cloudflare / Analytics / Google Drive / Payment Gateways)
 *
 * Extracted from SettingsController. Method signatures and route names
 * unchanged — trait is `use`d by the parent controller.
 *
 * Routes touched:
 *   • admin.settings.aws                — aws()
 *   • admin.settings.aws.update         — updateAws()
 *   • admin.settings.cloudflare         — cloudflare()
 *   • admin.settings.cloudflare.update  — updateCloudflare()
 *   • admin.settings.analytics          — analytics()
 *   • admin.settings.analytics.update   — updateAnalytics()
 *   • admin.settings.google-drive        — googleDrive()
 *   • admin.settings.google-drive.update — updateGoogleDrive()
 *   • admin.settings.payment-gateways        — paymentGateways()
 *   • admin.settings.payment-gateways.update — updatePaymentGateways()
 */
trait HandlesIntegrations
{
    // ─────────────────────────────────────────────────────────────
    // AWS (S3 + CloudFront + SES)
    // ─────────────────────────────────────────────────────────────

    public function aws()
    {
        $keys = [
            'aws_access_key_id', 'aws_secret_access_key', 'aws_default_region',
            'aws_s3_bucket', 'aws_s3_url', 'aws_s3_path_style', 'aws_s3_folder_prefix', 'aws_s3_default_visibility',
            'aws_cloudfront_enabled', 'aws_cloudfront_distribution_id', 'aws_cloudfront_domain',
            'aws_cloudfront_key_pair_id', 'aws_cloudfront_private_key',
            'aws_cloudfront_signed_urls', 'aws_cloudfront_signed_url_expiry',
            'aws_ses_enabled', 'aws_ses_region', 'aws_ses_from_email', 'aws_ses_from_name',
        ];

        $all = AppSetting::getAll();
        $settings = [];
        foreach ($keys as $key) {
            $settings[$key] = $all[$key] ?? '';
        }

        return view('admin.settings.aws', compact('settings'));
    }

    public function updateAws(Request $request)
    {
        $checkboxKeys = [
            'aws_s3_path_style', 'aws_cloudfront_enabled',
            'aws_cloudfront_signed_urls', 'aws_ses_enabled',
        ];

        $allKeys = [
            'aws_access_key_id', 'aws_secret_access_key', 'aws_default_region',
            'aws_s3_bucket', 'aws_s3_url', 'aws_s3_folder_prefix', 'aws_s3_default_visibility',
            'aws_cloudfront_distribution_id', 'aws_cloudfront_domain',
            'aws_cloudfront_key_pair_id', 'aws_cloudfront_private_key',
            'aws_cloudfront_signed_url_expiry',
            'aws_ses_region', 'aws_ses_from_email', 'aws_ses_from_name',
        ];

        $items = [];

        foreach ($checkboxKeys as $key) {
            $items[$key] = $request->has($key) ? '1' : '0';
        }

        foreach ($allKeys as $key) {
            if ($request->has($key)) {
                $value = $request->input($key, '');
                // Don't overwrite secrets with empty string if field was left blank
                if (in_array($key, ['aws_secret_access_key', 'aws_cloudfront_private_key'], true) && empty($value)) {
                    continue;
                }
                $items[$key] = $value;
            }
        }

        AppSetting::setMany($items);

        return back()->with('success', 'บันทึกการตั้งค่า AWS สำเร็จ');
    }

    // ─────────────────────────────────────────────────────────────
    // Cloudflare (CDN + Zone management + R2 object storage)
    // ─────────────────────────────────────────────────────────────

    public function cloudflare()
    {
        $all        = AppSetting::getAll();
        $apiToken   = $all['cloudflare_api_token']   ?? '';
        $zoneId     = $all['cloudflare_zone_id']     ?? '';
        $cdnEnabled = $all['cloudflare_cdn_enabled'] ?? '0';

        // Mask token for display: show first 6 chars + asterisks
        $maskedToken = '';
        if ($apiToken !== '') {
            $maskedToken = substr($apiToken, 0, 6) . str_repeat('*', max(0, strlen($apiToken) - 6));
        }

        $isConfigured = ($apiToken !== '' && $zoneId !== '');

        // Try fetching zone info from Cloudflare API
        $zoneInfo = null;
        if ($isConfigured) {
            try {
                $ch = curl_init("https://api.cloudflare.com/client/v4/zones/{$zoneId}");
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HTTPHEADER     => [
                        "Authorization: Bearer {$apiToken}",
                        'Content-Type: application/json',
                    ],
                    CURLOPT_TIMEOUT        => 5,
                ]);
                $response = curl_exec($ch);
                curl_close($ch);

                if ($response) {
                    $decoded = json_decode($response, true);
                    if (!empty($decoded['success'])) {
                        $zoneInfo = $decoded['result'] ?? null;
                    }
                }
            } catch (\Throwable $e) {
                // API unreachable — leave $zoneInfo null
            }
        }

        $settings = [
            'cloudflare_api_token'   => $maskedToken,
            'cloudflare_zone_id'     => $zoneId,
            'cloudflare_cdn_enabled' => $cdnEnabled,
        ];

        // R2 Storage settings
        $r2Keys = [
            'r2_enabled', 'r2_access_key_id', 'r2_secret_access_key',
            'r2_bucket', 'r2_endpoint', 'r2_public_url', 'r2_custom_domain',
        ];
        foreach ($r2Keys as $key) {
            $settings[$key] = $all[$key] ?? '';
        }
        // Mask R2 secret for display
        $r2Secret = $settings['r2_secret_access_key'];
        $settings['r2_secret_masked'] = ($r2Secret !== '')
            ? substr($r2Secret, 0, 6) . str_repeat('*', max(0, strlen($r2Secret) - 6))
            : '';

        $r2Configured = !empty($settings['r2_access_key_id'])
            && !empty($r2Secret)
            && !empty($settings['r2_bucket'])
            && !empty($settings['r2_endpoint']);

        return view('admin.settings.cloudflare', compact('settings', 'isConfigured', 'zoneInfo', 'r2Configured'));
    }

    public function updateCloudflare(Request $request)
    {
        // Handle R2 test connection (AJAX)
        if ($request->input('action') === 'test_r2') {
            try {
                $r2 = app(\App\Services\Cloudflare\R2StorageService::class);
                $testKey = '.r2-test-' . time();
                Storage::disk('r2')->put($testKey, 'ok');
                Storage::disk('r2')->delete($testKey);
                return response()->json(['success' => true, 'message' => 'R2 connection successful! Bucket is accessible.']);
            } catch (\Throwable $e) {
                return response()->json(['success' => false, 'message' => $e->getMessage()]);
            }
        }

        // Handle purge-all cache action
        if ($request->input('action') === 'purge_all') {
            $apiToken = AppSetting::get('cloudflare_api_token', '');
            $zoneId   = AppSetting::get('cloudflare_zone_id', '');

            if ($apiToken && $zoneId) {
                try {
                    $ch = curl_init("https://api.cloudflare.com/client/v4/zones/{$zoneId}/purge_cache");
                    curl_setopt_array($ch, [
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_POST           => true,
                        CURLOPT_POSTFIELDS     => json_encode(['purge_everything' => true]),
                        CURLOPT_HTTPHEADER     => [
                            "Authorization: Bearer {$apiToken}",
                            'Content-Type: application/json',
                        ],
                        CURLOPT_TIMEOUT        => 10,
                    ]);
                    $response = curl_exec($ch);
                    curl_close($ch);

                    $decoded = json_decode($response, true);
                    if (!empty($decoded['success'])) {
                        return back()->with('success', 'Cloudflare cache purged successfully.');
                    }
                    return back()->with('error', 'Cache purge failed: ' . ($decoded['errors'][0]['message'] ?? 'Unknown error'));
                } catch (\Throwable $e) {
                    return back()->with('error', 'Cache purge failed: ' . $e->getMessage());
                }
            }

            return back()->with('error', 'Cloudflare is not configured.');
        }

        $items = [];

        // Save API token only if a new (non-masked) value was supplied
        $newToken = $request->input('cloudflare_api_token', '');
        if ($newToken !== '' && !str_contains($newToken, '***')) {
            $items['cloudflare_api_token'] = $newToken;
        }

        $items['cloudflare_zone_id']     = $request->input('cloudflare_zone_id', '');
        $items['cloudflare_cdn_enabled'] = $request->has('cloudflare_cdn_enabled') ? '1' : '0';

        // ── R2 Storage settings ──
        if ($request->has('r2_section')) {
            $items['r2_enabled']        = $request->has('r2_enabled') ? '1' : '0';
            $items['r2_access_key_id']  = $request->input('r2_access_key_id', '');
            $items['r2_bucket']         = $request->input('r2_bucket', '');
            $items['r2_endpoint']       = $request->input('r2_endpoint', '');
            $items['r2_public_url']     = $request->input('r2_public_url', '');
            $items['r2_custom_domain']  = $request->input('r2_custom_domain', '');

            // Only overwrite secret if a new (non-masked) value was provided
            $r2Secret = $request->input('r2_secret_access_key', '');
            if ($r2Secret !== '' && !str_contains($r2Secret, '***')) {
                $items['r2_secret_access_key'] = $r2Secret;
            }
        }

        AppSetting::setMany($items);

        return back()->with('success', 'Cloudflare settings saved.');
    }

    // ─────────────────────────────────────────────────────────────
    // Analytics & Social (GA4 + FB Pixel + OG defaults)
    // ─────────────────────────────────────────────────────────────

    public function analytics()
    {
        $keys = [
            'ga4_enabled', 'ga4_measurement_id',
            'fb_pixel_enabled', 'fb_pixel_id',
            'og_default_image', 'og_site_description', 'og_fb_app_id', 'og_twitter_card_type',
        ];

        $all      = AppSetting::getAll();
        $settings = [];
        foreach ($keys as $key) {
            $settings[$key] = $all[$key] ?? '';
        }

        return view('admin.settings.analytics', compact('settings'));
    }

    public function updateAnalytics(Request $request)
    {
        $checkboxKeys = ['ga4_enabled', 'fb_pixel_enabled'];

        $allKeys = [
            'ga4_measurement_id',
            'fb_pixel_id',
            'og_default_image', 'og_site_description', 'og_fb_app_id', 'og_twitter_card_type',
        ];

        $items = [];

        foreach ($checkboxKeys as $key) {
            $items[$key] = $request->has($key) ? '1' : '0';
        }

        foreach ($allKeys as $key) {
            if ($request->has($key)) {
                $items[$key] = $request->input($key, '');
            }
        }

        AppSetting::setMany($items);

        return back()->with('success', 'บันทึกการตั้งค่า Analytics สำเร็จ');
    }

    // ─────────────────────────────────────────────────────────────
    // Google Drive (OAuth + Service Account + sync queue)
    // ─────────────────────────────────────────────────────────────

    public function googleDrive()
    {
        $keys = [
            'google_drive_api_key', 'google_client_id', 'google_client_secret',
            'queue_auto_sync', 'queue_sync_interval_minutes', 'queue_max_retries',
            'perf_api_max_files', 'perf_browser_cache_maxage', 'perf_stale_revalidate',
            'perf_lock_timeout', 'perf_cache_grace_hours',
        ];

        $all      = AppSetting::getAll();
        $settings = [];
        foreach ($keys as $key) {
            $settings[$key] = $all[$key] ?? '';
        }

        // Events with Drive folders for the sync dropdown
        $events = DB::table('event_events')
            ->whereNotNull('drive_folder_id')
            ->where('drive_folder_id', '!=', '')
            ->select('id', 'name', 'drive_folder_id')
            ->orderByDesc('id')
            ->get();

        // Queue stats
        $queueStats = [
            'pending'    => DB::table('sync_queue')->where('status', 'pending')->count(),
            'processing' => DB::table('sync_queue')->where('status', 'processing')->count(),
            'completed'  => DB::table('sync_queue')->where('status', 'completed')->count(),
            'failed'     => DB::table('sync_queue')->where('status', 'failed')->count(),
        ];

        // Recent jobs
        $recentJobs = DB::table('sync_queue')
            ->leftJoin('event_events', 'sync_queue.event_id', '=', 'event_events.id')
            ->select('sync_queue.*', 'event_events.name as event_name')
            ->orderByDesc('sync_queue.created_at')
            ->limit(20)
            ->get();

        return view('admin.settings.google-drive', compact('settings', 'events', 'queueStats', 'recentJobs'));
    }

    public function updateGoogleDrive(Request $request)
    {
        $checkboxKeys = ['queue_auto_sync'];

        $allKeys = [
            'google_drive_api_key', 'google_client_id', 'google_client_secret',
            'queue_sync_interval_minutes', 'queue_max_retries',
            'perf_api_max_files', 'perf_browser_cache_maxage', 'perf_stale_revalidate',
            'perf_lock_timeout', 'perf_cache_grace_hours',
        ];

        $items = [];

        foreach ($checkboxKeys as $key) {
            $items[$key] = $request->has($key) ? '1' : '0';
        }

        foreach ($allKeys as $key) {
            if ($request->has($key)) {
                $value = $request->input($key, '');
                if ($key === 'google_client_secret' && empty($value)) {
                    continue;
                }
                $items[$key] = $value;
            }
        }

        if (!empty($items)) {
            AppSetting::setMany($items);
        }

        // Handle Service Account JSON file upload
        if ($request->hasFile('service_account_json')) {
            $file    = $request->file('service_account_json');
            $content = file_get_contents($file->getRealPath());
            $parsed  = json_decode($content, true);

            if ($parsed && !empty($parsed['client_email']) && !empty($parsed['private_key'])) {
                AppSetting::setMany([
                    'google_service_account_json'  => $content,
                    'google_service_account_email' => $parsed['client_email'],
                ]);
                // Invalidate cached token so next call uses new credentials
                Cache::forget('google_sa_access_token');
                return back()->with('success', 'อัปโหลด Service Account สำเร็จ — ' . $parsed['client_email']);
            } else {
                return back()->with('error', 'ไฟล์ JSON ไม่ถูกต้อง — ต้องมี client_email และ private_key');
            }
        }

        // Remove Service Account if requested
        if ($request->input('remove_service_account') === '1') {
            AppSetting::setMany([
                'google_service_account_json'  => '',
                'google_service_account_email' => '',
            ]);
            Cache::forget('google_sa_access_token');
            return back()->with('success', 'ลบ Service Account แล้ว');
        }

        return back()->with('success', 'บันทึกการตั้งค่า Google Drive สำเร็จ');
    }

    // ─────────────────────────────────────────────────────────────
    // Payment Gateways (Stripe / Omise / PayPal / LINE Pay / PromptPay / TrueMoney / 2C2P)
    // ─────────────────────────────────────────────────────────────

    public function paymentGateways()
    {
        $keys = [
            // Stripe
            'stripe_enabled', 'stripe_public_key', 'stripe_secret_key', 'stripe_webhook_secret', 'stripe_sandbox',
            // Omise — webhook_secret verifies charge/refund webhooks (HMAC-SHA256
            // of raw body against X-Omise-Key-Hash header). Shared across both
            // the charge endpoint (/api/webhooks/omise) and the transfer
            // settlement endpoint (/api/webhooks/omise-transfer).
            'omise_enabled', 'omise_public_key', 'omise_secret_key', 'omise_webhook_secret', 'omise_sandbox',
            // PayPal
            'paypal_enabled', 'paypal_client_id', 'paypal_secret', 'paypal_sandbox',
            // LINE Pay
            'line_pay_enabled', 'line_pay_channel_id', 'line_pay_channel_secret', 'line_pay_sandbox',
            // PromptPay
            'promptpay_enabled', 'promptpay_number', 'promptpay_name',
            // TrueMoney
            'truemoney_enabled', 'truemoney_merchant_id', 'truemoney_secret', 'truemoney_sandbox',
            // 2C2P
            '2c2p_enabled', '2c2p_merchant_id', '2c2p_secret_key', '2c2p_sandbox',
        ];

        $all      = AppSetting::getAll();
        $settings = [];
        foreach ($keys as $key) {
            $settings[$key] = $all[$key] ?? '';
        }

        return view('admin.settings.payment-gateways', compact('settings'));
    }

    public function updatePaymentGateways(Request $request)
    {
        $toggleKeys = [
            'stripe_enabled', 'stripe_sandbox',
            'omise_enabled', 'omise_sandbox',
            'paypal_enabled', 'paypal_sandbox',
            'line_pay_enabled', 'line_pay_sandbox',
            'promptpay_enabled',
            'truemoney_enabled', 'truemoney_sandbox',
            '2c2p_enabled', '2c2p_sandbox',
        ];

        $secretKeys = [
            'stripe_secret_key', 'stripe_webhook_secret',
            'omise_secret_key', 'omise_webhook_secret',
            'paypal_secret',
            'line_pay_channel_secret',
            'truemoney_secret',
            '2c2p_secret_key',
        ];

        $textKeys = [
            'stripe_public_key',
            'omise_public_key',
            'paypal_client_id',
            'line_pay_channel_id',
            'promptpay_number', 'promptpay_name',
            'truemoney_merchant_id',
            '2c2p_merchant_id',
        ];

        $items = [];

        // Save toggles (checkbox → '1' or '0')
        foreach ($toggleKeys as $key) {
            $items[$key] = $request->has($key) ? '1' : '0';
        }

        // Save text fields
        foreach ($textKeys as $key) {
            if ($request->has($key)) {
                $items[$key] = $request->input($key, '');
            }
        }

        // Save secret fields — skip empty to preserve existing value
        foreach ($secretKeys as $key) {
            $value = $request->input($key, '');
            if (!empty($value)) {
                $items[$key] = $value;
            }
        }

        AppSetting::setMany($items);

        return back()->with('success', 'บันทึกการตั้งค่า Payment Gateways สำเร็จ');
    }
}

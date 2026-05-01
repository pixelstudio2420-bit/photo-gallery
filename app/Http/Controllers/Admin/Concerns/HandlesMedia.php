<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Models\AppSetting;
use App\Services\Media\Exceptions\InvalidMediaFileException;
use App\Services\Media\R2MediaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * MEDIA + LOCALIZATION (SEO / Watermark / Image pipeline / Language / LINE / Webhooks monitor)
 *
 * Extracted from SettingsController. Method signatures and route names
 * unchanged — trait is `use`d by the parent controller.
 *
 * Routes touched:
 *   • admin.settings.seo             — seo()
 *   • admin.settings.seo.update      — updateSeo()
 *   • admin.settings.watermark        — watermark()
 *   • admin.settings.watermark.update — updateWatermark()
 *   • admin.settings.image            — image()
 *   • admin.settings.image.update     — updateImage()
 *   • admin.settings.language         — language()
 *   • admin.settings.language.update  — updateLanguage()
 *   • admin.settings.line             — line()
 *   • admin.settings.line.update      — updateLine()
 *   • admin.settings.webhooks         — webhooks()
 */
trait HandlesMedia
{
    // ─────────────────────────────────────────────────────────────
    // SEO (site-wide meta + OG + favicon)
    // ─────────────────────────────────────────────────────────────

    public function seo()
    {
        $settings = collect(AppSetting::getAll())
            ->filter(fn($_, $key) => str_starts_with($key, 'seo_'))
            ->all();
        return view('admin.settings.seo', compact('settings'));
    }

    public function updateSeo(Request $request)
    {
        $data = $request->except(['_token', '_method']);

        // SEO assets (OG image, favicon) are platform-wide singletons —
        // stored under the reserved system user_id so the schema stays
        // regular and the GDPR delete-by-user sweep skips them naturally.
        $media = app(R2MediaService::class);

        // Handle OG default image upload.
        if ($request->hasFile('seo_og_default_image')) {
            $oldOg = (string) AppSetting::get('seo_og_default_image', '');
            if ($oldOg) {
                try { $media->delete($oldOg); } catch (\Throwable) {}
            }
            try {
                $upload = $media->uploadSystemSeoOg($request->file('seo_og_default_image'));
                $data['seo_og_default_image'] = $upload->key;
            } catch (InvalidMediaFileException $e) {
                return back()->withInput()->withErrors(['seo_og_default_image' => $e->getMessage()]);
            }
        } else {
            unset($data['seo_og_default_image']);
        }

        // Handle favicon upload.
        if ($request->hasFile('seo_favicon')) {
            $oldFav = (string) AppSetting::get('seo_favicon', '');
            if ($oldFav) {
                try { $media->delete($oldFav); } catch (\Throwable) {}
            }
            try {
                $upload = $media->uploadSystemFavicon($request->file('seo_favicon'));
                $data['seo_favicon'] = $upload->key;
            } catch (InvalidMediaFileException $e) {
                return back()->withInput()->withErrors(['seo_favicon' => $e->getMessage()]);
            }
        } else {
            unset($data['seo_favicon']);
        }

        AppSetting::setMany($data);

        // Clear cached SEO settings
        Cache::forget('seo_settings_all');

        return back()->with('success', 'SEO settings saved successfully.');
    }

    // ─────────────────────────────────────────────────────────────
    // Watermark (overlay config for protected photo views)
    // ─────────────────────────────────────────────────────────────

    public function watermark()
    {
        $keys = [
            'watermark_enabled', 'watermark_type', 'watermark_text',
            'watermark_opacity', 'watermark_color', 'watermark_position',
            'watermark_size_percent', 'watermark_image_path',
            // Tile-spacing controls how far apart repeated watermark
            // copies are when position = 'tiled'. 40-200, default 100.
            'watermark_tile_spacing',
        ];

        $all      = AppSetting::getAll();
        $settings = [];
        foreach ($keys as $key) {
            $settings[$key] = $all[$key] ?? '';
        }

        return view('admin.settings.watermark', compact('settings'));
    }

    public function updateWatermark(Request $request)
    {
        $data = $request->except(['_token', '_method']);

        // Watermark image (the platform "logo" tiled / diagonal-overlaid on
        // protected previews) is a singleton. On replace we:
        //   1. Delete the previous R2 object (CDN cache purged async).
        //   2. Upload the replacement under the canonical schema:
        //      `system/watermark/user_0/{uuid}_{name}.{ext}`.
        //   WatermarkService already reads through R2MediaService::url(),
        //   so the new path is picked up automatically.
        $media = app(R2MediaService::class);

        if ($request->hasFile('watermark_image_path')) {
            $oldWm = (string) AppSetting::get('watermark_image_path', '');
            if ($oldWm) {
                try { $media->delete($oldWm); } catch (\Throwable) {}
            }
            try {
                $upload = $media->uploadSystemWatermark($request->file('watermark_image_path'));
                $data['watermark_image_path'] = $upload->key;
            } catch (InvalidMediaFileException $e) {
                return back()->withInput()->withErrors(['watermark_image_path' => $e->getMessage()]);
            }
        } else {
            unset($data['watermark_image_path']);
        }

        // Handle checkbox (unchecked = not in request)
        $data['watermark_enabled'] = $request->has('watermark_enabled') ? '1' : '0';

        AppSetting::setMany($data);

        // Flush WatermarkService's in-memory cache so the new image is
        // picked up immediately by the next photo processed — otherwise
        // the admin would see no visible change until the PHP-FPM worker
        // recycled.
        try {
            \App\Services\WatermarkService::flushCache();
        } catch (\Throwable) {
            // Service may not expose flushCache in older installs — ignore.
        }

        return back()->with('success', 'Watermark settings saved successfully.');
    }

    // ─────────────────────────────────────────────────────────────
    // Image processing pipeline (GD-based WebP/AVIF conversion)
    // ─────────────────────────────────────────────────────────────

    public function image()
    {
        $contexts = ['cover', 'avatar', 'slip', 'seo'];
        $keys = ['img_processing_enabled'];
        foreach ($contexts as $ctx) {
            $keys[] = "img_{$ctx}_enabled";
            $keys[] = "img_{$ctx}_format";
            $keys[] = "img_{$ctx}_quality";
            $keys[] = "img_{$ctx}_max_width";
            $keys[] = "img_{$ctx}_max_height";
        }

        $all      = AppSetting::getAll();
        $settings = [];
        foreach ($keys as $key) {
            $settings[$key] = $all[$key] ?? '';
        }

        $gdAvailable = extension_loaded('gd');
        $gdInfo      = $gdAvailable ? gd_info() : [];

        return view('admin.settings.image', compact('settings', 'gdAvailable', 'gdInfo'));
    }

    public function updateImage(Request $request)
    {
        $data = $request->except(['_token', '_method']);

        // Handle checkboxes
        $checkboxKeys = ['img_processing_enabled', 'img_cover_enabled', 'img_avatar_enabled', 'img_slip_enabled', 'img_seo_enabled'];
        foreach ($checkboxKeys as $key) {
            $data[$key] = $request->has($key) ? '1' : '0';
        }

        AppSetting::setMany($data);

        return back()->with('success', 'Image processing settings saved successfully.');
    }

    // ─────────────────────────────────────────────────────────────
    // Photo Performance (event gallery — compression, thumbs, eager)
    // ─────────────────────────────────────────────────────────────
    //
    // Controls the upload → process → deliver pipeline for *event* photos
    // (separate from the general img_* pipeline above which covers
    // cover/avatar/slip/seo assets). Exposes a single page where admins can:
    //   • Toggle server-side re-encoding of originals (quality/max-dim)
    //   • Tune thumbnail + preview sizes
    //   • Toggle client-side (browser) compression before upload
    //   • Control how many images the public gallery eager-loads
    //
    // All keys read with a safe default so the page works even before the
    // admin has saved once. Keys all prefixed `photo_` to avoid collision
    // with the generic `img_*` pipeline.
    public function photoPerformance()
    {
        $defaults = [
            // Server-side original compression
            'photo_compress_enabled'      => '1',
            'photo_compress_max_width'    => '2560',
            'photo_compress_max_height'   => '2560',
            'photo_compress_quality'      => '85',
            'photo_compress_format'       => 'jpeg',      // jpeg | webp | original
            'photo_compress_strip_exif'   => '1',

            // Derivative sizes
            'photo_thumbnail_size'        => '400',
            'photo_thumbnail_quality'     => '75',
            'photo_preview_max'           => '1600',
            'photo_preview_quality'       => '82',

            // Gallery delivery
            'photo_gallery_eager_count'   => '12',
            'photo_gallery_thumb_size'    => '200',
            'photo_gallery_cache_seconds' => '60',

            // Client-side (browser) pre-upload compression
            'photo_client_compress_enabled' => '1',
            'photo_client_max_dimension'    => '3840',
            'photo_client_quality'          => '85',
        ];

        $all      = AppSetting::getAll();
        $settings = [];
        foreach ($defaults as $key => $fallback) {
            $settings[$key] = $all[$key] ?? $fallback;
        }

        $gdAvailable = extension_loaded('gd');

        return view('admin.settings.photo-performance', compact('settings', 'gdAvailable'));
    }

    public function updatePhotoPerformance(Request $request)
    {
        $validated = $request->validate([
            'photo_compress_max_width'      => 'required|integer|min:800|max:8000',
            'photo_compress_max_height'     => 'required|integer|min:800|max:8000',
            'photo_compress_quality'        => 'required|integer|min:50|max:100',
            'photo_compress_format'         => 'required|in:jpeg,webp,original',
            'photo_thumbnail_size'          => 'required|integer|min:100|max:800',
            'photo_thumbnail_quality'       => 'required|integer|min:50|max:100',
            'photo_preview_max'             => 'required|integer|min:600|max:4000',
            'photo_preview_quality'         => 'required|integer|min:50|max:100',
            'photo_gallery_eager_count'     => 'required|integer|min:0|max:60',
            'photo_gallery_thumb_size'      => 'required|integer|min:100|max:600',
            'photo_gallery_cache_seconds'   => 'required|integer|min:0|max:3600',
            'photo_client_max_dimension'    => 'required|integer|min:800|max:8000',
            'photo_client_quality'          => 'required|integer|min:50|max:100',
        ], [
            '*.required' => 'กรุณากรอกค่านี้',
            '*.integer'  => 'ค่าต้องเป็นตัวเลขจำนวนเต็ม',
            '*.min'      => 'ค่าน้อยเกินไป',
            '*.max'      => 'ค่ามากเกินไป',
        ]);

        $checkboxKeys = [
            'photo_compress_enabled',
            'photo_compress_strip_exif',
            'photo_client_compress_enabled',
        ];

        $payload = $validated;
        foreach ($checkboxKeys as $key) {
            $payload[$key] = $request->has($key) ? '1' : '0';
        }

        // Normalize all to string — AppSetting stores string values.
        foreach ($payload as $k => $v) {
            $payload[$k] = (string) $v;
        }

        AppSetting::setMany($payload);

        // Invalidate AppSetting cache so new values take effect immediately
        // on the photographer upload page + public gallery.
        Cache::forget('app_settings_all');

        return back()->with('success', 'บันทึกการตั้งค่าการอัปโหลดและแสดงผลรูปภาพเรียบร้อยแล้ว');
    }

    // ─────────────────────────────────────────────────────────────
    // Language (multi-lingual toggle + supported-language whitelist)
    // ─────────────────────────────────────────────────────────────

    public function language()
    {
        $all = AppSetting::getAll();

        $settings = [
            'multilang_enabled'  => $all['multilang_enabled']  ?? '0',
            'default_language'   => $all['default_language']   ?? 'th',
            'enabled_languages'  => $all['enabled_languages']  ?? '["th"]',
        ];

        // Decode enabled_languages JSON to array
        $enabledLangs = json_decode($settings['enabled_languages'], true) ?? ['th'];

        return view('admin.settings.language', compact('settings', 'enabledLangs'));
    }

    public function updateLanguage(Request $request)
    {
        $supported = \App\Http\Controllers\Api\LanguageApiController::SUPPORTED;

        $default = $request->input('default_language', 'th');
        if (!array_key_exists($default, $supported)) {
            $default = 'th';
        }

        $enabled = $request->input('enabled_languages', []);
        if (!is_array($enabled)) {
            $enabled = [$enabled];
        }

        // Filter to only supported + dedupe + ensure default is included
        $enabled = array_values(array_unique(array_filter($enabled, fn($l) => array_key_exists($l, $supported))));
        if (!in_array($default, $enabled, true)) {
            $enabled[] = $default;
        }
        if (empty($enabled)) {
            $enabled = [$default];
        }

        AppSetting::setMany([
            'multilang_enabled' => $request->has('multilang_enabled') ? '1' : '0',
            'default_language'  => $default,
            'enabled_languages' => json_encode(array_values($enabled)),
        ]);

        return back()->with('success', 'บันทึกการตั้งค่าภาษาสำเร็จ');
    }

    // ─────────────────────────────────────────────────────────────
    // LINE (Notify + Messaging API + push-notification toggles)
    // ─────────────────────────────────────────────────────────────

    public function line()
    {
        $keys = [
            // ─── Section A: LINE Login Channel (OAuth — for "Sign in with LINE") ───
            //   Separate channel under the same Provider as the Messaging
            //   channel below — that's how the customer's userId stays
            //   consistent across the two channels.
            'line_login_channel_id', 'line_login_channel_secret',

            // ─── Section B: LINE Messaging API Channel (Push + OA + Admin alerts) ───
            //   Replaces dead LINE Notify (killed 31 Mar 2025). Admin alerts now
            //   delivered via Messaging API multicast to admin LINE userIds.
            'line_messaging_enabled', 'line_channel_id',
            'line_channel_secret', 'line_channel_access_token',
            'line_admin_user_ids',

            // Webhook + auto-reply behaviour
            'line_webhook_log', 'line_webhook_auto_reply',

            // Admin channel toggles
            'line_admin_notify_orders', 'line_admin_notify_events',
            'line_admin_notify_registration', 'line_admin_notify_payouts',
            'line_admin_notify_contact', 'line_admin_notify_cancellation',

            // User push toggles
            'line_user_push_enabled', 'line_user_push_download',
            'line_user_push_events', 'line_user_push_payout',
        ];

        $all      = AppSetting::getAll();
        $settings = [];
        foreach ($keys as $key) {
            $settings[$key] = $all[$key] ?? '0';
        }

        return view('admin.settings.line', compact('settings'));
    }

    public function updateLine(Request $request)
    {
        $checkboxKeys = [
            'line_messaging_enabled',
            'line_webhook_log', 'line_webhook_auto_reply',
            'line_admin_notify_orders', 'line_admin_notify_events',
            'line_admin_notify_registration', 'line_admin_notify_payouts',
            'line_admin_notify_contact', 'line_admin_notify_cancellation',
            'line_user_push_enabled', 'line_user_push_download',
            'line_user_push_events', 'line_user_push_payout',
        ];

        $textKeys = [
            // Login channel (OAuth)
            'line_login_channel_id', 'line_login_channel_secret',
            // Messaging API channel
            'line_channel_id', 'line_channel_secret', 'line_channel_access_token',
            // Admin LINE userIds for system alerts (replaces dead Notify token)
            'line_admin_user_ids',
        ];

        $items = [];

        // Handle checkboxes (unchecked = not in request → '0')
        foreach ($checkboxKeys as $key) {
            $items[$key] = $request->has($key) ? '1' : '0';
        }

        // Handle text/password fields
        foreach ($textKeys as $key) {
            if ($request->filled($key)) {
                $items[$key] = $request->input($key);
            }
            // If empty, leave existing value (don't overwrite token with blank)
        }

        AppSetting::setMany($items);

        return back()->with('success', 'บันทึกการตั้งค่า LINE เรียบร้อยแล้ว');
    }

    // ─────────────────────────────────────────────────────────────
    // Delivery (how paid photos reach the buyer: web / LINE / email)
    // ─────────────────────────────────────────────────────────────

    public function delivery()
    {
        $defaults = [
            'delivery_methods_enabled'  => '["web","line","email"]',
            'delivery_default_method'   => 'auto',
            'delivery_auto_switch'      => '1',
            'delivery_line_max_photos'  => '9',
            'delivery_email_threshold'  => '30',
            'delivery_web_always'       => '1',
        ];

        $all      = AppSetting::getAll();
        $settings = [];
        foreach ($defaults as $key => $fallback) {
            $settings[$key] = $all[$key] ?? $fallback;
        }

        // Decode the JSON array into a simple list for the view
        $decoded = json_decode((string) $settings['delivery_methods_enabled'], true);
        $settings['delivery_methods_enabled_list'] = is_array($decoded)
            ? $decoded
            : ['web', 'line', 'email'];

        return view('admin.settings.delivery', compact('settings'));
    }

    public function updateDelivery(Request $request)
    {
        // Checkboxes: one per channel. Web is forced on (safety net).
        $channels = ['web', 'line', 'email'];
        $enabled  = ['web'];  // always include web
        foreach ($channels as $channel) {
            if ($request->has('delivery_enabled_' . $channel) && $channel !== 'web') {
                $enabled[] = $channel;
            }
        }
        $enabled = array_values(array_unique($enabled));

        $default = $request->input('delivery_default_method', 'auto');
        if (!in_array($default, ['auto', 'web', 'line', 'email'], true)) {
            $default = 'auto';
        }

        $emailThresh = (int) $request->input('delivery_email_threshold', 30);
        if ($emailThresh < 1)   $emailThresh = 1;
        if ($emailThresh > 500) $emailThresh = 500;

        $lineMax = (int) $request->input('delivery_line_max_photos', 9);
        if ($lineMax < 1)  $lineMax = 1;
        if ($lineMax > 10) $lineMax = 10;  // LINE API hard cap

        AppSetting::setMany([
            'delivery_methods_enabled' => json_encode($enabled),
            'delivery_default_method'  => $default,
            'delivery_auto_switch'     => $request->has('delivery_auto_switch') ? '1' : '0',
            'delivery_line_max_photos' => (string) $lineMax,
            'delivery_email_threshold' => (string) $emailThresh,
            'delivery_web_always'      => '1',  // always on; exposed for future flexibility
        ]);

        return back()->with('success', 'บันทึกการตั้งค่าการจัดส่งรูปภาพเรียบร้อยแล้ว');
    }

    // ─────────────────────────────────────────────────────────────
    // Webhook Monitor (payment-audit-log readouts per gateway)
    // ─────────────────────────────────────────────────────────────

    public function webhooks(Request $request)
    {
        // Actual columns: id, transaction_id, order_id, action, actor_type,
        //                 actor_id, ip_address, old_values, new_values, signature, created_at
        // action stores gateway-prefixed events like "stripe_payment_intent.succeeded"
        // new_values stores JSON payload

        $stats = [
            'total_today'   => 0,
            'success_today' => 0,
            'failed_today'  => 0,
            'last_received' => null,
        ];

        $logs      = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 25);
        $gateways  = collect();
        $hasTable  = Schema::hasTable('payment_audit_log');

        if ($hasTable) {
            $baseQuery = DB::table('payment_audit_log')->where('actor_type', 'webhook');

            $stats['total_today']   = (clone $baseQuery)->whereDate('created_at', today())->count();
            $stats['last_received'] = (clone $baseQuery)->max('created_at');

            // Extract unique gateway prefixes from action column
            $allActions = (clone $baseQuery)->distinct()->pluck('action')->filter();
            $gateways = $allActions->map(fn($a) => explode('_', $a, 2)[0])->unique()->sort()->values();

            // Success/failed: check action for failure keywords
            $todayActions = (clone $baseQuery)->whereDate('created_at', today())->pluck('action');
            foreach ($todayActions as $action) {
                if (str_contains($action, 'failure') || str_contains($action, 'failed') || str_contains($action, 'error')) {
                    $stats['failed_today']++;
                } else {
                    $stats['success_today']++;
                }
            }

            // Build log query — select with aliases so the view can use ->gateway, ->event_type, ->payload
            $logQuery = DB::table('payment_audit_log')
                ->where('actor_type', 'webhook')
                ->selectRaw("*, action as event_type, new_values as payload, split_part(action, '_', 1) as gateway")
                ->orderByDesc('created_at');

            if ($request->filled('gateway')) {
                $logQuery->where('action', 'ilike', $request->input('gateway') . '_%');
            }

            if ($request->filled('event_type')) {
                $logQuery->where('action', 'ilike', '%' . $request->input('event_type') . '%');
            }

            $logs = $logQuery->paginate(25)->withQueryString();
        }

        // Webhook endpoints definition
        $baseUrl = rtrim(config('app.url'), '/');
        $gwWhere = fn($gw) => $hasTable
            ? DB::table('payment_audit_log')->where('actor_type', 'webhook')->where('action', 'ilike', "{$gw}_%")
            : null;

        $endpoints = [
            [
                'gateway'       => 'stripe',
                'label'         => 'Stripe',
                'url'           => $baseUrl . '/webhook/stripe',
                'last_received' => $gwWhere('stripe') ? (clone $gwWhere('stripe'))->max('created_at') : null,
                'count_today'   => $gwWhere('stripe') ? (clone $gwWhere('stripe'))->whereDate('created_at', today())->count() : 0,
                'active'        => AppSetting::get('stripe_enabled', '0') === '1',
            ],
            [
                'gateway'       => 'omise',
                'label'         => 'Omise',
                'url'           => $baseUrl . '/webhook/omise',
                'last_received' => $gwWhere('omise') ? (clone $gwWhere('omise'))->max('created_at') : null,
                'count_today'   => $gwWhere('omise') ? (clone $gwWhere('omise'))->whereDate('created_at', today())->count() : 0,
                'active'        => AppSetting::get('omise_enabled', '0') === '1',
            ],
            [
                'gateway'       => 'line',
                'label'         => 'LINE',
                'url'           => $baseUrl . '/webhook/line',
                'last_received' => $gwWhere('linepay') ? (clone $gwWhere('linepay'))->max('created_at') : null,
                'count_today'   => $gwWhere('linepay') ? (clone $gwWhere('linepay'))->whereDate('created_at', today())->count() : 0,
                'active'        => AppSetting::get('line_messaging_enabled', '0') === '1',
            ],
            [
                'gateway'       => 'promptpay',
                'label'         => 'PromptPay',
                'url'           => $baseUrl . '/webhook/promptpay',
                'last_received' => $gwWhere('slipok') ? (clone $gwWhere('slipok'))->max('created_at') : null,
                'count_today'   => $gwWhere('slipok') ? (clone $gwWhere('slipok'))->whereDate('created_at', today())->count() : 0,
                'active'        => AppSetting::get('promptpay_number', '') !== '',
            ],
        ];

        return view('admin.settings.webhooks', compact('stats', 'logs', 'gateways', 'endpoints'));
    }
}

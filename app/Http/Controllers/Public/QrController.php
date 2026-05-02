<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Services\BrandedQrService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Branded QR code endpoint.
 *
 * Streams a PNG with the site logo + brand label baked in. Designed to be
 * dropped into <img src="/qr/branded?data=..."> by event QR pages so when
 * the user prints/screenshots/shares the result they see the brand.
 *
 * Cached aggressively (per data+label+size+logo_version) — output is
 * deterministic, and the cache key includes site_logo so an admin logo
 * change auto-busts.
 */
class QrController extends Controller
{
    public function branded(Request $request, BrandedQrService $svc)
    {
        $data = (string) $request->query('data', '');
        // null label = use the service's default (APP_URL host).
        // Empty string explicitly = caller wants no label at all.
        $label = $request->query('label');

        // Clamp size to a sane range. 200 is the smallest readable on
        // mobile thumbnails; 800 is large enough for 4×6" prints at 200dpi
        // — anything bigger is wasted bytes since QR resolution is fixed.
        $size = max(200, min(800, (int) $request->query('size', 300)));

        // Reject empty / oversized payloads. ECC=H caps URL data at ~1273
        // chars; we set a tighter 2000-char hard limit since URLs that
        // long indicate something pathological (probably abuse).
        if ($data === '' || strlen($data) > 2000) {
            abort(422, 'invalid data');
        }

        // Cache key includes logo storage_key so a new admin upload
        // invalidates without us tracking versions explicitly.
        $logoVersion = (string) AppSetting::get('site_logo', '');
        $cacheKey    = 'qr:branded:' . md5($data . '|' . ($label ?? '__default__') . '|' . $size . '|' . $logoVersion);

        $png = Cache::remember(
            $cacheKey,
            86400,                                    // 24h — same as Cache-Control max-age
            fn () => $svc->generate($data, $label === null ? null : (string) $label, $size),
        );

        return response($png, 200, [
            'Content-Type'   => 'image/png',
            // Public + immutable: we vary cache by all input params so the
            // same URL always returns the same bytes.
            'Cache-Control'  => 'public, max-age=86400, immutable',
            'Content-Length' => (string) strlen($png),
        ]);
    }
}

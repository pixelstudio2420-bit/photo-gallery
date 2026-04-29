<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\AdCreative;
use App\Services\Monetization\AdServingService;
use Illuminate\Http\Request;

/**
 * Public-facing ad endpoints.
 *
 *   GET  /ads/{creative}/click  — log + 302 to creative.click_url
 *   POST /ads/{creative}/seen   — beacon-style impression log (called from JS)
 *
 * Both endpoints rate-limit at the Service layer (see AdServingService).
 * The route group adds an additional Laravel-level rate limit on
 * /ads/click as a hard ceiling.
 */
class AdController extends Controller
{
    public function __construct(private readonly AdServingService $ads) {}

    /**
     * Click handler — logs the click then redirects to the destination.
     * 302 (not 301) — we want fresh logs every time, not browser cache.
     */
    public function click(Request $request, AdCreative $creative)
    {
        // Even if AdServingService says suspicious, we still redirect so
        // the user reaches the advertiser's site. We just don't bill.
        $this->ads->recordClick($creative, $request);

        $url = $creative->click_url;
        if (!preg_match('~^https?://~i', $url)) {
            return redirect('/');   // safety net for misconfigured creative
        }
        return redirect()->away($url, 302);
    }

    /**
     * Impression beacon — POST so the request doesn't trigger preload
     * by Chrome's link-prefetch (which would inflate impressions).
     * Returns 204 No Content; success is implicit.
     */
    public function seen(Request $request, AdCreative $creative)
    {
        $this->ads->recordImpression($creative, $request);
        return response()->noContent();
    }
}

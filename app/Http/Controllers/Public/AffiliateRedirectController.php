<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\BlogAffiliateClick;
use App\Models\BlogAffiliateLink;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AffiliateRedirectController extends Controller
{
    /**
     * Click cooldown in seconds — prevent duplicate tracking from the same
     * IP within this window.
     */
    protected const CLICK_COOLDOWN_SECONDS = 300; // 5 minutes

    /* ================================================================
     *  redirect — Handle /go/{slug} redirects
     * ================================================================ */
    public function redirect(Request $request, string $slug)
    {
        $link = BlogAffiliateLink::active()->where('slug', $slug)->first();

        // Link not found or inactive — redirect home with error
        if (!$link) {
            return redirect('/')
                ->with('error', 'ลิงก์ที่คุณเข้าถึงไม่พร้อมใช้งานหรือหมดอายุแล้ว');
        }

        // ── Track click ──
        $this->trackClick($request, $link);

        // ── 302 redirect to destination ──
        return redirect()->away($link->destination_url, 302);
    }

    /* ────────────────────────────────────────────────────────────────
     *  Private: track the click with cooldown logic
     * ──────────────────────────────────────────────────────────────── */
    protected function trackClick(Request $request, BlogAffiliateLink $link): void
    {
        try {
            $ip        = $request->ip();
            $userAgent = $request->userAgent() ?? '';

            // ── Cooldown check — skip duplicate clicks from same IP ──
            $recentClick = BlogAffiliateClick::where('affiliate_link_id', $link->id)
                ->where('ip_address', $ip)
                ->where('clicked_at', '>=', now()->subSeconds(self::CLICK_COOLDOWN_SECONDS))
                ->exists();

            if ($recentClick) {
                return;
            }

            // ── Detect device type ──
            $deviceType = $this->detectDeviceType($userAgent);

            // ── Resolve referrer ──
            $referrer = $request->header('referer');

            // ── Determine post_id if referrer is from a blog post ──
            $postId = null;
            if ($referrer) {
                $parsed = parse_url($referrer, PHP_URL_PATH);
                if ($parsed && preg_match('#^/blog/([a-z0-9\-]+)$#', $parsed, $m)) {
                    $post = \App\Models\BlogPost::where('slug', $m[1])->first();
                    if ($post) {
                        $postId = $post->id;
                    }
                }
            }

            // ── Store click record ──
            BlogAffiliateClick::create([
                'affiliate_link_id' => $link->id,
                'post_id'           => $postId,
                'user_id'           => auth()->id(),
                'ip_address'        => $ip,
                'user_agent'        => mb_substr($userAgent, 0, 500),
                'referrer'          => $referrer ? mb_substr($referrer, 0, 500) : null,
                'country'           => null, // Could integrate GeoIP later
                'device_type'       => $deviceType,
                'clicked_at'        => now(),
            ]);

            // ── Increment total clicks on the affiliate link ──
            $link->increment('total_clicks');
        } catch (\Throwable $e) {
            // Never let tracking errors prevent the redirect
            Log::warning('Affiliate click tracking failed', [
                'link_id' => $link->id,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    /* ────────────────────────────────────────────────────────────────
     *  Private: simple device type detection from user-agent
     * ──────────────────────────────────────────────────────────────── */
    protected function detectDeviceType(string $userAgent): string
    {
        $ua = strtolower($userAgent);

        if (preg_match('/mobile|android|iphone|ipod|blackberry|opera mini|iemobile/i', $ua)) {
            return 'mobile';
        }
        if (preg_match('/tablet|ipad|kindle|silk/i', $ua)) {
            return 'tablet';
        }

        return 'desktop';
    }
}

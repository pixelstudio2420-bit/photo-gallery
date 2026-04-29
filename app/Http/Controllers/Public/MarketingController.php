<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\Marketing\LandingPage;
use App\Models\Marketing\MarketingEvent;
use App\Models\Marketing\Subscriber;
use App\Services\Marketing\CampaignService;
use App\Services\Marketing\LandingPageService;
use App\Services\Marketing\LoyaltyService;
use App\Services\Marketing\MarketingAnalyticsService;
use App\Services\Marketing\MarketingService;
use App\Services\Marketing\NewsletterService;
use App\Services\Marketing\PushService;
use App\Services\Marketing\ReferralService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Public-facing marketing endpoints:
 *  - Newsletter: subscribe, confirm, unsubscribe
 *  - Referral:   my code, invite page
 *  - Loyalty:    my points dashboard
 *  - Campaign tracking: open pixel, click redirect
 */
class MarketingController extends Controller
{
    public function subscribe(Request $request, NewsletterService $newsletter)
    {
        $data = $request->validate([
            'email' => 'required|email|max:255',
            'name'  => 'nullable|string|max:128',
        ]);

        $result = $newsletter->subscribe($data['email'], [
            'name'    => $data['name'] ?? null,
            'source'  => $request->input('source', 'widget'),
            'user_id' => Auth::id(),
            'locale'  => app()->getLocale(),
            'meta'    => [
                'ip'         => $request->ip(),
                'user_agent' => $request->userAgent(),
                'utm'        => [
                    'source'   => $request->input('utm_source'),
                    'medium'   => $request->input('utm_medium'),
                    'campaign' => $request->input('utm_campaign'),
                ],
            ],
        ]);

        if ($request->expectsJson()) {
            return response()->json($result);
        }
        return back()->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

    public function confirm(string $token, NewsletterService $newsletter)
    {
        $result = $newsletter->confirm($token);
        return view('public.newsletter.confirm', ['result' => $result]);
    }

    public function unsubscribe(Request $request, NewsletterService $newsletter)
    {
        $email = $request->input('email') ?: $request->query('email');
        if (!$email) {
            return view('public.newsletter.unsubscribe', ['state' => 'ask']);
        }
        if ($request->isMethod('POST')) {
            $result = $newsletter->unsubscribe($email, $request->input('reason'));
            return view('public.newsletter.unsubscribe', ['state' => 'done', 'result' => $result, 'email' => $email]);
        }
        return view('public.newsletter.unsubscribe', ['state' => 'confirm', 'email' => $email]);
    }

    // ── Referral ──────────────────────────────────────────

    public function myReferral(ReferralService $referral)
    {
        if (!Auth::check()) {
            return redirect()->route('auth.login')->with('error', 'กรุณาเข้าสู่ระบบก่อน');
        }
        $user = Auth::user();
        $code = $referral->getOrCreateForUser($user);
        if (!$code) {
            return view('public.marketing.referral-disabled');
        }
        $stats = $referral->statsForUser($user);
        // Short URL `/r/{code}` is preferred (cleaner for SMS/Twitter); the
        // `/?ref=` form still works as a fallback for any legacy share links.
        $shareUrl = url('/r/' . $code->code);
        return view('public.marketing.my-referral', compact('code', 'stats', 'shareUrl'));
    }

    // ── Loyalty ───────────────────────────────────────────

    public function myLoyalty(LoyaltyService $loyalty)
    {
        if (!Auth::check()) {
            return redirect()->route('auth.login')->with('error', 'กรุณาเข้าสู่ระบบก่อน');
        }
        if (!$loyalty->enabled()) {
            return view('public.marketing.loyalty-disabled');
        }
        $user = Auth::user();
        $account = $loyalty->getOrCreate($user->id);
        $transactions = $account->transactions()->orderByDesc('created_at')->limit(50)->get();
        return view('public.marketing.my-loyalty', compact('account', 'transactions'));
    }

    // ── Campaign tracking ─────────────────────────────────

    public function trackOpen(string $token, CampaignService $campaign)
    {
        $campaign->trackOpen($token);
        // 1x1 transparent GIF
        return response(base64_decode('R0lGODlhAQABAIAAAP///wAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw=='))
            ->header('Content-Type', 'image/gif')
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache');
    }

    public function trackClick(Request $request, string $token, CampaignService $campaign)
    {
        $campaign->trackClick($token);
        $redirect = $request->query('to', url('/'));
        // Safety: only allow same-origin or whitelisted domains
        if (!filter_var($redirect, FILTER_VALIDATE_URL)) {
            $redirect = url('/');
        }
        return redirect($redirect);
    }

    // ═══════════════════════════════════════════════════════════
    //  PHASE 3 — Landing Pages (public render)
    // ═══════════════════════════════════════════════════════════

    public function showLandingPage(string $slug, LandingPageService $lpSvc, MarketingService $marketing, MarketingAnalyticsService $analytics, Request $request)
    {
        if (!$marketing->enabled('landing_pages')) {
            abort(404);
        }

        $page = LandingPage::where('slug', $slug)->published()->firstOrFail();

        $lpSvc->recordView($page);
        $analytics->track(MarketingEvent::EV_LP_VIEW, [
            'url'          => $request->fullUrl(),
            'referrer'     => $request->headers->get('referer'),
            'lp_id'        => $page->id,
            'campaign_id'  => $page->campaign_id,
            'utm_source'   => $request->input('utm_source'),
            'utm_medium'   => $request->input('utm_medium'),
            'utm_campaign' => $request->input('utm_campaign'),
            'utm_content'  => $request->input('utm_content'),
            'utm_term'     => $request->input('utm_term'),
            'session_id'   => $request->session()->getId(),
            'user_id'      => Auth::id(),
            'ip'           => $request->ip(),
        ]);

        return view('public.marketing.landing-render', compact('page'));
    }

    public function landingCtaClick(LandingPage $landingPage, LandingPageService $lpSvc, MarketingAnalyticsService $analytics, Request $request)
    {
        $lpSvc->recordConversion($landingPage);
        $analytics->track(MarketingEvent::EV_LP_CTA, [
            'lp_id'      => $landingPage->id,
            'url'        => $request->fullUrl(),
            'session_id' => $request->session()->getId(),
            'user_id'    => Auth::id(),
        ]);
        $target = $landingPage->cta_url ?: url('/');
        return redirect($target);
    }

    // ═══════════════════════════════════════════════════════════
    //  PHASE 3 — Push Subscription
    // ═══════════════════════════════════════════════════════════

    public function pushSubscribe(Request $request, PushService $push, MarketingAnalyticsService $analytics)
    {
        if (!$push->enabled()) {
            return response()->json(['ok' => false, 'message' => 'Push disabled'], 403);
        }

        $data = $request->validate([
            'endpoint'     => 'required|string|max:600',
            'keys.p256dh'  => 'required|string|max:200',
            'keys.auth'    => 'required|string|max:100',
        ]);

        $sub = $push->subscribe(
            $data,
            Auth::id(),
            app()->getLocale(),
            $request->userAgent()
        );

        $analytics->track(MarketingEvent::EV_PUSH_SUBSCRIBE, [
            'user_id'    => Auth::id(),
            'session_id' => $request->session()->getId(),
            'ip'         => $request->ip(),
        ]);

        return response()->json(['ok' => true, 'id' => $sub->id]);
    }

    public function pushUnsubscribe(Request $request, PushService $push)
    {
        $endpoint = $request->input('endpoint');
        if ($endpoint) {
            $push->unsubscribe($endpoint);
        }
        return response()->json(['ok' => true]);
    }

    public function pushClick(Request $request, int $campaignId, PushService $push, MarketingAnalyticsService $analytics)
    {
        $push->recordClick($campaignId);
        $analytics->track(MarketingEvent::EV_PUSH_CLICK, [
            'push_campaign_id' => $campaignId,
            'session_id'       => $request->session()->getId(),
            'user_id'          => Auth::id(),
        ]);
        $to = $request->query('to', url('/'));
        if (!filter_var($to, FILTER_VALIDATE_URL)) $to = url('/');
        return redirect($to);
    }

    public function pushVapidPublicKey(PushService $push)
    {
        if (!$push->enabled()) {
            return response()->json(['ok' => false], 403);
        }
        return response()->json([
            'ok'        => true,
            'publicKey' => $push->publicVapidKey(),
        ]);
    }

    // Service worker script — served from root for scope "/"
    public function pushServiceWorker(PushService $push)
    {
        return response()->view('public.marketing.push-sw', [], 200, [
            'Content-Type'           => 'application/javascript; charset=utf-8',
            'Service-Worker-Allowed' => '/',
            'Cache-Control'          => 'no-cache, max-age=0',
        ]);
    }
}

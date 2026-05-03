<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\Marketing\Campaign;
use App\Models\Marketing\LandingPage;
use App\Models\Marketing\LoyaltyAccount;
use App\Models\Marketing\PushCampaign;
use App\Models\Marketing\PushSubscription;
use App\Models\Marketing\ReferralCode;
use App\Models\Marketing\Subscriber;
use App\Services\Marketing\AttributionService;
use App\Services\Marketing\CampaignService;
use App\Services\Marketing\LandingPageService;
use App\Services\Marketing\LineBroadcastService;
use App\Services\Marketing\LineNotifyService;
use App\Services\Marketing\LoyaltyService;
use App\Services\Marketing\MarketingAnalyticsService;
use App\Services\Marketing\MarketingService;
use App\Services\Marketing\PushService;
use App\Services\Marketing\ReferralService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Admin → Marketing Hub
 *
 * Central dashboard with master on/off + quick toggles + deep links to
 * per-feature settings pages (Pixels, LINE, SEO, Newsletter, etc.)
 */
class MarketingController extends Controller
{
    public function __construct(protected MarketingService $marketing) {}

    /**
     * Hub landing page — shows status of all features + quick toggles.
     */
    public function index()
    {
        $status  = $this->marketing->statusSummary();
        $groups  = $this->marketing->featureGroups();
        $metrics = $this->quickMetrics();

        return view('admin.marketing.index', compact('status', 'groups', 'metrics'));
    }

    /**
     * Master toggle + individual feature toggles (AJAX-friendly).
     */
    public function toggle(Request $request)
    {
        $data = $request->validate([
            'feature' => 'required|string|max:64',
            'enabled' => 'required|boolean',
        ]);

        $key = $data['feature'] === 'master'
            ? 'marketing_enabled'
            : 'marketing_' . $data['feature'] . '_enabled';

        AppSetting::set($key, $data['enabled'] ? '1' : '0');

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'key' => $key, 'value' => $data['enabled']]);
        }
        return back()->with('success', 'อัปเดตการตั้งค่าแล้ว');
    }

    // ═══ Feature settings pages ═══════════════════════════════════════

    public function pixels()
    {
        $settings = $this->loadSettings([
            'fb_pixel_id', 'fb_conversions_api_token', 'fb_test_event_code',
            'ga4_measurement_id', 'ga4_api_secret',
            'gtm_container_id',
            'google_ads_conversion_id', 'google_ads_conversion_label',
            'line_tag_id',
            'tiktok_pixel_id',
        ]);
        $status = $this->marketing->statusSummary();
        return view('admin.marketing.pixels', compact('settings', 'status'));
    }

    public function updatePixels(Request $request)
    {
        $data = $request->validate([
            'fb_pixel_id'                 => 'nullable|string|max:64',
            'fb_pixel_enabled'            => 'nullable|boolean',
            'fb_conversions_api_token'    => 'nullable|string|max:512',
            'fb_conversions_api_enabled'  => 'nullable|boolean',
            'fb_test_event_code'          => 'nullable|string|max:64',

            'ga4_measurement_id'          => 'nullable|string|max:64',
            'ga4_enabled'                 => 'nullable|boolean',
            'ga4_api_secret'              => 'nullable|string|max:128',

            'gtm_container_id'            => 'nullable|string|max:64',
            'gtm_enabled'                 => 'nullable|boolean',

            'google_ads_conversion_id'    => 'nullable|string|max:64',
            'google_ads_conversion_label' => 'nullable|string|max:64',
            'google_ads_enabled'          => 'nullable|boolean',

            'line_tag_id'                 => 'nullable|string|max:64',
            'line_tag_enabled'            => 'nullable|boolean',

            'tiktok_pixel_id'             => 'nullable|string|max:64',
            'tiktok_pixel_enabled'        => 'nullable|boolean',
        ]);

        foreach ($data as $k => $v) {
            if (is_bool($v) || in_array($k, [
                'fb_pixel_enabled', 'fb_conversions_api_enabled', 'ga4_enabled',
                'gtm_enabled', 'google_ads_enabled', 'line_tag_enabled', 'tiktok_pixel_enabled'
            ])) {
                AppSetting::set('marketing_' . $k, $request->boolean($k) ? '1' : '0');
            } else {
                AppSetting::set('marketing_' . $k, (string) ($v ?? ''));
            }
        }
        return back()->with('success', 'บันทึก pixel/analytics settings แล้ว');
    }

    public function line(LineBroadcastService $broadcast, LineNotifyService $notify)
    {
        $settings = $this->loadSettings([
            'line_channel_access_token', 'line_channel_secret', 'line_oa_id',
            'line_notify_token',
        ]);

        $quota = $this->marketing->lineMessagingEnabled() ? $broadcast->quota() : null;
        $usage = $this->marketing->lineMessagingEnabled() ? $broadcast->consumption() : null;

        return view('admin.marketing.line', compact('settings', 'quota', 'usage'));
    }

    public function updateLine(Request $request)
    {
        $data = $request->validate([
            'line_channel_access_token' => 'nullable|string|max:512',
            'line_channel_secret'       => 'nullable|string|max:128',
            'line_oa_id'                => 'nullable|string|max:64',
            'line_messaging_enabled'    => 'nullable|boolean',
            'line_notify_token'         => 'nullable|string|max:128',
            'line_notify_enabled'       => 'nullable|boolean',
        ]);

        foreach (['line_messaging_enabled', 'line_notify_enabled'] as $k) {
            AppSetting::set('marketing_' . $k, $request->boolean($k) ? '1' : '0');
        }
        foreach (['line_channel_access_token', 'line_channel_secret', 'line_oa_id', 'line_notify_token'] as $k) {
            if (array_key_exists($k, $data)) {
                AppSetting::set('marketing_' . $k, (string) ($data[$k] ?? ''));
            }
        }
        return back()->with('success', 'บันทึก LINE settings แล้ว');
    }

    public function broadcastLine(Request $request, LineBroadcastService $broadcast)
    {
        $data = $request->validate([
            'message' => 'required|string|max:5000',
        ]);

        $result = $broadcast->broadcastText($data['message']);

        if (!$result['ok']) {
            return back()->with('error', 'ส่ง broadcast ไม่สำเร็จ: ' . ($result['error'] ?? 'unknown'));
        }
        return back()->with('success', 'ส่ง broadcast สำเร็จ — ข้อความจะถึงทุกคนที่ add friend OA');
    }

    public function testLineNotify(Request $request, LineNotifyService $notify)
    {
        $result = $notify->send('🧪 ทดสอบ LINE Notify จาก ' . config('app.name'));
        if (!$result['ok']) {
            return back()->with('error', 'ทดสอบล้มเหลว: ' . ($result['error'] ?? 'unknown'));
        }
        return back()->with('success', 'ส่งข้อความทดสอบ LINE Notify สำเร็จ');
    }

    public function seo()
    {
        $settings = $this->loadSettings(['og_default_image']);
        return view('admin.marketing.seo', compact('settings'));
    }

    public function updateSeo(Request $request)
    {
        $data = $request->validate([
            'schema_markup_enabled' => 'nullable|boolean',
            'og_auto_enabled'       => 'nullable|boolean',
            'og_default_image'      => 'nullable|string|max:512',
        ]);
        AppSetting::set('marketing_schema_markup_enabled', $request->boolean('schema_markup_enabled') ? '1' : '0');
        AppSetting::set('marketing_og_auto_enabled',       $request->boolean('og_auto_enabled') ? '1' : '0');
        AppSetting::set('marketing_og_default_image',      (string) ($data['og_default_image'] ?? ''));
        return back()->with('success', 'บันทึก SEO settings แล้ว');
    }

    /**
     * Marketing analytics dashboard (UTM-based).
     */
    public function analytics(AttributionService $attribution)
    {
        $days = 30;
        $channels = $attribution->channelPerformance($days);

        // Total traffic + orders from attribution
        $summary = [
            'visits'   => DB::table('marketing_utm_attributions')->where('created_at', '>=', now()->subDays($days))->count(),
            'orders'   => DB::table('marketing_utm_attributions')->where('created_at', '>=', now()->subDays($days))->whereNotNull('order_id')->count(),
            'revenue'  => 0,
            'top_campaigns' => [],
        ];
        try {
            $summary['revenue'] = (float) DB::table('marketing_utm_attributions as u')
                ->join('orders as o', 'o.id', '=', 'u.order_id')
                ->where('u.created_at', '>=', now()->subDays($days))
                ->sum('o.total_amount');
        } catch (\Throwable $e) {}

        try {
            $summary['top_campaigns'] = DB::table('marketing_utm_attributions as u')
                ->leftJoin('orders as o', 'o.id', '=', 'u.order_id')
                ->selectRaw("COALESCE(u.utm_campaign, '(not set)') as campaign, COUNT(DISTINCT u.id) as visits, COUNT(DISTINCT o.id) as orders, COALESCE(SUM(o.total_amount),0) as revenue")
                ->where('u.created_at', '>=', now()->subDays($days))
                ->groupBy('campaign')
                ->orderByDesc('revenue')
                ->limit(10)
                ->get()
                ->map(fn($r) => (array) $r)
                ->all();
        } catch (\Throwable $e) {}

        return view('admin.marketing.analytics', compact('channels', 'summary', 'days'));
    }

    // ═══ Phase 2: Newsletter / Subscribers ═══════════════════════════

    public function subscribers(Request $request)
    {
        $q      = trim((string) $request->get('q', ''));
        $status = $request->get('status', '');

        $query = Subscriber::query()->orderByDesc('created_at');
        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('email', 'ilike', "%$q%")->orWhere('name', 'ilike', "%$q%");
            });
        }
        if ($status !== '') $query->where('status', $status);
        $subscribers = $query->paginate(40)->withQueryString();

        $stats = [
            'total'        => Subscriber::count(),
            'confirmed'    => Subscriber::confirmed()->count(),
            'pending'      => Subscriber::pending()->count(),
            'unsubscribed' => Subscriber::unsubscribed()->count(),
        ];

        $settings = [
            'newsletter_double_optin'    => AppSetting::get('marketing_newsletter_double_optin', '1') === '1',
            'newsletter_welcome_enabled' => AppSetting::get('marketing_newsletter_welcome_enabled', '0') === '1',
            'email_from_name'            => AppSetting::get('marketing_email_from_name', ''),
            'email_from_address'         => AppSetting::get('marketing_email_from_address', ''),
        ];

        return view('admin.marketing.subscribers', compact('subscribers', 'stats', 'settings', 'q', 'status'));
    }

    public function updateSubscriberSettings(Request $request)
    {
        $data = $request->validate([
            'newsletter_double_optin'    => 'nullable|boolean',
            'newsletter_welcome_enabled' => 'nullable|boolean',
            'email_from_name'            => 'nullable|string|max:128',
            'email_from_address'         => 'nullable|email|max:255',
        ]);
        AppSetting::set('marketing_newsletter_double_optin',    $request->boolean('newsletter_double_optin')    ? '1' : '0');
        AppSetting::set('marketing_newsletter_welcome_enabled', $request->boolean('newsletter_welcome_enabled') ? '1' : '0');
        AppSetting::set('marketing_email_from_name',    (string) ($data['email_from_name']    ?? ''));
        AppSetting::set('marketing_email_from_address', (string) ($data['email_from_address'] ?? ''));
        return back()->with('success', 'บันทึก subscriber settings แล้ว');
    }

    public function deleteSubscriber(Subscriber $subscriber)
    {
        $subscriber->delete();
        return back()->with('success', 'ลบ subscriber แล้ว');
    }

    // ═══ Campaigns ══════════════════════════════════════════════════

    public function campaigns(Request $request)
    {
        $status = $request->get('status', '');
        $q = Campaign::query()->orderByDesc('created_at');
        if ($status !== '') $q->where('status', $status);
        $campaigns = $q->paginate(20)->withQueryString();
        $stats = [
            'total'     => Campaign::count(),
            'draft'     => Campaign::draft()->count(),
            'scheduled' => Campaign::scheduled()->count(),
            'sent'      => Campaign::sent()->count(),
        ];
        return view('admin.marketing.campaigns.index', compact('campaigns', 'stats', 'status'));
    }

    public function createCampaign()
    {
        return view('admin.marketing.campaigns.create');
    }

    public function storeCampaign(Request $request, CampaignService $service)
    {
        $data = $request->validate([
            'name'          => 'required|string|max:128',
            'subject'       => 'required|string|max:256',
            'body_markdown' => 'required|string|max:50000',
            'segment_type'  => 'required|in:all,subscribers,vip,dormant,tag,users',
            'segment_value' => 'nullable|string|max:64',
            'scheduled_at'  => 'nullable|date|after:now',
        ]);
        $adminId = auth('admin')->id();
        $campaign = $service->create([
            'name' => $data['name'],
            'subject' => $data['subject'],
            'body_markdown' => $data['body_markdown'],
            'segment' => ['type' => $data['segment_type'], 'value' => $data['segment_value'] ?? null],
            'status' => !empty($data['scheduled_at']) ? 'scheduled' : 'draft',
            'scheduled_at' => $data['scheduled_at'] ?? null,
        ], $adminId);
        return redirect()->route('admin.marketing.campaigns.show', $campaign)->with('success', 'สร้าง campaign สำเร็จ');
    }

    public function showCampaign(Campaign $campaign)
    {
        $recipients = $campaign->recipients()->orderByDesc('created_at')->limit(50)->get();
        return view('admin.marketing.campaigns.show', compact('campaign', 'recipients'));
    }

    public function sendCampaign(Campaign $campaign, CampaignService $service)
    {
        $result = $service->send($campaign);
        if (!($result['ok'] ?? false)) {
            return back()->with('error', $result['message'] ?? 'ส่ง campaign ไม่สำเร็จ');
        }
        return back()->with('success', "ส่ง campaign สำเร็จ — sent: {$result['sent']}, failed: {$result['failed']}");
    }

    public function cancelCampaign(Campaign $campaign, CampaignService $service)
    {
        $service->cancel($campaign)
            ? $flash = 'ยกเลิก campaign แล้ว'
            : $flash = 'ยกเลิกไม่ได้ (ส่งไปแล้ว)';
        return back()->with('success', $flash);
    }

    public function deleteCampaign(Campaign $campaign)
    {
        if ($campaign->status === 'sent') {
            return back()->with('error', 'ลบ campaign ที่ส่งไปแล้วไม่ได้');
        }
        $campaign->delete();
        return redirect()->route('admin.marketing.campaigns.index')->with('success', 'ลบ campaign แล้ว');
    }

    // ═══ Referral ═══════════════════════════════════════════════════

    public function referral(Request $request)
    {
        // `owner` lives on `auth_users` which has first_name/last_name (no `name`
        // column). The User model exposes a `name` accessor that joins them, so
        // we must select the underlying columns for the accessor to work.
        $codes = ReferralCode::query()
            ->with('owner:id,first_name,last_name,email')
            ->withCount('redemptions')
            ->orderByDesc('created_at')
            ->paginate(30);

        $summary = [
            'codes'        => ReferralCode::count(),
            'active_codes' => ReferralCode::active()->count(),
            'redemptions'  => DB::table('marketing_referral_redemptions')->count(),
            'rewarded'     => DB::table('marketing_referral_redemptions')->where('status', 'rewarded')->count(),
            'total_reward' => (float) DB::table('marketing_referral_redemptions')->where('status', 'rewarded')->sum('reward_granted'),
        ];

        $settings = [
            'discount_type'    => AppSetting::get('marketing_referral_discount_type', 'percent'),
            'discount_value'   => (float) AppSetting::get('marketing_referral_discount_value', 10),
            'reward_value'     => (float) AppSetting::get('marketing_referral_reward_value', 50),
            'cooldown_days'    => (int) AppSetting::get('marketing_referral_cooldown_days', 0),
            'points_per_baht'  => (float) AppSetting::get('marketing_referral_points_per_baht', 1),
        ];

        // Surface toggle states so the admin can see/control them inline —
        // ReferralService::enabled() needs BOTH master and feature to be ON,
        // and Loyalty must be on for rewards to actually credit.
        $masterEnabled   = AppSetting::get('marketing_enabled', '0') == '1';
        $referralEnabled = AppSetting::get('marketing_referral_enabled', '0') == '1';
        $loyaltyEnabled  = AppSetting::get('marketing_loyalty_enabled', '0') == '1';

        return view('admin.marketing.referral', compact(
            'codes', 'summary', 'settings', 'masterEnabled', 'referralEnabled', 'loyaltyEnabled'
        ));
    }

    public function updateReferralSettings(Request $request)
    {
        $data = $request->validate([
            'discount_type'      => 'required|in:percent,fixed',
            'discount_value'     => 'required|numeric|min:0',
            'reward_value'       => 'required|numeric|min:0',
            'cooldown_days'      => 'required|integer|min:0|max:365',
            'points_per_baht'    => 'required|numeric|min:0|max:1000',
            'auto_enable_loyalty'=> 'nullable|boolean',
        ]);
        $autoEnable = (bool) ($data['auto_enable_loyalty'] ?? false);
        unset($data['auto_enable_loyalty']);

        foreach ($data as $k => $v) {
            AppSetting::set('marketing_referral_' . $k, (string) $v);
        }

        // Convenience: if admin ticks "auto-enable loyalty", we flip the
        // loyalty master switch on. Saves a round-trip to the loyalty page
        // and prevents the silent "rewards but no points" state.
        $msg = 'บันทึก referral settings แล้ว';
        if ($autoEnable && AppSetting::get('marketing_loyalty_enabled', '0') != '1') {
            AppSetting::set('marketing_loyalty_enabled', '1');
            $msg .= ' (เปิดระบบ Loyalty ให้อัตโนมัติแล้ว)';
        }
        return back()->with('success', $msg);
    }

    public function toggleReferralCode(ReferralCode $code)
    {
        $code->update(['is_active' => !$code->is_active]);
        return back()->with('success', $code->is_active ? 'เปิดใช้รหัสแล้ว' : 'ปิดรหัสแล้ว');
    }

    /**
     * Edit an individual referral code — lets admin override the global
     * discount/reward defaults for power users (ambassadors, partners).
     *
     * Editable fields:
     *   discount_type / discount_value — what the friend gets at checkout
     *   reward_value                   — what the owner gets when paid
     *   max_uses                       — 0 = unlimited
     *   expires_at                     — null = never expires
     *   is_active                      — soft on/off
     */
    public function updateReferralCode(Request $request, ReferralCode $code)
    {
        $data = $request->validate([
            'discount_type'  => 'required|in:percent,fixed',
            'discount_value' => 'required|numeric|min:0',
            'reward_value'   => 'required|numeric|min:0',
            'max_uses'       => 'required|integer|min:0|max:1000000',
            'expires_at'     => 'nullable|date|after:now',
            'is_active'      => 'nullable|boolean',
        ]);
        $code->update([
            'discount_type'  => $data['discount_type'],
            'discount_value' => $data['discount_value'],
            'reward_value'   => $data['reward_value'],
            'max_uses'       => $data['max_uses'],
            'expires_at'     => $data['expires_at'] ?: null,
            'is_active'      => (bool) ($data['is_active'] ?? false),
        ]);
        return back()->with('success', "บันทึกรหัส {$code->code} แล้ว");
    }

    // ═══ Loyalty ════════════════════════════════════════════════════

    public function loyalty(Request $request, LoyaltyService $loyalty)
    {
        $q      = trim((string) $request->get('q', ''));
        $tier   = $request->get('tier', '');

        // User model is on `auth_users` with first_name/last_name (no `name` col).
        $query = LoyaltyAccount::query()
            ->with('user:id,first_name,last_name,email')
            ->orderByDesc('points_balance');
        if ($tier !== '') $query->where('tier', $tier);
        if ($q !== '') {
            $query->whereHas('user', function ($w) use ($q) {
                $w->where('first_name', 'ilike', "%$q%")
                  ->orWhere('last_name', 'ilike', "%$q%")
                  ->orWhere('email', 'ilike', "%$q%");
            });
        }
        $accounts = $query->paginate(30)->withQueryString();
        $summary = $loyalty->summary();

        $settings = [
            'earn_rate'           => (float) AppSetting::get('marketing_loyalty_earn_rate', 1),
            'redeem_rate'         => (float) AppSetting::get('marketing_loyalty_redeem_rate', 10),
            'min_redeem'          => (int)   AppSetting::get('marketing_loyalty_min_redeem', 100),
            'tier_silver_spend'   => (float) AppSetting::get('marketing_loyalty_tier_silver_spend', 3000),
            'tier_gold_spend'     => (float) AppSetting::get('marketing_loyalty_tier_gold_spend', 15000),
            'tier_platinum_spend' => (float) AppSetting::get('marketing_loyalty_tier_platinum_spend', 50000),
        ];

        return view('admin.marketing.loyalty', compact('accounts', 'summary', 'settings', 'q', 'tier'));
    }

    public function updateLoyaltySettings(Request $request)
    {
        $data = $request->validate([
            'earn_rate'           => 'required|numeric|min:0',
            'redeem_rate'         => 'required|numeric|min:0.01',
            'min_redeem'          => 'required|integer|min:0',
            'tier_silver_spend'   => 'required|numeric|min:0',
            'tier_gold_spend'     => 'required|numeric|min:0',
            'tier_platinum_spend' => 'required|numeric|min:0',
        ]);
        foreach ($data as $k => $v) {
            AppSetting::set('marketing_loyalty_' . $k, (string) $v);
        }
        return back()->with('success', 'บันทึก loyalty settings แล้ว');
    }

    public function adjustLoyalty(Request $request, LoyaltyService $loyalty)
    {
        $data = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'points'  => 'required|integer|not_in:0',
            'reason'  => 'nullable|string|max:64',
        ]);
        $tx = $loyalty->adjust($data['user_id'], (int) $data['points'], 'adjust', $data['reason'] ?? 'admin_adjust');
        return back()->with($tx ? 'success' : 'error', $tx ? "ปรับแต้ม {$data['points']} แล้ว" : 'ปรับแต้มไม่สำเร็จ');
    }

    // ═══════════════════════════════════════════════════════════
    //  PHASE 3 — Landing Pages
    // ═══════════════════════════════════════════════════════════

    public function landingPages(Request $request, LandingPageService $lp)
    {
        $status = $request->string('status')->trim();
        $q      = $request->string('q')->trim();

        $pages = LandingPage::query()
            ->when($status->isNotEmpty(), fn ($qry) => $qry->where('status', $status))
            ->when($q->isNotEmpty(), function ($qry) use ($q) {
                $qry->where(function ($w) use ($q) {
                    $w->where('title', 'ilike', "%{$q}%")
                      ->orWhere('slug', 'ilike', "%{$q}%");
                });
            })
            ->orderByDesc('updated_at')
            ->paginate(20)
            ->withQueryString();

        $summary = $lp->summary();
        $enabled = $this->marketing->enabled('landing_pages');

        return view('admin.marketing.landing.index', compact('pages', 'summary', 'enabled'));
    }

    public function createLandingPage()
    {
        $themes = LandingPage::THEMES;
        $blockTypes = \App\Services\Marketing\LandingPageService::BLOCK_TYPES;
        return view('admin.marketing.landing.create', compact('themes', 'blockTypes'));
    }

    public function storeLandingPage(Request $request, LandingPageService $lp)
    {
        $data = $request->validate([
            'title'      => 'required|string|max:160',
            'slug'       => 'nullable|string|max:120|regex:/^[a-z0-9\-]+$/i',
            'subtitle'   => 'nullable|string|max:300',
            'hero_image' => 'nullable|string|max:500',
            'theme'      => 'nullable|in:indigo,pink,emerald,amber,slate',
            'cta_label'  => 'nullable|string|max:80',
            'cta_url'    => 'nullable|url|max:500',
            'status'     => 'nullable|in:draft,published,archived',
            'sections'   => 'nullable|array',
            'seo_title'  => 'nullable|string|max:160',
            'seo_desc'   => 'nullable|string|max:500',
            'seo_og'     => 'nullable|string|max:500',
        ]);

        $sections = $lp->normalizeSections($data['sections'] ?? []);

        $page = $lp->create([
            'title'      => $data['title'],
            'slug'       => $data['slug'] ?? $data['title'],
            'subtitle'   => $data['subtitle'] ?? null,
            'hero_image' => $data['hero_image'] ?? null,
            'theme'      => $data['theme'] ?? 'indigo',
            'cta_label'  => $data['cta_label'] ?? null,
            'cta_url'    => $data['cta_url'] ?? null,
            'status'     => $data['status'] ?? 'draft',
            'sections'   => $sections,
            'seo'        => array_filter([
                'title'       => $data['seo_title']  ?? null,
                'description' => $data['seo_desc']   ?? null,
                'og_image'    => $data['seo_og']     ?? null,
            ]),
        ], auth('admin')->id());

        if ($page->status === 'published') {
            $lp->publish($page);
        }

        return redirect()->route('admin.marketing.landing.edit', $page)
            ->with('success', 'สร้าง Landing Page แล้ว');
    }

    public function editLandingPage(LandingPage $landingPage)
    {
        $themes = LandingPage::THEMES;
        $blockTypes = \App\Services\Marketing\LandingPageService::BLOCK_TYPES;
        return view('admin.marketing.landing.edit', [
            'page'       => $landingPage,
            'themes'     => $themes,
            'blockTypes' => $blockTypes,
        ]);
    }

    public function updateLandingPage(Request $request, LandingPage $landingPage, LandingPageService $lp)
    {
        $data = $request->validate([
            'title'      => 'required|string|max:160',
            'slug'       => 'nullable|string|max:120|regex:/^[a-z0-9\-]+$/i',
            'subtitle'   => 'nullable|string|max:300',
            'hero_image' => 'nullable|string|max:500',
            'theme'      => 'nullable|in:indigo,pink,emerald,amber,slate',
            'cta_label'  => 'nullable|string|max:80',
            'cta_url'    => 'nullable|url|max:500',
            'status'     => 'nullable|in:draft,published,archived',
            'sections'   => 'nullable|array',
            'seo_title'  => 'nullable|string|max:160',
            'seo_desc'   => 'nullable|string|max:500',
            'seo_og'     => 'nullable|string|max:500',
        ]);

        $sections = $lp->normalizeSections($data['sections'] ?? []);

        $lp->update($landingPage, [
            'title'      => $data['title'],
            'slug'       => $data['slug'] ?? $landingPage->slug,
            'subtitle'   => $data['subtitle'] ?? null,
            'hero_image' => $data['hero_image'] ?? null,
            'theme'      => $data['theme'] ?? 'indigo',
            'cta_label'  => $data['cta_label'] ?? null,
            'cta_url'    => $data['cta_url'] ?? null,
            'sections'   => $sections,
            'seo'        => array_filter([
                'title'       => $data['seo_title']  ?? null,
                'description' => $data['seo_desc']   ?? null,
                'og_image'    => $data['seo_og']     ?? null,
            ]),
        ]);

        if (($data['status'] ?? $landingPage->status) === 'published') {
            $lp->publish($landingPage);
        } elseif (($data['status'] ?? null) === 'archived') {
            $lp->archive($landingPage);
        } else {
            $landingPage->update(['status' => $data['status'] ?? $landingPage->status]);
        }

        return back()->with('success', 'บันทึกแล้ว');
    }

    public function deleteLandingPage(LandingPage $landingPage)
    {
        $landingPage->delete();
        return redirect()->route('admin.marketing.landing.index')->with('success', 'ลบ Landing Page แล้ว');
    }

    public function updateLandingSettings(Request $request)
    {
        $data = $request->validate([
            'landing_pages_enabled' => 'nullable|in:0,1',
            'lp_default_theme'      => 'nullable|in:indigo,pink,emerald,amber,slate',
        ]);
        foreach ($data as $k => $v) {
            AppSetting::set('marketing_' . $k, (string) $v);
        }
        return back()->with('success', 'บันทึกการตั้งค่า Landing Pages แล้ว');
    }

    // ═══════════════════════════════════════════════════════════
    //  PHASE 3 — Push Notifications
    // ═══════════════════════════════════════════════════════════

    public function push(Request $request, PushService $push)
    {
        $summary   = $push->summary();
        $settings  = $this->loadSettings([
            'push_enabled', 'push_vapid_public', 'push_vapid_private',
            'push_vapid_subject', 'push_prompt_delay', 'push_prompt_text',
        ]);
        $campaigns = PushCampaign::orderByDesc('id')->paginate(20)->withQueryString();

        return view('admin.marketing.push.index', compact('summary', 'settings', 'campaigns'));
    }

    public function updatePushSettings(Request $request)
    {
        $data = $request->validate([
            'push_enabled'        => 'nullable|in:0,1',
            'push_vapid_public'   => 'nullable|string|max:200',
            'push_vapid_private'  => 'nullable|string|max:200',
            'push_vapid_subject'  => 'nullable|string|max:160',
            'push_prompt_delay'   => 'nullable|integer|min:0|max:600',
            'push_prompt_text'    => 'nullable|string|max:200',
        ]);
        foreach ($data as $k => $v) {
            AppSetting::set('marketing_' . $k, (string) ($v ?? ''));
        }
        return back()->with('success', 'บันทึกการตั้งค่า Push แล้ว');
    }

    public function generateVapid(PushService $push)
    {
        try {
            [$public, $private] = $push->generateVapidKeys();
            AppSetting::set('marketing_push_vapid_public', $public);
            AppSetting::set('marketing_push_vapid_private', $private);
            return back()->with('success', 'สร้าง VAPID keys ใหม่แล้ว');
        } catch (\Throwable $e) {
            return back()->with('error', 'สร้าง VAPID ไม่สำเร็จ: ' . $e->getMessage());
        }
    }

    public function createPushCampaign()
    {
        return view('admin.marketing.push.create');
    }

    public function storePushCampaign(Request $request)
    {
        $data = $request->validate([
            'title'         => 'required|string|max:120',
            'body'          => 'required|string|max:500',
            'icon'          => 'nullable|string|max:500',
            'click_url'     => 'nullable|url|max:500',
            'segment'       => 'nullable|in:all,users,guests,tag',
            'segment_value' => 'nullable|string|max:120',
        ]);

        $data['author_id'] = auth('admin')->id();
        $data['status']    = 'draft';
        $campaign = PushCampaign::create($data);

        return redirect()->route('admin.marketing.push.index')
            ->with('success', "สร้าง Push campaign #{$campaign->id} แล้ว");
    }

    public function sendPushCampaign(PushCampaign $campaign, PushService $push)
    {
        if (! $this->marketing->enabled('push')) {
            return back()->with('error', 'Push ยังไม่เปิดใช้งาน');
        }
        if (! in_array($campaign->status, ['draft', 'failed'])) {
            return back()->with('error', 'ส่งไม่ได้ — สถานะปัจจุบัน: ' . $campaign->status);
        }

        $result = $push->send($campaign);
        return back()->with('success', "ส่งแล้ว — targets: {$result['targets']}, sent: {$result['sent']}, failed: {$result['failed']}");
    }

    public function deletePushCampaign(PushCampaign $campaign)
    {
        $campaign->delete();
        return back()->with('success', 'ลบ Push campaign แล้ว');
    }

    // ═══════════════════════════════════════════════════════════
    //  PHASE 3 — Analytics v2 Dashboard
    // ═══════════════════════════════════════════════════════════

    public function analyticsV2(Request $request, MarketingAnalyticsService $mk)
    {
        $days = (int) $request->input('days', 30);
        $days = max(7, min(90, $days));

        $from = now()->subDays($days - 1)->startOfDay();
        $to   = now()->endOfDay();

        $overview = $mk->overview();
        $funnel   = $mk->funnel([
            \App\Models\Marketing\MarketingEvent::EV_PAGE_VIEW,
            \App\Models\Marketing\MarketingEvent::EV_VIEW_PRODUCT,
            \App\Models\Marketing\MarketingEvent::EV_ADD_TO_CART,
            \App\Models\Marketing\MarketingEvent::EV_BEGIN_CHECKOUT,
            \App\Models\Marketing\MarketingEvent::EV_PURCHASE,
        ], $from, $to);

        $roas     = $mk->roas($from, $to);
        $cohort   = $mk->weeklyCohort(8);
        $ltv      = $mk->ltvBySource();
        $series   = [
            'page_view' => $mk->dailySeries(\App\Models\Marketing\MarketingEvent::EV_PAGE_VIEW, $days),
            'purchase'  => $mk->dailySeries(\App\Models\Marketing\MarketingEvent::EV_PURCHASE, $days),
        ];
        $enabled  = $mk->enabled();

        // ─── GA4 + Search Console widgets (Phase A3 + B1 + C1 + C2) ───
        // Optional admin marketing insights, only fetched when admin
        // configured Google APIs at /admin/settings/google-apis.
        // All return empty arrays when unconfigured / API down — view
        // renders "no data" state.
        $gaSvc = app(\App\Services\Google\GoogleAnalyticsService::class);
        $scSvc = app(\App\Services\Google\GoogleSearchConsoleService::class);
        $gaConfigured = $gaSvc->isConfigured();
        $scConfigured = $scSvc->isConfigured();

        $startDate = $days . 'daysAgo';
        $endDate   = 'today';

        $pagePerformance     = $gaConfigured ? $gaSvc->pagePerformance($startDate, $endDate, 10) : [];
        $deviceBreakdown     = $gaConfigured ? $gaSvc->deviceBreakdown($startDate, $endDate)     : [];
        $attributionTable    = $gaConfigured ? $gaSvc->attributionTable($startDate, $endDate)    : [];

        $scSummary    = $scConfigured ? $scSvc->summary($days)         : null;
        $scTopKeywords = $scConfigured ? $scSvc->topKeywords($days, null, 15) : [];
        $scTopPages    = $scConfigured ? $scSvc->topPages($days, 10)   : [];

        return view('admin.marketing.analytics-v2', compact(
            'overview', 'funnel', 'roas', 'cohort', 'ltv', 'series', 'days', 'enabled',
            'gaConfigured', 'scConfigured',
            'pagePerformance', 'deviceBreakdown', 'attributionTable',
            'scSummary', 'scTopKeywords', 'scTopPages'
        ));
    }

    public function updateAnalyticsSettings(Request $request)
    {
        $data = $request->validate([
            'analytics_enabled'       => 'nullable|in:0,1',
            'event_retention_days'    => 'nullable|integer|min:7|max:3650',
        ]);
        foreach ($data as $k => $v) {
            AppSetting::set('marketing_' . $k, (string) $v);
        }
        return back()->with('success', 'บันทึกการตั้งค่า Analytics แล้ว');
    }

    // ── Helpers ────────────────────────────────────────────────

    protected function loadSettings(array $keys): array
    {
        $out = [];
        foreach ($keys as $k) {
            $out[$k] = AppSetting::get('marketing_' . $k, '');
        }
        return $out;
    }

    /**
     * Quick metric snapshot for hub home.
     */
    protected function quickMetrics(): array
    {
        $out = [
            'subscribers' => 0,
            'utm_visits_7d' => 0,
            'campaigns_sent' => 0,
            'referral_codes' => 0,
            'loyalty_accounts' => 0,
            'landing_pages' => 0,
            'push_subs' => 0,
            'events_7d' => 0,
        ];
        try { $out['subscribers']      = DB::table('marketing_subscribers')->where('status', 'confirmed')->count(); } catch (\Throwable $e) {}
        try { $out['utm_visits_7d']    = DB::table('marketing_utm_attributions')->where('created_at', '>=', now()->subDays(7))->count(); } catch (\Throwable $e) {}
        try { $out['campaigns_sent']   = DB::table('marketing_campaigns')->where('status', 'sent')->count(); } catch (\Throwable $e) {}
        try { $out['referral_codes']   = DB::table('marketing_referral_codes')->where('is_active', 1)->count(); } catch (\Throwable $e) {}
        try { $out['loyalty_accounts'] = DB::table('marketing_loyalty_accounts')->count(); } catch (\Throwable $e) {}
        try { $out['landing_pages']    = DB::table('marketing_landing_pages')->where('status', 'published')->count(); } catch (\Throwable $e) {}
        try { $out['push_subs']        = DB::table('marketing_push_subscriptions')->where('status', 'active')->count(); } catch (\Throwable $e) {}
        try { $out['events_7d']        = DB::table('marketing_events')->where('occurred_at', '>=', now()->subDays(7))->count(); } catch (\Throwable $e) {}
        return $out;
    }
}

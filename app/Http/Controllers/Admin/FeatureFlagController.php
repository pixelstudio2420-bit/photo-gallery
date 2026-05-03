<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/**
 * Global Feature Flags admin page.
 *
 * Lets the platform admin kill-switch any subscription feature WITHOUT
 * editing each plan's ai_features array. The check chain at request time
 * is:
 *
 *   subs.canAccessFeature(profile, $f)
 *     = plan grants $f  AND  AppSetting::feature_<f>_enabled = '1'
 *
 * So a "1" here means "the feature is on for plans that grant it".
 * A "0" turns the feature off globally even for paid plans — useful
 * for incident response (e.g. "AWS Rekognition is down — disable
 * face_search until we redeploy").
 *
 * Active features default to '1' (on); deprecated features default to
 * '0' (off) so a fresh install gets a focused MVP without the long
 * tail of low-value functionality. The deprecated set is documented in
 * DEPRECATED_FEATURES below — flipping any of those to '1' brings the
 * code path back online (it's still wired, just gated).
 */
class FeatureFlagController extends Controller
{
    /**
     * Canonical list of features the admin can toggle.
     * Format: key => [label, group]
     *
     * NOTE: The order here drives admin-page card order, so keep groups
     * together. Adding a feature: add the row + register a sane default
     * in defaultFor() below.
     */
    public const FEATURES = [
        // ── AI features ────────────────────────────────────────────
        'face_search'         => ['ค้นหาด้วยใบหน้า (Face Search)', 'ai'],
        'quality_filter'      => ['คัดรูปเสียด้วย AI', 'ai'],
        'duplicate_detection' => ['ตรวจจับรูปซ้ำ', 'ai'],
        'auto_tagging'        => ['ติดแท็กอัตโนมัติ', 'ai'],
        'best_shot'           => ['เลือกช็อตเด็ด', 'ai'],
        'ai_preview_limited'  => ['AI Preview (จำกัด — Free tier)', 'ai'],
        'color_enhance'       => ['ปรับสีอัตโนมัติ (deprecated)', 'ai'],
        'smart_captions'      => ['Smart Captions / LLM (deprecated)', 'ai'],
        'video_thumbnails'    => ['Video Thumbnails / FFmpeg (deprecated)', 'ai'],

        // ── LINE Integration ──────────────────────────────────────
        // The LINE Messaging API connection itself is configured under
        // /admin/marketing → LINE channel access token + secret. These
        // flags gate which OUTBOUND flows are allowed once the
        // channel is connected. Each flag corresponds to a method
        // family on LineNotifyService:
        //   line_delivery          → pushDownloadLink / pushPhotos
        //   line_notify_admin      → notifyAdmin / notifyNewOrder etc.
        //   line_notify_customer   → pushOrderApproved / pushOrderRejected
        //   line_broadcast         → broadcastNewEvent (LINE OA push)
        //   line_lifecycle         → pushLifecycleMessage (welcome / cart-abandon)
        //   line_login             → LINE social login (also gated by
        //                            auth_social_line_enabled at the
        //                            SocialAuthService layer)
        'line_delivery'       => ['ส่งรูป/ลิงก์ดาวน์โหลดเข้า LINE หลังจ่ายเงิน', 'line'],
        'line_notify_admin'   => ['แจ้งยอด/ออเดอร์/สลิปเข้า LINE (admin)', 'line'],
        'line_notify_customer'=> ['แจ้งสถานะออเดอร์ให้ลูกค้าทาง LINE', 'line'],
        'line_broadcast'      => ['Broadcast อีเวนต์ใหม่ผ่าน LINE OA', 'line'],
        'line_lifecycle'      => ['Lifecycle messages (welcome / cart-abandon)', 'line'],
        'line_login'          => ['LINE Login (เข้าระบบด้วย LINE)', 'line'],

        // ── Workflow & Performance ────────────────────────────────
        'priority_upload'     => ['Priority Upload (Pro+ queue lane)', 'workflow'],
        'customer_analytics'  => ['Customer Analytics (Business+)', 'workflow'],
        'presets'             => ['Lightroom Presets (Starter+)', 'workflow'],

        // ── Branding & White-label ────────────────────────────────
        'custom_branding'     => ['Custom Branding (logo/สี/ลายน้ำ)', 'branding'],
        'white_label'         => ['White-label (ซ่อน "Powered by")', 'branding'],

        // ── Platform Features ─────────────────────────────────────
        'chat'                => ['ระบบแชทระหว่างลูกค้า ↔ ช่างภาพ', 'platform'],
        'sla_99_99'           => ['SLA 99.99% uptime (Studio plan)', 'platform'],
        'dedicated_csm'       => ['Dedicated CSM (Studio plan)', 'platform'],
        'team_seats'          => ['Team Members / Business 3 · Studio 10 (deprecated)', 'platform'],
        'api_access'          => ['Public API (v1 — Bearer tokens)', 'platform'],
        'chatbot'             => ['AI Chatbot widget (deprecated)', 'platform'],
    ];

    /**
     * Features whose default state is OFF — the product audit at
     * 2026-04-28 found these have <1% projected demand for the MVP
     * launch. They're kept in code (not deleted) so flipping the flag
     * brings them back without a redeploy. If after 12 months no one
     * has flipped them on, remove them and their code paths in v2.
     */
    public const DEPRECATED_FEATURES = [
        'color_enhance',
        'smart_captions',
        'video_thumbnails',
        'team_seats',
        // 'api_access' was previously in this list (gated for MVP).
        // Promoted to a regular platform feature on 2026-05-04 once the
        // /api/v1/photographer/* endpoints landed in V1\PhotographerApiController.
        // Default is now '1' (on) so fresh installs pick it up; admins
        // can still flip it off at /admin/features for incident response.
        'chatbot',
        // 'chat' is opt-in (default OFF) but NOT deprecated — admin
        // simply enables it from this page when ready. Listed here so
        // defaultFor('chat') returns '0' by default.
        'chat',
    ];

    /**
     * Default '1' for active features, '0' for deprecated. Used both by
     * the admin UI initial load AND by SubscriptionService::featureGloballyEnabled
     * so the two sides agree on what "no setting recorded yet" means.
     */
    public static function defaultFor(string $feature): string
    {
        return in_array($feature, self::DEPRECATED_FEATURES, true) ? '0' : '1';
    }

    /**
     * Per-feature display metadata used by photographer-facing views
     * (subscription dashboard, plan picker, promo, sell-photos).
     *
     * Returns an associative array keyed by feature code with values:
     *   [label, bootstrap-icon, group]
     *
     * Single source of truth so a label/icon change propagates to
     * every view automatically. The plan-picker / dashboard pages
     * pre-2026-05-02 each had their own hardcoded copy that drifted
     * out of sync with FEATURES — this method ends that drift.
     */
    public static function featureLabels(): array
    {
        return [
            // ── AI ────────────────────────────────────────────────
            'face_search'          => ['ค้นหาด้วยใบหน้า (AI)',      'bi-person-bounding-box', 'ai'],
            'quality_filter'       => ['คัดรูปเสียอัตโนมัติ',        'bi-funnel',              'ai'],
            'duplicate_detection'  => ['ตรวจจับรูปซ้ำ',             'bi-files',               'ai'],
            'auto_tagging'         => ['แท็กอัตโนมัติ',             'bi-tags',                'ai'],
            'best_shot'            => ['เลือกช็อตเด็ด',             'bi-trophy',              'ai'],
            'ai_preview_limited'   => ['AI Preview (จำกัด)',         'bi-eye',                 'ai'],
            'color_enhance'        => ['ปรับสีอัตโนมัติ',           'bi-palette2',            'ai'],
            'smart_captions'       => ['Smart Captions',           'bi-chat-quote',          'ai'],
            'video_thumbnails'     => ['Video Thumbnails',         'bi-play-btn',            'ai'],
            // ── LINE ──────────────────────────────────────────────
            'line_delivery'        => ['ส่งรูปเข้า LINE หลังจ่าย',   'bi-line',                'line'],
            'line_notify_admin'    => ['แจ้งยอด/ออเดอร์เข้า LINE',  'bi-bell-fill',           'line'],
            'line_notify_customer' => ['แจ้งสถานะออเดอร์ใน LINE',   'bi-chat-dots-fill',      'line'],
            'line_broadcast'       => ['Broadcast LINE OA',        'bi-broadcast',           'line'],
            'line_lifecycle'       => ['Lifecycle LINE messages',  'bi-clock-history',       'line'],
            'line_login'           => ['LINE Login',               'bi-line',                'line'],
            // ── Workflow ──────────────────────────────────────────
            'priority_upload'      => ['อัปโหลดด่วน 2x',            'bi-lightning-charge',    'workflow'],
            'customer_analytics'   => ['Analytics ลูกค้า',          'bi-graph-up',            'workflow'],
            'presets'              => ['Lightroom Presets',        'bi-sliders',             'workflow'],
            // ── Branding ─────────────────────────────────────────
            'custom_branding'      => ['Custom Branding',          'bi-palette',             'branding'],
            'white_label'          => ['White-label',              'bi-incognito',           'branding'],
            // ── Platform ─────────────────────────────────────────
            'chat'                 => ['ระบบแชทกับลูกค้า',          'bi-chat-left-dots',      'platform'],
            'sla_99_99'            => ['SLA 99.99% uptime',        'bi-shield-check',        'platform'],
            'dedicated_csm'        => ['Dedicated CSM',            'bi-person-badge',        'platform'],
            'team_seats'           => ['Team Members',             'bi-people',              'platform'],
            'api_access'           => ['Public API',               'bi-key',                 'platform'],
            'chatbot'              => ['AI Chatbot',               'bi-robot',               'platform'],
        ];
    }

    public function index(): View
    {
        $flags = [];
        foreach (array_keys(self::FEATURES) as $key) {
            $flags[$key] = (string) AppSetting::get('feature_'.$key.'_enabled', self::defaultFor($key)) === '1';
        }

        // Group features for the UI.
        $grouped = [];
        foreach (self::FEATURES as $key => [$label, $group]) {
            $grouped[$group] ??= [];
            $grouped[$group][] = [
                'key'   => $key,
                'label' => $label,
                'on'    => $flags[$key],
            ];
        }

        return view('admin.features.index', [
            'grouped' => $grouped,
            'groupLabels' => [
                'ai'        => 'AI Features',
                'line'      => 'LINE Integration',
                'workflow'  => 'Workflow & Performance',
                'branding'  => 'Branding & White-label',
                'platform'  => 'Platform Features',
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $submitted = (array) $request->input('flags', []); // array of "feature_xxx" keys that are on
        $changes = 0;

        foreach (array_keys(self::FEATURES) as $key) {
            $newVal = in_array($key, $submitted, true) ? '1' : '0';
            $oldVal = (string) AppSetting::get('feature_'.$key.'_enabled', self::defaultFor($key));
            if ($newVal !== $oldVal) {
                AppSetting::set('feature_'.$key.'_enabled', $newVal);
                $changes++;
            }
        }

        // Bust the AppSetting cache so the new flags take effect immediately.
        Cache::forget('app_settings_all');

        return back()->with('success', $changes > 0
            ? "อัปเดต feature flags เรียบร้อย ({$changes} รายการ) — มีผลทันที"
            : 'ไม่มีการเปลี่ยนแปลง'
        );
    }
}

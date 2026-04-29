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
        // AI features
        'face_search'         => ['ค้นหาด้วยใบหน้า (Face Search)', 'ai'],
        'quality_filter'      => ['คัดรูปเสียด้วย AI', 'ai'],
        'duplicate_detection' => ['ตรวจจับรูปซ้ำ', 'ai'],
        'auto_tagging'        => ['ติดแท็กอัตโนมัติ', 'ai'],
        'best_shot'           => ['เลือกช็อตเด็ด', 'ai'],
        'color_enhance'       => ['ปรับสีอัตโนมัติ (deprecated)', 'ai'],
        'smart_captions'      => ['Smart Captions / LLM (deprecated)', 'ai'],
        'video_thumbnails'    => ['Video Thumbnails / FFmpeg (deprecated)', 'ai'],
        // Workflow features
        'priority_upload'     => ['Priority Upload (Pro+ queue lane)', 'workflow'],
        'customer_analytics'  => ['Customer Analytics (Business+)', 'workflow'],
        'presets'             => ['Lightroom Presets (Starter+)', 'workflow'],
        // Branding features
        'custom_branding'     => ['Custom Branding (logo/สี/ลายน้ำ)', 'branding'],
        'white_label'         => ['White-label (ซ่อน "Powered by")', 'branding'],
        // Platform features
        'team_seats'          => ['Team Members / Business 3 · Studio 10 (deprecated)', 'platform'],
        'api_access'          => ['Public API / Studio plan (deprecated)', 'platform'],
        'chatbot'             => ['AI Chatbot widget (deprecated)', 'platform'],
        'chat'                => ['ระบบแชทระหว่างลูกค้า ↔ ช่างภาพ', 'platform'],
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
        'api_access',
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

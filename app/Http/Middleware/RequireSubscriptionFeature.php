<?php

namespace App\Http\Middleware;

use App\Models\PhotographerProfile;
use App\Services\SubscriptionService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate an endpoint on a specific subscription feature.
 *
 * Usage (in routes):
 *   Route::post('/ai/face-search', …)
 *       ->middleware(['photographer.auth', 'subscription.feature:face_search']);
 *
 * Lookup chain:
 *   1. request attribute photographer_profile (cached by upstream middleware)
 *   2. PhotographerProfile by user_id
 *   3. refuse 403
 *
 * If the subscription system is globally disabled, middleware passes through
 * (we don't want to block existing commission-mode photographers from AI
 * endpoints that might later get added to them for free).
 *
 * Features come from SubscriptionPlan.ai_features (JSON array):
 *   face_search, quality_filter, duplicate_detection, auto_tagging,
 *   best_shot, priority_upload, …
 *
 * Response shape mirrors EnforceStorageQuota — 402 Payment Required with a
 * JSON body that the frontend can render as an upgrade CTA. 402 is the right
 * code semantically: "the request is otherwise valid, but requires payment".
 */
class RequireSubscriptionFeature
{
    public function __construct(private SubscriptionService $subs) {}

    public function handle(Request $request, Closure $next, string $feature): Response
    {
        // If subscriptions are globally disabled, let everything through.
        // Admins can toggle this to fall back to the old behaviour.
        if (!$this->subs->systemEnabled()) {
            return $next($request);
        }

        $userId = Auth::id();
        if (!$userId) {
            // Let the auth middleware raise its own error.
            return $next($request);
        }

        $profile = $request->attributes->get('photographer_profile')
            ?? PhotographerProfile::where('user_id', $userId)->first();
        if (!$profile) {
            // Not a photographer — photographer.auth middleware will handle it.
            return $next($request);
        }

        $request->attributes->set('photographer_profile', $profile);

        if ($this->subs->canAccessFeature($profile, $feature)) {
            return $next($request);
        }

        $currentPlan = $this->subs->currentPlan($profile);

        return $this->refuse($request, $feature, $currentPlan->code ?? 'free');
    }

    private function refuse(Request $request, string $feature, string $currentPlanCode): Response
    {
        $featureLabel = $this->featureLabel($feature);
        $message = "ฟีเจอร์ {$featureLabel} ต้องใช้แผนแบบรายเดือน กรุณาอัพเกรดแผนของคุณเพื่อเปิดใช้งาน";

        if ($request->expectsJson() || $request->is('api/*') || $request->ajax()) {
            return response()->json([
                'success'      => false,
                'message'      => $message,
                'feature'      => $feature,
                'current_plan' => $currentPlanCode,
                'upgrade_url'  => route('photographer.subscription.plans', [], false),
            ], 402);
        }

        return redirect()
            ->route('photographer.subscription.plans')
            ->with('subscription_feature_block', [
                'feature' => $feature,
                'message' => $message,
            ]);
    }

    /** Pretty Thai label for the feature, for the upgrade prompt. */
    private function featureLabel(string $feature): string
    {
        return match ($feature) {
            'face_search'         => 'ค้นหาด้วยใบหน้า (Face Search AI)',
            'quality_filter'      => 'คัดรูปเสียด้วย AI',
            'duplicate_detection' => 'ตรวจจับรูปซ้ำ',
            'auto_tagging'        => 'ติดแท็กอัตโนมัติ',
            'best_shot'           => 'เลือกช็อตเด็ด (Best Shot)',
            'priority_upload'     => 'อัพโหลดด่วน',
            default               => $feature,
        };
    }
}

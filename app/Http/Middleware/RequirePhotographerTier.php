<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use App\Models\PhotographerProfile;

/**
 * Tier-gate for photographer actions.
 *
 * The outer {@see PhotographerAuth} middleware decides "can this user log in to
 * the dashboard at all" (i.e. not blocked). This one decides "is their tier
 * high enough to perform THIS specific action?" — publishing a sellable event
 * needs seller+, requesting a payout needs seller+, etc.
 *
 * Why split them: we want a brand-new signup (tier=creator) to land on a fully
 * functional dashboard where they can upload + create drafts, and only hit
 * friendly "กรอก PromptPay เพื่อเริ่มขาย" prompts at the moment they try to do
 * something that would actually involve money moving. No dead-end screens, no
 * admin approval queue for the basics.
 *
 * Usage in routes:
 *   ->middleware('photographer.tier:seller')   // publishing / selling
 *   ->middleware('photographer.tier:pro')      // unlocked caps / verified badge
 *
 * Default (no param) is 'seller' — the common case for money-moving routes.
 *
 * Response contract:
 *   - AJAX/JSON → 403 with a structured payload the frontend can branch on
 *     (status=tier_required, current, required, upgrade_url).
 *   - HTML → redirect to profile/setup-bank page (the "next step" CTA) with a
 *     flash message explaining which field to fill.
 *
 * We deliberately don't hard-403 on HTML: a generic 403 screen tells the user
 * nothing. Redirecting them to the form that unlocks the tier is the whole
 * point of the tier system — it turns permission errors into guided upsells.
 */
class RequirePhotographerTier
{
    /**
     * @param  string  $required  Minimum tier (creator|seller|pro). Defaults to seller.
     */
    public function handle(Request $request, Closure $next, string $required = PhotographerProfile::TIER_SELLER): Response
    {
        // PhotographerAuth should have already enforced login, but be defensive:
        // if this middleware is ever applied without that one, don't 500 — bounce
        // to login the same way.
        if (!Auth::check()) {
            return redirect()->route('photographer.login')
                ->with('warning', 'กรุณาเข้าสู่ระบบก่อน');
        }

        $profile = Auth::user()->photographerProfile;

        if (!$profile) {
            return redirect()->route('photographer.register')
                ->with('info', 'กรุณาสมัครเป็นช่างภาพก่อน');
        }

        // Normalise the argument so a typo in the route (e.g. 'Seller') doesn't
        // silently fail-open. Unknown tiers fall back to seller — the safer
        // default for money-moving routes.
        $required = strtolower($required);
        if (!in_array($required, [
            PhotographerProfile::TIER_CREATOR,
            PhotographerProfile::TIER_SELLER,
            PhotographerProfile::TIER_PRO,
        ], true)) {
            $required = PhotographerProfile::TIER_SELLER;
        }

        // Admin-controlled: when Pro tier is disabled globally, downgrade
        // any `pro` requirement to `seller` so no feature 403's into a
        // dead-end. Once admin re-enables Pro, the original gating returns
        // without a code change.
        if ($required === PhotographerProfile::TIER_PRO
            && !PhotographerProfile::isProTierEnabled()) {
            $required = PhotographerProfile::TIER_SELLER;
        }

        // Before deciding the request has insufficient tier, make sure the
        // stored tier actually reflects current field state. Cheap insurance
        // against stale rows (e.g. admin just nulled out a PromptPay number,
        // or a photographer who JUST saved their PromptPay in the previous
        // request). syncTier() only writes if the value changes.
        $profile->syncTier();

        if ($profile->canReach($required)) {
            return $next($request);
        }

        // --- Gate failed: build a contextual CTA -------------------------------

        [$redirectRoute, $ctaMessage] = $this->upgradeHint($profile, $required);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'status'      => 'tier_required',
                'message'     => $ctaMessage,
                'current'     => $profile->tier,
                'required'    => $required,
                'upgrade_url' => $redirectRoute ? route($redirectRoute) : null,
            ], 403);
        }

        return redirect()
            ->route($redirectRoute ?? 'photographer.dashboard')
            ->with('warning', $ctaMessage);
    }

    /**
     * Pick the most useful "next step" page based on which field the photographer
     * is missing. We prefer the PromptPay form over the bank form because the
     * former is what actually unlocks Seller — the bank page is legacy.
     *
     * Returns [routeName, userFacingMessage].
     */
    private function upgradeHint(PhotographerProfile $profile, string $required): array
    {
        // Climbing to Seller — they're missing PromptPay.
        if ($required === PhotographerProfile::TIER_SELLER) {
            return [
                'photographer.setup-bank',
                'เพิ่มหมายเลข PromptPay ในโปรไฟล์เพื่อเริ่มขายผลงานและรับเงินอัตโนมัติ',
            ];
        }

        // Climbing to Pro — admin-only promotion. We no longer show an
        // in-app upload form (the ID-card + contract flow was retired on
        // 2026-04-25); direct them to contact an admin instead.
        if ($required === PhotographerProfile::TIER_PRO) {
            return [
                'photographer.profile',
                'ฟีเจอร์นี้ต้องใช้ระดับ Pro — ติดต่อแอดมินเพื่อขออนุมัติเลื่อนระดับ',
            ];
        }

        // Shouldn't actually happen (creator is the floor — everyone canReach it),
        // but keep a clean fallback so we never leak a raw 403 screen.
        return [
            'photographer.dashboard',
            'สิทธิ์ไม่เพียงพอสำหรับการดำเนินการนี้',
        ];
    }
}

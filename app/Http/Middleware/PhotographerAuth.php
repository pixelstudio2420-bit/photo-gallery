<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate the photographer area (/photographer/*).
 *
 * Historically this middleware blocked every photographer whose status
 * wasn't exactly 'approved' — which was the old all-or-nothing model
 * where admins had to manually approve people before they could do
 * ANYTHING, including just browsing their own dashboard.
 *
 * The new tier system decouples "can you log in at all" (here) from
 * "can you do a specific action" (RequirePhotographerTier). Any profile
 * that isn't explicitly rejected/suspended/banned is welcome into the
 * dashboard — selling, publishing, and receiving payouts are gated at
 * the action level by the tier middleware instead.
 *
 *   creator tier → lands on dashboard with a "complete your profile"
 *                  nudge; can upload, create drafts
 *   seller tier  → plus publish + sell
 *   pro tier     → no caps
 *
 * Blocked statuses still bounce out — the point of admin suspension is
 * to disable access outright, not to downgrade a tier.
 */
class PhotographerAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::check()) {
            return redirect()->route('photographer.login')
                ->with('warning', 'กรุณาเข้าสู่ระบบก่อน');
        }

        $user = Auth::user();
        $profile = $user->photographerProfile;

        if (!$profile) {
            return redirect()->route('photographer.register')
                ->with('info', 'กรุณาสมัครเป็นช่างภาพก่อน');
        }

        // Hard blocks: admin has actively disabled this account. Don't care
        // about tier here — a suspended pro should still be kicked out.
        if ($profile->isBlocked()) {
            Auth::logout();
            $reason = $profile->rejection_reason
                ? "บัญชีของคุณถูกระงับ: {$profile->rejection_reason}"
                : 'บัญชีช่างภาพของคุณถูกระงับการใช้งาน กรุณาติดต่อผู้ดูแลระบบ';
            return redirect()->route('photographer.login')->with('warning', $reason);
        }

        // Keep the tier value in sync with the fields the photographer has
        // actually filled in. Cheap (one SELECT, at most one UPDATE) and
        // ensures tier-gated routes downstream see fresh state — otherwise
        // a photographer who just added their PromptPay number would still
        // be 'creator' until a background job caught up.
        $profile->syncTier();

        // Resolve the current plan once per request so the layout's top
        // bar, sidebar, and any partial can show a plan-aware icon
        // without each one re-querying the SubscriptionService. We swallow
        // failures (e.g. plans table missing in a fresh install) and fall
        // back to the seeded free plan.
        $currentPlan = null;
        try {
            $currentPlan = app(\App\Services\SubscriptionService::class)->currentPlan($profile);
        } catch (\Throwable $e) {
            $currentPlan = \App\Models\SubscriptionPlan::defaultFree();
        }

        // Share photographer profile + plan to all views
        view()->share('photographer', $profile);
        view()->share('photographerPlan', $currentPlan);
        view()->share('photographerPlanIcon', $currentPlan?->iconClass() ?? 'bi-camera');
        view()->share('photographerPlanAccent', $currentPlan?->accentHex() ?? '#7c3aed');

        return $next($request);
    }
}

<?php

namespace App\Http\Controllers\Photographer;

use App\Http\Controllers\Controller;
use App\Models\PhotographerProfile;
use App\Models\PhotographerSubscription;
use App\Models\SubscriptionInvoice;
use App\Models\SubscriptionPlan;
use App\Services\SubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * Photographer-facing subscription UI.
 *
 * Screens:
 *   GET  /photographer/subscription            → dashboard (plan + usage + renewal)
 *   GET  /photographer/subscription/plans      → plan picker
 *   POST /photographer/subscription/subscribe  → create sub + order + redirect to pay
 *   POST /photographer/subscription/cancel     → cancel_at_period_end = true
 *   POST /photographer/subscription/resume     → undo cancel
 *   POST /photographer/subscription/change     → upgrade immediately or defer downgrade
 *   GET  /photographer/subscription/invoices   → billing history
 *
 * Flow mirrors the credit-package buy flow — we create an Order with
 * order_type='subscription' + subscription_invoice_id, then redirect to
 * payment.checkout. Webhook calls back into SubscriptionService::
 * activateFromPaidInvoice to flip sub status → active + refresh quota cache.
 */
class SubscriptionController extends Controller
{
    public function __construct(private SubscriptionService $subs) {}

    // ─────────────────────────────────────────────────────────────────────
    // Dashboard
    // ─────────────────────────────────────────────────────────────────────

    public function index(): View
    {
        $profile = $this->profile();
        $summary = $this->subs->dashboardSummary($profile);

        $invoices = SubscriptionInvoice::query()
            ->where('photographer_id', $profile->user_id)
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        return view('photographer.subscription.index', [
            'profile'  => $profile,
            'summary'  => $summary,
            'invoices' => $invoices,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Plan picker
    // ─────────────────────────────────────────────────────────────────────

    public function plans(): View
    {
        $profile = $this->profile();
        $summary = $this->subs->dashboardSummary($profile);

        $plans = SubscriptionPlan::active()->public()->ordered()->get();

        return view('photographer.subscription.plans', [
            'profile'  => $profile,
            'summary'  => $summary,
            'plans'    => $plans,
            'currentCode' => $summary['plan']?->code,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Subscribe — creates sub + invoice + order, redirects to checkout
    // ─────────────────────────────────────────────────────────────────────

    public function subscribe(Request $request, string $code): RedirectResponse
    {
        if (!$this->subs->systemEnabled()) {
            return back()->with('error', 'ระบบสมัครสมาชิกปิดใช้งานชั่วคราว กรุณาลองใหม่ภายหลัง');
        }

        $plan = SubscriptionPlan::active()->byCode($code)->first();
        if (!$plan) {
            return back()->with('error', 'ไม่พบแผนสมัครสมาชิกที่เลือก');
        }

        $profile = $this->profile();
        $annual  = (bool) $request->boolean('annual');

        try {
            $sub = $this->subs->subscribe(
                $profile,
                $plan,
                $request->input('payment_method', 'omise'),
                $annual
            );
        } catch (\Throwable $e) {
            Log::error('SubscriptionController::subscribe failed', [
                'user_id' => Auth::id(),
                'plan'    => $code,
                'error'   => $e->getMessage(),
            ]);
            return back()->with('error', 'ไม่สามารถสมัครสมาชิกได้ กรุณาลองใหม่อีกครั้ง');
        }

        // Free plan → already activated, go to dashboard.
        if ($plan->isFree()) {
            return redirect()
                ->route('photographer.subscription.index')
                ->with('success', "เปิดใช้งานแผน {$plan->name} แล้ว");
        }

        // Paid plan → jump to checkout for the generated pending invoice order.
        $order = $sub->invoices()->latest('id')->first()?->order;
        if (!$order) {
            // Should never happen — subscribe() always creates both.
            Log::error('SubscriptionController::subscribe — no order created', [
                'sub_id' => $sub->id,
            ]);
            return back()->with('error', 'สร้างคำสั่งซื้อไม่สำเร็จ กรุณาติดต่อผู้ดูแล');
        }

        return redirect()
            ->route('payment.checkout', ['order' => $order->id])
            ->with('success', "สมัคร {$plan->name} แล้ว — กรุณาชำระเงินเพื่อเปิดใช้งาน");
    }

    // ─────────────────────────────────────────────────────────────────────
    // Cancel / resume
    // ─────────────────────────────────────────────────────────────────────

    public function cancel(Request $request): RedirectResponse
    {
        $profile = $this->profile();
        $sub     = $this->subs->currentSubscription($profile);

        if (!$sub || !$sub->isUsable()) {
            return back()->with('error', 'ไม่พบการสมัครสมาชิกที่ใช้งานอยู่');
        }

        $this->subs->cancel($sub, immediate: false);

        return redirect()
            ->route('photographer.subscription.index')
            ->with('success', 'ยกเลิกการต่ออายุเรียบร้อย คุณจะยังใช้งานได้จนถึงสิ้นรอบบิลปัจจุบัน');
    }

    public function resume(Request $request): RedirectResponse
    {
        $profile = $this->profile();
        $sub     = $this->subs->currentSubscription($profile);

        if (!$sub || $sub->status !== PhotographerSubscription::STATUS_ACTIVE) {
            return back()->with('error', 'ไม่พบการสมัครสมาชิกที่สามารถกู้คืนได้');
        }

        $this->subs->resume($sub);

        return redirect()
            ->route('photographer.subscription.index')
            ->with('success', 'กู้คืนการต่ออายุเรียบร้อย');
    }

    // ─────────────────────────────────────────────────────────────────────
    // Change plan (upgrade / downgrade)
    // ─────────────────────────────────────────────────────────────────────

    public function change(Request $request, string $code): RedirectResponse
    {
        $profile = $this->profile();
        $sub     = $this->subs->currentSubscription($profile);

        if (!$sub || !$sub->isUsable()) {
            return back()->with('error', 'ไม่พบการสมัครสมาชิกที่ใช้งานอยู่');
        }

        $newPlan = SubscriptionPlan::active()->byCode($code)->first();
        if (!$newPlan) {
            return back()->with('error', 'ไม่พบแผนที่เลือก');
        }

        if ($sub->plan_id === $newPlan->id) {
            return back()->with('info', 'คุณอยู่ในแผนนี้แล้ว');
        }

        try {
            $result = $this->subs->changePlan($sub, $newPlan, prorateImmediately: true);
        } catch (\Throwable $e) {
            Log::error('SubscriptionController::change failed', [
                'user_id' => Auth::id(),
                'plan'    => $code,
                'error'   => $e->getMessage(),
            ]);
            return back()->with('error', 'ไม่สามารถเปลี่ยนแผนได้ กรุณาลองใหม่อีกครั้ง');
        }

        // Downgrade or microscopic prorated diff → no charge today; renewal
        // job will swap the plan at period end.
        if ($result['type'] === 'deferred') {
            return redirect()
                ->route('photographer.subscription.index')
                ->with('success', "บันทึกการเปลี่ยนแผนเป็น {$newPlan->name} แล้ว — มีผลในรอบบิลถัดไป");
        }

        // Same plan no-op (defensive, also handled above)
        if ($result['type'] === 'noop' || empty($result['order'])) {
            return back()->with('info', 'คุณอยู่ในแผนนี้แล้ว');
        }

        // Upgrade with prorated charge → checkout for the difference.
        return redirect()
            ->route('payment.checkout', ['order' => $result['order']->id])
            ->with('success', "เปลี่ยนแผนเป็น {$newPlan->name} — กรุณาชำระเงินส่วนต่างเพื่อเปิดใช้งาน");
    }

    // ─────────────────────────────────────────────────────────────────────
    // Billing history
    // ─────────────────────────────────────────────────────────────────────

    public function invoices(Request $request): View
    {
        $profile = $this->profile();

        $invoices = SubscriptionInvoice::query()
            ->where('photographer_id', $profile->user_id)
            ->with(['order', 'subscription.plan'])
            ->orderByDesc('id')
            ->paginate(20);

        return view('photographer.subscription.invoices', [
            'profile'  => $profile,
            'invoices' => $invoices,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────

    private function profile(): PhotographerProfile
    {
        $profile = Auth::user()?->photographerProfile;
        abort_unless($profile instanceof PhotographerProfile, 403, 'ไม่พบโปรไฟล์ช่างภาพ');
        return $profile;
    }
}

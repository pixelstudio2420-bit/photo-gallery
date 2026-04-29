<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\StoragePlan;
use App\Models\UserStorageInvoice;
use App\Services\UserStorageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Authenticated consumer-side subscription management:
 *
 *   GET  /storage                     → dashboard (usage + current plan)
 *   GET  /storage/plans               → plan picker / upgrade screen
 *   POST /storage/subscribe/{code}    → start a subscription (routes to checkout)
 *   POST /storage/cancel              → mark cancel_at_period_end
 *   POST /storage/resume              → undo a pending cancellation
 *   POST /storage/change/{code}       → upgrade (immediate) / schedule downgrade
 *   GET  /storage/invoices            → billing history
 *
 * All actions require the storage system to be enabled; when off, the
 * route group's middleware short-circuits to 404.
 */
class StorageSubscriptionController extends Controller
{
    public function __construct(private UserStorageService $svc) {}

    public function index(): View
    {
        $user     = Auth::user();
        $summary  = $this->svc->dashboardSummary($user);

        return view('storage.dashboard', [
            'summary' => $summary,
        ]);
    }

    public function plans(): View
    {
        $plans    = StoragePlan::active()->public()->ordered()->get();
        $summary  = $this->svc->dashboardSummary(Auth::user());

        return view('storage.plans', [
            'plans'   => $plans,
            'summary' => $summary,
        ]);
    }

    public function subscribe(string $code): RedirectResponse
    {
        $plan = StoragePlan::findByCode($code);
        if (!$plan || !$plan->is_active) {
            return back()->with('error', 'ไม่พบแผนที่เลือก');
        }

        if (!$this->svc->salesModeEnabled() && !$plan->isFree()) {
            return back()->with('error', 'ขณะนี้ยังไม่เปิดขายแผนแบบชำระเงิน');
        }

        $user = Auth::user();
        $sub  = $this->svc->subscribe($user, $plan);

        if ($plan->isFree()) {
            return redirect()->route('storage.index')
                ->with('success', 'เปิดใช้แผน Free เรียบร้อย');
        }

        // Paid plan — route to checkout via the Order that was created.
        $invoice = $sub->invoices()->latest('id')->first();
        if ($invoice && $invoice->order_id) {
            return redirect()->route('payment.checkout', ['order' => $invoice->order_id])
                ->with('success', 'สร้างคำสั่งซื้อเรียบร้อย รอชำระเงิน');
        }

        return redirect()->route('storage.index')
            ->with('success', 'สมัครสมาชิกเรียบร้อย');
    }

    public function cancel(): RedirectResponse
    {
        $sub = $this->svc->currentSubscription(Auth::user());
        if (!$sub || $sub->plan?->isFree()) {
            return back()->with('error', 'ไม่มีแผนที่ยกเลิกได้');
        }
        $this->svc->cancel($sub);
        return back()->with('success', 'ยกเลิกแผนเรียบร้อย — ใช้งานได้จนถึงสิ้นรอบบิล');
    }

    public function resume(): RedirectResponse
    {
        $sub = $this->svc->currentSubscription(Auth::user());
        if (!$sub) return back();
        $this->svc->resume($sub);
        return back()->with('success', 'กู้คืนแผนเรียบร้อย');
    }

    public function change(string $code): RedirectResponse
    {
        $plan = StoragePlan::findByCode($code);
        if (!$plan || !$plan->is_active) {
            return back()->with('error', 'ไม่พบแผนที่เลือก');
        }

        $sub = $this->svc->currentSubscription(Auth::user());
        if (!$sub) {
            return redirect()->route('storage.subscribe', ['code' => $code]);
        }

        // changePlan() now returns a structured result. Three possible
        // outcomes:
        //   noop     → already on this plan, just bounce back
        //   deferred → downgrade scheduled / sub-20 THB upgrade
        //   order    → upgrade with prorated invoice — go pay
        $result = $this->svc->changePlan($sub, $plan);

        if (($result['type'] ?? null) === 'noop') {
            return back()->with('info', 'คุณใช้แผนนี้อยู่แล้ว');
        }
        if (($result['type'] ?? null) === 'order' && !empty($result['order'])) {
            return redirect()
                ->route('payment.checkout', ['order' => $result['order']->id])
                ->with('success', 'อัปเกรดแผน — กรุณาชำระส่วนต่างเพื่อยืนยัน');
        }
        // Deferred — schedule will pick it up at renewal.
        return back()->with('success', 'เปลี่ยนแผนเรียบร้อย — มีผลในรอบบิลถัดไป');
    }

    public function invoices(Request $request): View
    {
        $invoices = UserStorageInvoice::where('user_id', Auth::id())
            ->with('subscription.plan', 'order')
            ->orderByDesc('id')
            ->paginate(20);

        return view('storage.invoices', [
            'invoices' => $invoices,
        ]);
    }
}

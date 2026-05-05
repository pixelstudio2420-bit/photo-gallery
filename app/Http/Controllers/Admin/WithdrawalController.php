<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\WithdrawalRequest;
use App\Services\LineNotifyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * Admin-side withdrawal queue + actions.
 *
 *   GET  /admin/payments/withdrawals       → queue list (filterable)
 *   GET  /admin/payments/withdrawals/{id}  → detail
 *   POST /admin/payments/withdrawals/{id}/approve   → mark approved
 *   POST /admin/payments/withdrawals/{id}/reject    → mark rejected (req'd reason)
 *   POST /admin/payments/withdrawals/{id}/mark-paid → terminal: paid (with slip)
 *
 *   GET  /admin/payments/withdrawals/settings — show + save settings page
 *   POST /admin/payments/withdrawals/settings — update admin-tunable rules
 */
class WithdrawalController extends Controller
{
    /** Listing — with status filter + KPI counts. */
    public function index(Request $request): View
    {
        $status = $request->query('status', 'pending');
        $valid  = ['all', 'pending', 'approved', 'paid', 'rejected', 'cancelled'];
        if (!in_array($status, $valid, true)) $status = 'pending';

        $q = WithdrawalRequest::with(['photographer:id,first_name,last_name,email', 'reviewedBy:id,first_name,last_name'])
            ->orderByDesc('id');
        if ($status !== 'all') $q->where('status', $status);

        $requests = $q->paginate(25)->withQueryString();

        // Per-status KPI for the filter chips
        $counts = WithdrawalRequest::selectRaw('status, COUNT(*) AS c, COALESCE(SUM(amount_thb), 0) AS amt')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $kpi = [
            'pending_count'    => (int) ($counts['pending']->c    ?? 0),
            'pending_amount'   => (float) ($counts['pending']->amt   ?? 0),
            'approved_count'   => (int) ($counts['approved']->c   ?? 0),
            'approved_amount'  => (float) ($counts['approved']->amt  ?? 0),
            'paid_month_count' => (int) WithdrawalRequest::paid()
                ->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])
                ->count(),
            'paid_month_amount'=> (float) WithdrawalRequest::paid()
                ->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])
                ->sum('amount_thb'),
        ];

        return view('admin.payments.withdrawals.index', [
            'requests' => $requests,
            'status'   => $status,
            'kpi'      => $kpi,
        ]);
    }

    /** Detail page — full info + action buttons. */
    public function show(int $id): View
    {
        $req = WithdrawalRequest::with(['photographer', 'reviewedBy'])->findOrFail($id);
        return view('admin.payments.withdrawals.show', ['req' => $req]);
    }

    /** Move pending → approved (admin acknowledges intent to pay). */
    public function approve(Request $request, int $id): RedirectResponse
    {
        $req = WithdrawalRequest::findOrFail($id);
        if (!$req->isPending()) {
            return back()->with('error', 'ทำได้เฉพาะรายการที่รอตรวจสอบ');
        }

        $req->update([
            'status'                => WithdrawalRequest::STATUS_APPROVED,
            'admin_note'            => $request->input('admin_note') ?: $req->admin_note,
            'reviewed_by_admin_id'  => Auth::id(),
            'reviewed_at'           => now(),
        ]);

        $this->notifyPhotographer($req,
            "✅ คำขอถอนเงิน ฿" . number_format((float) $req->amount_thb, 2) . " ได้รับการอนุมัติแล้ว — กำลังโอน"
        );

        return back()->with('success', 'อนุมัติคำขอแล้ว — กดยืนยันโอนเมื่อโอนเสร็จ');
    }

    /** Move pending/approved → rejected (with reason). */
    public function reject(Request $request, int $id): RedirectResponse
    {
        $request->validate([
            'rejection_reason' => 'required|string|max:500',
        ], [
            'rejection_reason.required' => 'กรุณาระบุเหตุผลการปฏิเสธ',
        ]);

        $req = WithdrawalRequest::findOrFail($id);
        if (!$req->isActionable()) {
            return back()->with('error', 'ทำได้เฉพาะรายการที่รอตรวจสอบหรืออนุมัติแล้ว');
        }

        $req->update([
            'status'                => WithdrawalRequest::STATUS_REJECTED,
            'rejection_reason'      => $request->input('rejection_reason'),
            'admin_note'            => $request->input('admin_note') ?: $req->admin_note,
            'reviewed_by_admin_id'  => Auth::id(),
            'reviewed_at'           => now(),
        ]);

        $this->notifyPhotographer($req,
            "❌ คำขอถอนเงิน ฿" . number_format((float) $req->amount_thb, 2) . " ถูกปฏิเสธ\nเหตุผล: " . $req->rejection_reason
        );

        return back()->with('success', 'ปฏิเสธคำขอแล้ว');
    }

    /** Move pending/approved → paid (terminal). Slip URL + reference required. */
    public function markPaid(Request $request, int $id): RedirectResponse
    {
        $request->validate([
            'payment_slip_url'  => 'nullable|url|max:500',
            'payment_reference' => 'nullable|string|max:100',
        ]);

        $req = WithdrawalRequest::findOrFail($id);
        if (!$req->isActionable()) {
            return back()->with('error', 'ทำได้เฉพาะรายการที่รอตรวจสอบหรืออนุมัติแล้ว');
        }

        $req->update([
            'status'                => WithdrawalRequest::STATUS_PAID,
            'payment_slip_url'      => $request->input('payment_slip_url'),
            'payment_reference'     => $request->input('payment_reference'),
            'admin_note'            => $request->input('admin_note') ?: $req->admin_note,
            'reviewed_by_admin_id'  => $req->reviewed_by_admin_id ?: Auth::id(),
            'reviewed_at'           => $req->reviewed_at ?: now(),
            'paid_at'               => now(),
        ]);

        $this->notifyPhotographer($req,
            "💰 โอนเงินถอน ฿" . number_format((float) $req->amount_thb, 2) . " เรียบร้อยแล้ว"
            . ($req->payment_reference ? "\nReference: {$req->payment_reference}" : '')
        );

        return back()->with('success', 'บันทึกการโอนเรียบร้อย');
    }

    /* ────────── Settings page ────────── */

    public function settings(): View
    {
        $settings = [
            'enabled'          => (string) AppSetting::get('withdrawal_request_enabled', '1') === '1',
            'min_amount'       => (int) AppSetting::get('withdrawal_min_amount', 500),
            'max_amount'       => (int) AppSetting::get('withdrawal_max_amount', 500000),
            'fee_thb'          => (int) AppSetting::get('withdrawal_fee_thb', 0),
            'processing_days'  => (int) AppSetting::get('withdrawal_processing_days', 3),
            'max_pending'      => (int) AppSetting::get('withdrawal_max_pending_per_photographer', 1),
        ];
        $methodsRaw = (string) AppSetting::get('withdrawal_methods_enabled', '["bank_transfer","promptpay"]');
        $settings['methods'] = json_decode($methodsRaw, true) ?: ['bank_transfer', 'promptpay'];

        return view('admin.payments.withdrawals.settings', ['settings' => $settings]);
    }

    public function saveSettings(Request $request): RedirectResponse
    {
        $request->validate([
            'enabled'          => 'nullable|boolean',
            'min_amount'       => 'required|integer|min:1|max:1000000',
            'max_amount'       => 'required|integer|min:1|max:10000000',
            'fee_thb'          => 'required|integer|min:0|max:10000',
            'processing_days'  => 'required|integer|min:0|max:30',
            'max_pending'      => 'required|integer|min:1|max:10',
            'methods'          => 'required|array|min:1',
            'methods.*'        => 'in:bank_transfer,promptpay,other',
        ], [
            'min_amount.required' => 'ขั้นต่ำต้องระบุ',
            'max_amount.required' => 'ยอดสูงสุดต้องระบุ',
            'methods.required'    => 'เลือกวิธีรับเงินอย่างน้อย 1 วิธี',
        ]);

        AppSetting::set('withdrawal_request_enabled', $request->boolean('enabled') ? '1' : '0');
        AppSetting::set('withdrawal_min_amount',      (string) $request->integer('min_amount'));
        AppSetting::set('withdrawal_max_amount',      (string) $request->integer('max_amount'));
        AppSetting::set('withdrawal_fee_thb',         (string) $request->integer('fee_thb'));
        AppSetting::set('withdrawal_processing_days', (string) $request->integer('processing_days'));
        AppSetting::set('withdrawal_max_pending_per_photographer', (string) $request->integer('max_pending'));
        AppSetting::set('withdrawal_methods_enabled', json_encode(array_values($request->input('methods')), JSON_UNESCAPED_SLASHES));

        return back()->with('success', 'บันทึกการตั้งค่าแล้ว — มีผลทันทีกับคำขอถอนใหม่');
    }

    /* ────────── Internal ────────── */

    private function notifyPhotographer(WithdrawalRequest $req, string $message): void
    {
        try {
            app(LineNotifyService::class)->pushText($req->photographer_id, $message);
        } catch (\Throwable $e) {
            Log::debug('withdrawal.notify_photographer_failed: ' . $e->getMessage());
        }
    }
}

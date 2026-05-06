<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\PhotographerDisbursement;
use App\Models\PhotographerPayout;
use App\Models\WithdrawalRequest;
use App\Services\LineNotifyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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

    /**
     * Move pending/approved → paid (terminal). Slip URL + reference required.
     *
     * What this does to the EARNINGS LEDGER (the part that took years to get
     * right and is the answer to "หลังโอนเงินแล้วระบบรีเซ็ตรายได้ไหม?"):
     *
     *   • PhotographerPayout rows ARE NOT deleted, ever. Each row is one
     *     sale's commission slice. Lifetime history is preserved.
     *   • What changes is the row's `status` ('pending' → 'paid') and a
     *     `paid_at` timestamp + `disbursement_id` foreign key get stamped
     *     so the photographer dashboard's "ยอดที่ถอนได้" gauge correctly
     *     drops by the paid amount.
     *   • A PhotographerDisbursement row is created — same shape as the
     *     auto-payout cron produces. The photographer's /earnings page
     *     unifies both into one "ประวัติการโอน" list.
     *   • Stats that show on the photographer dashboard (total_earnings,
     *     total_paid, pending_amount) recompute LIVE from these tables on
     *     every page render — there are no cached counters that need
     *     resetting. They self-heal as soon as the ledger settles.
     *
     * The bug this commit fixes: the original implementation flipped the
     * WithdrawalRequest row to 'paid' but left the underlying payouts at
     * status='pending', so available_balance (computed `unpaid_payouts −
     * active_requests`) re-included the just-paid amount the moment the
     * request scope filtered itself out — letting the photographer
     * request the same money TWICE. The migration's docblock claimed
     * "controller logic flips the attached payouts" but no controller
     * ever did. Now it does.
     */
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

        try {
            $disbursement = DB::transaction(function () use ($req, $request) {
                // Pick FIFO-oldest pending payouts to cover the request amount.
                // lockForUpdate keeps concurrent admin actions on the same
                // photographer from picking the same rows. If two admins
                // markPaid two different requests for the same photographer
                // simultaneously, the second one waits at this lock then sees
                // fewer pending rows — exactly what we want.
                $remaining = (float) $req->amount_thb;
                $picked    = collect();

                $pending = PhotographerPayout::where('photographer_id', $req->photographer_id)
                    ->where('status', 'pending')
                    ->whereNull('disbursement_id')
                    ->orderBy('created_at')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                foreach ($pending as $row) {
                    if ($remaining <= 0) break;
                    $picked->push($row);
                    $remaining -= (float) $row->payout_amount;
                }

                $coveredAmount = (float) $picked->sum('payout_amount');

                // Defensive: if the photographer's pending pool somehow
                // shrank below the request amount between request creation
                // and markPaid (admin sat on the request for days while a
                // refund/credit clawed back a payout), we still mark the
                // request paid — admin already moved real money — but log
                // loudly so accounting can investigate.
                if ($coveredAmount + 0.01 < (float) $req->amount_thb) {
                    Log::warning('withdrawal.mark_paid_undercovered', [
                        'withdrawal_request_id' => $req->id,
                        'photographer_id'       => $req->photographer_id,
                        'request_amount'        => (float) $req->amount_thb,
                        'covered_amount'        => $coveredAmount,
                        'shortfall'             => round((float) $req->amount_thb - $coveredAmount, 2),
                    ]);
                }

                // Create a unified-ledger disbursement at status='succeeded'
                // immediately — admin already moved real money, the
                // disbursement IS settled the moment they click "paid". Going
                // through the markSucceeded() lifecycle helper here would
                // pull notification/email/LINE side-effects INTO the
                // transaction, and any one of them with a nested SQL hiccup
                // poisons the whole tx (PostgreSQL aborts the connection on
                // any failed query inside a tx, even when PHP catches the
                // exception). We do the ledger writes here, then fire the
                // notifications outside the transaction below.
                //
                // Idempotency key encodes the WithdrawalRequest ID so re-
                // submits of the same admin POST can never create duplicate
                // disbursements (would over-deduct the photographer's
                // earnings if they did).
                $disbursement = PhotographerDisbursement::create([
                    'photographer_id' => $req->photographer_id,
                    'amount_thb'      => $coveredAmount > 0 ? $coveredAmount : (float) $req->amount_thb,
                    'payout_count'    => $picked->count(),
                    'provider'        => 'manual_admin',
                    'idempotency_key' => 'wr-' . $req->id,
                    'provider_txn_id' => $request->input('payment_reference') ?: ('manual-' . $req->id),
                    'status'          => PhotographerDisbursement::STATUS_SUCCEEDED,
                    'trigger_type'    => PhotographerDisbursement::TRIGGER_MANUAL,
                    'attempted_at'    => now(),
                    'settled_at'      => now(),
                    'attempts'        => 1,
                    'raw_response'    => [
                        'source'                => 'admin_manual_mark_paid',
                        'admin_id'              => Auth::id(),
                        'withdrawal_request_id' => $req->id,
                        'slip_url'              => $request->input('payment_slip_url'),
                    ],
                ]);

                // Flip the picked payouts to 'paid' AND attach them to the
                // disbursement in one update. This is the authoritative
                // ledger write that closes the double-spend window.
                if ($picked->isNotEmpty()) {
                    PhotographerPayout::whereIn('id', $picked->pluck('id'))
                        ->update([
                            'disbursement_id' => $disbursement->id,
                            'status'          => 'paid',
                            'paid_at'         => now(),
                        ]);
                }

                $req->update([
                    'status'                => WithdrawalRequest::STATUS_PAID,
                    'payment_slip_url'      => $request->input('payment_slip_url'),
                    'payment_reference'     => $request->input('payment_reference'),
                    'admin_note'            => $request->input('admin_note') ?: $req->admin_note,
                    'reviewed_by_admin_id'  => $req->reviewed_by_admin_id ?: Auth::id(),
                    'reviewed_at'           => $req->reviewed_at ?: now(),
                    'paid_at'               => now(),
                    'disbursement_id'       => $disbursement->id,
                ]);

                return $disbursement;
            });
        } catch (\Throwable $e) {
            Log::error('withdrawal.mark_paid_failed', [
                'withdrawal_request_id' => $req->id,
                'error'                 => $e->getMessage(),
            ]);
            return back()->with('error', 'บันทึกการโอนล้มเหลว: ' . $e->getMessage());
        }

        // Notifications run AFTER the ledger commits. Any failure here is
        // best-effort — the money is already recorded settled, the
        // photographer's dashboard already shows accurate balances. A
        // failed LINE push or admin-bell ping doesn't reverse anything.
        try {
            \App\Models\AdminNotification::disbursementSuccess($disbursement);
        } catch (\Throwable $e) {
            Log::warning('withdrawal.admin_notification_failed', [
                'disbursement_id' => $disbursement->id, 'error' => $e->getMessage(),
            ]);
        }
        try {
            \App\Models\UserNotification::payoutProcessed(
                $req->photographer_id,
                (float) $disbursement->amount_thb,
                $disbursement
            );
        } catch (\Throwable $e) {
            Log::warning('withdrawal.user_notification_failed', [
                'disbursement_id' => $disbursement->id, 'error' => $e->getMessage(),
            ]);
        }

        $this->notifyPhotographer($req->fresh(),
            "💰 โอนเงินถอน ฿" . number_format((float) $req->amount_thb, 2) . " เรียบร้อยแล้ว"
            . ($req->payment_reference ? "\nReference: {$req->payment_reference}" : '')
        );

        return back()->with('success', 'บันทึกการโอนเรียบร้อย — รายได้ในระบบอัพเดทแล้ว');
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

        $min = (string) $request->integer('min_amount');

        AppSetting::set('withdrawal_request_enabled', $request->boolean('enabled') ? '1' : '0');
        AppSetting::set('withdrawal_min_amount',      $min);
        AppSetting::set('withdrawal_max_amount',      (string) $request->integer('max_amount'));
        AppSetting::set('withdrawal_fee_thb',         (string) $request->integer('fee_thb'));
        AppSetting::set('withdrawal_processing_days', (string) $request->integer('processing_days'));
        AppSetting::set('withdrawal_max_pending_per_photographer', (string) $request->integer('max_pending'));
        AppSetting::set('withdrawal_methods_enabled', json_encode(array_values($request->input('methods')), JSON_UNESCAPED_SLASHES));
        // Mirror the manual-withdrawal floor onto the auto-payout threshold
        // so admins never see a mismatch between this page and
        // /admin/payouts/settings. Historically these were two distinct
        // keys (`withdrawal_min_amount` for the self-withdrawal widget,
        // `payout_min_amount` for the auto-payout cron) — admins set one
        // and were confused when the photographer dashboard kept showing
        // the other. Two-way mirror keeps them in lockstep regardless of
        // which page the admin opens. Auto-payout's "0 = disable threshold"
        // semantics are preserved — when withdrawal min is 1+ (enforced
        // by validation above) it's always a valid auto-payout floor too.
        AppSetting::set('payout_min_amount', $min);

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

<?php

namespace App\Http\Controllers\Photographer;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\PhotographerDisbursement;
use App\Models\PhotographerPayout;
use App\Models\PhotographerProfile;
use App\Models\User;
use App\Models\WithdrawalRequest;
use App\Services\LineNotifyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Photographer-side withdrawal request flow.
 *
 *   POST /photographer/withdrawals          → create new request
 *   POST /photographer/withdrawals/{id}/cancel → photographer cancels pending
 *
 * The flow is gated by admin-configurable settings (min/max amount,
 * methods enabled, max pending). Available balance is derived from
 * unpaid PhotographerPayout rows minus everything already paid out
 * (existing disbursements + currently pending withdrawal requests).
 */
class WithdrawalController extends Controller
{
    /**
     * Compute settings + available balance + active request status.
     * Returns the same shape used by the photographer earnings view
     * so blade can show it either way.
     *
     * @return array{
     *   enabled:bool, min:int, max:int, fee:int, processing_days:int,
     *   methods:array<string>, max_pending:int,
     *   pending_count:int, has_active:bool, active_request:?WithdrawalRequest,
     *   available_balance:float, can_request:bool, blocking_reason:?string
     * }
     */
    public static function snapshot(int $photographerId): array
    {
        $enabled = (string) AppSetting::get('withdrawal_request_enabled', '1') === '1';
        $min     = (int) AppSetting::get('withdrawal_min_amount', 500);
        $max     = (int) AppSetting::get('withdrawal_max_amount', 500000);
        $fee     = (int) AppSetting::get('withdrawal_fee_thb', 0);
        $days    = (int) AppSetting::get('withdrawal_processing_days', 3);
        $maxPend = (int) AppSetting::get('withdrawal_max_pending_per_photographer', 1);

        $methodsRaw = (string) AppSetting::get('withdrawal_methods_enabled', '["bank_transfer","promptpay"]');
        $methods    = json_decode($methodsRaw, true);
        if (!is_array($methods)) $methods = ['bank_transfer', 'promptpay'];

        // ── Available balance ─────────────────────────────────────────
        // Earnings = sum of UNPAID payouts. We subtract any AMOUNT
        // currently locked inside a pending/approved withdrawal so the
        // photographer can't double-spend the same balance into two
        // simultaneous requests.
        $unpaidPayouts = (float) PhotographerPayout::where('photographer_id', $photographerId)
            ->where('status', 'pending')
            ->sum('payout_amount');

        $lockedInActiveRequests = (float) WithdrawalRequest::where('photographer_id', $photographerId)
            ->active()
            ->sum('amount_thb');

        $availableBalance = max(0, $unpaidPayouts - $lockedInActiveRequests);

        // ── Pending count (anti-spam) ─────────────────────────────────
        $pendingCount = WithdrawalRequest::where('photographer_id', $photographerId)
            ->active()
            ->count();
        $activeRequest = WithdrawalRequest::where('photographer_id', $photographerId)
            ->active()
            ->latest('id')
            ->first();

        // ── Compute "can_request" + reason ────────────────────────────
        $canRequest    = true;
        $blockingReason = null;
        if (!$enabled) {
            $canRequest = false;
            $blockingReason = 'ระบบแจ้งถอนปิดอยู่ชั่วคราว';
        } elseif ($pendingCount >= $maxPend) {
            $canRequest = false;
            $blockingReason = "คุณมีคำขอที่รอตรวจสอบอยู่แล้ว ({$pendingCount} รายการ) — รอเสร็จก่อน";
        } elseif ($availableBalance < $min) {
            $canRequest = false;
            $blockingReason = "ยอดยังไม่ถึงขั้นต่ำ — ต้องมียอดอย่างน้อย ฿" . number_format($min);
        }

        return [
            'enabled'            => $enabled,
            'min'                => $min,
            'max'                => $max,
            'fee'                => $fee,
            'processing_days'    => $days,
            'methods'            => $methods,
            'max_pending'        => $maxPend,
            'pending_count'      => $pendingCount,
            'has_active'         => $activeRequest !== null,
            'active_request'     => $activeRequest,
            'available_balance'  => $availableBalance,
            'can_request'        => $canRequest,
            'blocking_reason'    => $blockingReason,
        ];
    }

    /**
     * POST /photographer/withdrawals
     *
     * Creates a pending request after validating against the snapshot
     * gate, snapshots the bank/promptpay info onto the row (so the
     * audit trail survives later profile edits), and pings admin via
     * LINE/admin-notification so they can act on it.
     */
    public function store(Request $request): RedirectResponse
    {
        $userId = Auth::id();
        $snap   = self::snapshot($userId);

        if (!$snap['can_request']) {
            return back()->with('error', $snap['blocking_reason'] ?? 'ขณะนี้แจ้งถอนไม่ได้');
        }

        $validated = $request->validate([
            'amount'      => "required|numeric|min:{$snap['min']}|max:{$snap['max']}",
            'method'      => 'required|in:bank_transfer,promptpay',
            'bank_name'   => 'nullable|string|max:64',
            'account_name'=> 'required_if:method,bank_transfer,promptpay|string|max:128',
            'account_number' => 'required_if:method,bank_transfer|string|max:32',
            'promptpay_id'   => 'required_if:method,promptpay|string|max:32',
            'note'        => 'nullable|string|max:500',
        ], [
            'amount.min'  => "ต้องไม่ต่ำกว่า ฿" . number_format($snap['min']),
            'amount.max'  => "ต้องไม่เกิน ฿" . number_format($snap['max']),
            'method.in'   => 'วิธีรับเงินไม่ถูกต้อง',
            'account_name.required_if'   => 'กรุณาระบุชื่อบัญชี',
            'account_number.required_if' => 'กรุณาระบุเลขบัญชี',
            'promptpay_id.required_if'   => 'กรุณาระบุเบอร์ PromptPay หรือเลขประจำตัวประชาชน',
        ]);

        // Method must be in the admin's enabled list
        if (!in_array($validated['method'], $snap['methods'], true)) {
            return back()->with('error', 'วิธีรับเงินนี้ปิดให้บริการอยู่');
        }

        $amount = (float) $validated['amount'];
        if ($amount > $snap['available_balance']) {
            return back()->with('error', "ยอดที่ขอ (฿" . number_format($amount, 2) . ") มากกว่ายอดที่ใช้ได้ (฿" . number_format($snap['available_balance'], 2) . ")");
        }

        $details = [];
        if ($validated['method'] === 'bank_transfer') {
            $details = [
                'bank_name'      => $validated['bank_name'] ?? null,
                'account_name'   => $validated['account_name'],
                'account_number' => $validated['account_number'],
            ];
        } elseif ($validated['method'] === 'promptpay') {
            $details = [
                'account_name' => $validated['account_name'],
                'promptpay_id' => $validated['promptpay_id'],
            ];
        }

        $req = DB::transaction(function () use ($userId, $amount, $snap, $validated, $details) {
            return WithdrawalRequest::create([
                'photographer_id'   => $userId,
                'amount_thb'        => $amount,
                'fee_thb'           => $snap['fee'],
                'net_thb'           => max(0, $amount - $snap['fee']),
                'method'            => $validated['method'],
                'method_details'    => $details,
                'status'            => WithdrawalRequest::STATUS_PENDING,
                'photographer_note' => $validated['note'] ?? null,
            ]);
        });

        // Notify admins (best-effort — never block the success path)
        try {
            $photographer = User::find($userId);
            $name = $photographer?->name ?? "user #{$userId}";
            $msg = "💰 คำขอถอนเงินใหม่\n"
                 . "ช่างภาพ: {$name}\n"
                 . "ยอด: ฿" . number_format($amount, 2) . "\n"
                 . "วิธี: " . ($req->methodLabel())
                 . "\n→ /admin/payments/withdrawals";
            app(LineNotifyService::class)->notifyAdmin($msg);
        } catch (\Throwable $e) {
            Log::warning('withdrawal.notify_admin_failed: ' . $e->getMessage());
        }

        return back()->with('success', "ส่งคำขอถอนเงิน ฿" . number_format($amount, 2) . " เรียบร้อย — รอแอดมินตรวจสอบภายใน {$snap['processing_days']} วัน");
    }

    /**
     * POST /photographer/withdrawals/{id}/cancel — photographer cancels
     * their own pending request (cannot cancel after admin approval).
     */
    public function cancel(int $id): RedirectResponse
    {
        $userId = Auth::id();
        $req = WithdrawalRequest::where('id', $id)
            ->where('photographer_id', $userId)
            ->firstOrFail();

        if (!$req->isCancellable()) {
            return back()->with('error', 'ยกเลิกไม่ได้ — สถานะปัจจุบัน "' . $req->statusLabel() . '"');
        }

        $req->update(['status' => WithdrawalRequest::STATUS_CANCELLED]);
        return back()->with('success', 'ยกเลิกคำขอถอนเงินแล้ว');
    }
}

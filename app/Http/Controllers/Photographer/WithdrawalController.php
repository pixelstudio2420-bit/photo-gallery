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
        // Reconciled minimum across both admin pages.
        //
        // Two distinct keys exist for historical reasons:
        //   • withdrawal_min_amount  — set at /admin/payments/withdrawals/settings
        //   • payout_min_amount      — set at /admin/payouts/settings
        //
        // From an admin's UX perspective these are the SAME knob ("how
        // small can a withdrawal be?"). They were drifting apart whenever
        // an admin tweaked one page without realising the other existed,
        // and the photographer dashboard widget would show the stale
        // half — exactly the "แสดงยอดขั้นต่ำไม่ตรงกับที่แอดมินตั้งไว้"
        // bug. Two-way mirror on save (see the two admin controllers)
        // prevents new drift; this max() reconciles any pre-existing
        // drift by surfacing the higher floor (manual requests are NEVER
        // looser than the auto-payout threshold, which is the safer
        // direction — a photographer denied a manual withdrawal can wait
        // for auto-payout, but the reverse stranding never makes sense).
        $minWithdrawal = (int) AppSetting::get('withdrawal_min_amount', 500);
        $minPayout     = (int) AppSetting::get('payout_min_amount', 500);
        $min     = max($minWithdrawal, $minPayout, 1);
        $max     = (int) AppSetting::get('withdrawal_max_amount', 500000);
        $fee     = (int) AppSetting::get('withdrawal_fee_thb', 0);
        $days    = (int) AppSetting::get('withdrawal_processing_days', 3);
        $maxPend = (int) AppSetting::get('withdrawal_max_pending_per_photographer', 1);

        $methodsRaw = (string) AppSetting::get('withdrawal_methods_enabled', '["bank_transfer","promptpay"]');
        $methods    = json_decode($methodsRaw, true);
        if (!is_array($methods)) $methods = ['bank_transfer', 'promptpay'];

        // ── Available balance ─────────────────────────────────────────
        // Two locks combine to keep the same balance from being spent
        // twice across concurrent paths:
        //
        //   (a) Row-level lock — when a photographer creates a withdrawal
        //       request, store() attaches the FIFO-picked PhotographerPayout
        //       rows to a draft Disbursement. Those rows then have
        //       disbursement_id != null, so this query (which filters
        //       whereNull) excludes them. This ALSO blocks the auto-payout
        //       cron from grabbing the same payouts (PayoutEngine has the
        //       identical whereNull filter at line 70-71).
        //
        //   (b) WithdrawalRequest scope subtraction — defensive fallback
        //       for legacy pre-fix rows that exist without disbursement_id.
        //       New requests don't double-count because the row-level lock
        //       already excluded them in clause (a); this clause only
        //       catches the legacy unlocked case where a WR was created
        //       before this refactor shipped.
        //
        // The "max(0, …)" guard catches the rare race where (a) and (b)
        // briefly disagree and arithmetic would go negative.
        $unpaidPayouts = (float) PhotographerPayout::where('photographer_id', $photographerId)
            ->where('status', 'pending')
            ->whereNull('disbursement_id')
            ->sum('payout_amount');

        // Legacy fallback — only counts WRs without a draft disbursement
        // attached (those are the pre-refactor rows). Post-refactor WRs
        // always have disbursement_id set, so they'd be double-counted
        // against the row-level lock above without this whereNull.
        $lockedLegacy = (float) WithdrawalRequest::where('photographer_id', $photographerId)
            ->active()
            ->whereNull('disbursement_id')
            ->sum('amount_thb');

        $availableBalance = max(0, $unpaidPayouts - $lockedLegacy);

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

        try {
            [$req, $snapAdjusted] = DB::transaction(function () use ($userId, $amount, $snap, $validated, $details) {
                // Row-level lock — pick FIFO-oldest pending payouts that
                // cover the requested amount. lockForUpdate prevents two
                // concurrent withdrawal-request submissions from picking the
                // same rows. Since each PhotographerPayout row maps 1:1 to a
                // sale and rows can't be split (unique key on photographer_id
                // + order_id), we always pick whole rows — the smallest
                // covering subset.
                $pending = PhotographerPayout::where('photographer_id', $userId)
                    ->where('status', 'pending')
                    ->whereNull('disbursement_id')
                    ->orderBy('created_at')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                $remaining = (float) $amount;
                $picked    = collect();
                foreach ($pending as $row) {
                    if ($remaining <= 0) break;
                    $picked->push($row);
                    $remaining -= (float) $row->payout_amount;
                }

                $coveredAmount = (float) $picked->sum('payout_amount');

                if ($coveredAmount + 0.01 < (float) $amount) {
                    // Pending pool can't cover the request. Should be caught
                    // by the available_balance gate above, but defense in
                    // depth: refuse here too rather than silently lock too
                    // few rows.
                    throw new \RuntimeException(
                        'ยอดที่ขอ ฿' . number_format($amount, 2) .
                        ' มากกว่ายอดรายได้ที่ใช้ได้ (฿' . number_format($coveredAmount, 2) . ')'
                    );
                }

                // Snap UP to the exact FIFO-aligned amount. Photographer
                // typed ฿500 with FIFO [฿300, ฿300, ฿400] → covered = ฿600
                // (smallest superset covering the request). The bank
                // transfer will be ฿600, photographer gets MORE money than
                // they typed — accounting reconciles cleanly because
                // disbursement.amount_thb = sum of attached payouts always
                // matches reality (no overshoot drift).
                $finalAmount = $coveredAmount;

                // Create the WR first so the draft Disbursement can use a
                // unique idempotency key derived from WR.id (prevents
                // duplicate drafts even on rapid double-submit).
                $wr = WithdrawalRequest::create([
                    'photographer_id'   => $userId,
                    'amount_thb'        => $finalAmount,
                    'fee_thb'           => $snap['fee'],
                    'net_thb'           => max(0, $finalAmount - $snap['fee']),
                    'method'            => $validated['method'],
                    'method_details'    => $details,
                    'status'            => WithdrawalRequest::STATUS_PENDING,
                    'photographer_note' => $validated['note'] ?? null,
                ]);

                // Draft Disbursement — status='pending' means "money has
                // not been transferred yet, but the photographer's payouts
                // are reserved for this withdrawal". Auto-payout cron skips
                // (PayoutEngine filters whereNull('disbursement_id')) so
                // this manual flow can't race against it.
                $draft = PhotographerDisbursement::create([
                    'photographer_id' => $userId,
                    'amount_thb'      => $finalAmount,
                    'payout_count'    => $picked->count(),
                    'provider'        => 'manual_admin',
                    'idempotency_key' => 'wr-' . $wr->id,
                    'status'          => PhotographerDisbursement::STATUS_PENDING,
                    'trigger_type'    => PhotographerDisbursement::TRIGGER_MANUAL,
                    'attempts'        => 0,
                ]);

                // Lock the picked payouts to the draft. This is the
                // authoritative row-level reservation. Status stays
                // 'pending' until admin marks paid; auto-payout filter
                // skips by disbursement_id IS NOT NULL.
                PhotographerPayout::whereIn('id', $picked->pluck('id'))
                    ->update(['disbursement_id' => $draft->id]);

                $wr->update(['disbursement_id' => $draft->id]);

                return [$wr, [
                    'requested' => (float) $amount,
                    'final'     => $finalAmount,
                    'adjusted'  => abs($finalAmount - (float) $amount) > 0.01,
                ]];
            });
        } catch (\Throwable $e) {
            Log::error('withdrawal.store_failed', [
                'user_id' => $userId, 'amount' => $amount, 'error' => $e->getMessage(),
            ]);
            return back()->with('error', $e->getMessage());
        }

        // Tell the photographer if we snapped UP. They typed ฿500, system
        // adjusted to ฿600 because their pending sales align in those
        // increments — they get MORE money, not less.
        if ($snapAdjusted['adjusted']) {
            session()->flash(
                'info',
                sprintf(
                    'ปรับยอดถอนเป็น ฿%s (จาก ฿%s ที่กรอก) เพื่อให้ตรงกับยอดขายในระบบ — คุณจะได้รับยอดที่ปรับเต็มจำนวน',
                    number_format($snapAdjusted['final'], 2),
                    number_format($snapAdjusted['requested'], 2),
                )
            );
        }

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

        DB::transaction(function () use ($req) {
            // Release the row-level lock — set the picked payouts back to
            // a free state (disbursement_id = null) so the photographer
            // (and the auto-payout cron) can re-grab them. Without this,
            // cancelling a request would strand earnings forever in the
            // "attached to a draft Disbursement that no one will ever
            // settle" state.
            if ($req->disbursement_id) {
                PhotographerPayout::where('disbursement_id', $req->disbursement_id)
                    ->update(['disbursement_id' => null]);
                // Clean up the draft Disbursement row — it's only safe to
                // delete because we restrict to status='pending' (drafts
                // never reach succeeded without going through markPaid).
                PhotographerDisbursement::where('id', $req->disbursement_id)
                    ->where('status', PhotographerDisbursement::STATUS_PENDING)
                    ->delete();
            }
            $req->update([
                'status'          => WithdrawalRequest::STATUS_CANCELLED,
                'disbursement_id' => null,
            ]);
        });

        return back()->with('success', 'ยกเลิกคำขอถอนเงินแล้ว — ยอดเงินกลับสู่ยอดที่ถอนได้');
    }
}

<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\DownloadToken;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\PaymentSlip;
use App\Models\PaymentTransaction;
use App\Services\Payment\OrderStateMachine;
use App\Services\Payment\PaymentService;
use App\Services\Payment\PaymentVerificationResult;
use App\Services\Payment\PaymentVerificationService;
use App\Services\Payment\SlipFingerprintService;
use App\Services\Payment\SlipOKService;
use App\Services\Payment\SlipVerifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Services\ImageProcessorService;
use App\Services\StorageManager;
use App\Models\UserNotification;
use Illuminate\Support\Facades\Schema;

class PaymentController extends Controller
{
    public function checkout($orderId)
    {
        $order = Order::where('id', $orderId)
            ->where('user_id', Auth::id())
            ->with(['items', 'event', 'package'])
            ->firstOrFail();

        // Accept both DB status values
        if (!in_array($order->status, ['pending_payment', 'pending'])) {
            return redirect()->route('orders.show', $order->id)
                ->with('info', 'This order has already been processed.');
        }

        // Active payment methods filtered by gateway availability
        $paymentMethods = PaymentService::getActiveGateways();

        // Promote PromptPay to first position — in Thailand ~80% of customers
        // pay via PromptPay QR, so defaulting to it removes one decision from
        // the checkout. Admin-configured sort_order is still respected as the
        // secondary sort (used for the non-PromptPay methods).
        //
        // This is intentionally done in the controller (not the DB sort) so
        // operators keep the freedom to turn PromptPay off or reorder the
        // other methods without this priority rule fighting their settings.
        $paymentMethods = $paymentMethods
            ->sortBy(fn ($m) => $m->method_type === 'promptpay' ? -1 : (int) ($m->sort_order ?? 999))
            ->values();

        // Inline display data
        $bankAccounts    = PaymentService::getBankAccounts();
        $promptPayNumber = PaymentService::getPromptPayNumber();

        // Icon map
        $methodIcons = [];
        foreach ($paymentMethods as $method) {
            $methodIcons[$method->method_type] = PaymentService::getMethodIcon($method->method_type);
        }

        return view('public.payment.checkout', compact(
            'order',
            'paymentMethods',
            'bankAccounts',
            'promptPayNumber',
            'methodIcons'
        ));
    }

    public function process(Request $request)
    {
        $request->validate([
            'order_id'       => 'required|integer|exists:orders,id',
            'payment_method' => 'required|string',
        ]);

        $order = Order::where('id', $request->order_id)
            ->where('user_id', Auth::id())
            ->with(['items', 'event'])
            ->firstOrFail();

        if (!in_array($order->status, ['pending_payment', 'pending'])) {
            return redirect()->route('orders.show', $order->id)
                ->with('info', 'This order has already been processed.');
        }

        $methodType = $request->payment_method;

        // Payment method is tracked via payment_transactions.payment_gateway, not on orders table

        // Route to gateway
        try {
            $result      = PaymentService::processPayment($order, $methodType);
            $transaction = $result['transaction'];
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', 'ช่องทางชำระเงินไม่ถูกต้อง: ' . $e->getMessage());
        }

        // External redirect (Stripe, Omise, etc.)
        if (!empty($result['redirect_url'])) {
            return redirect($result['redirect_url']);
        }

        // PromptPay → QR view
        if ($methodType === 'promptpay') {
            $qrPayload       = $result['data']['qr_payload'] ?? '';
            $qrUrl           = $result['data']['qr_url'] ?? '';
            $promptPayNumber = $result['data']['promptpay_number'] ?? PaymentService::getPromptPayNumber();
            $amount          = $result['data']['amount'] ?? $order->total;

            return view('public.payment.promptpay', compact(
                'transaction', 'qrPayload', 'qrUrl', 'promptPayNumber', 'amount', 'order'
            ));
        }

        // Bank transfer → bank info view
        if ($methodType === 'bank_transfer') {
            $bankAccounts = PaymentService::getBankAccounts();
            $amount       = $order->total;

            return view('public.payment.bank-transfer', compact(
                'transaction', 'bankAccounts', 'amount', 'order'
            ));
        }

        // Manual / others — pending admin review
        if (!empty($result['success'])) {
            return redirect()->route('payment.success')
                ->with('info', 'คำสั่งซื้อรอการยืนยันจากผู้ดูแลระบบ');
        }

        // Gateway not configured
        $errorMsg = $result['data']['error'] ?? 'ช่องทางนี้ยังไม่พร้อมใช้งาน กรุณาเลือกช่องทางอื่น';
        return back()->with('warning', $errorMsg);
    }

    public function success(Request $request)
    {
        $order        = null;
        $transaction  = null;
        $digitalOrder = null;

        if ($request->filled('token') && $request->input('type') === 'digital') {
            $digitalOrder = \App\Models\DigitalOrder::where('download_token', $request->input('token'))
                ->with('product')
                ->first();
        }

        if ($request->filled('txn')) {
            $transaction = PaymentTransaction::where('transaction_id', $request->input('txn'))
                ->with('order.items')
                ->first();
            if ($transaction) {
                $order = $transaction->order;
            }
        }

        if (!$order && $request->filled('order_number') && $request->input('type') !== 'digital') {
            $order = Order::where('order_number', $request->input('order_number'))
                ->with('items')
                ->first();
        }

        if (!$digitalOrder && $request->filled('order_number') && $request->input('type') === 'digital') {
            $digitalOrder = \App\Models\DigitalOrder::where('order_number', $request->input('order_number'))
                ->with('product')
                ->first();
        }

        return view('public.payment.success', compact('order', 'transaction', 'digitalOrder'));
    }

    // -----------------------------------------------------------------------
    // Slip Upload (AJAX)
    // -----------------------------------------------------------------------

    public function uploadSlip(
        Request $request,
        \App\Services\Media\R2MediaService $media,
        SlipFingerprintService $fingerprints,
        PaymentVerificationService $verification,
        OrderStateMachine $stateMachine,
    ) {
        $request->validate([
            'order_id'        => 'required|integer|exists:orders,id',
            'slip_image'      => 'required|file|mimes:jpg,jpeg,png,webp,pdf|max:5120',
            'transfer_amount' => 'required|numeric|min:0',
            'transfer_date'   => 'required|date',
            'ref_code'        => 'nullable|string|max:100',
            'payment_method'  => 'nullable|string|max:50',
        ]);

        $score       = 0;
        $autoApprove = false;
        $slipokData  = null;
        $verifyMode  = \App\Models\AppSetting::get('slip_verify_mode', 'manual');

        // 1. Verify order ownership
        $order = Order::where('id', $request->order_id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        // 2. Compute fingerprint EARLY (before upload). Lets us short-circuit
        //    cross-user / same-user duplicate fraud BEFORE writing to R2 +
        //    burning SlipOK API quota.
        $file        = $request->file('slip_image');
        $fingerprint = $fingerprints->fingerprint($file);
        $hash        = $fingerprint->sha256;

        // 3. Hard-reject pass — PaymentVerificationService.verify() returns
        //    REJECTED with a reason if any of the structural fraud signals
        //    fire (cross-user reuse, amount underpaid, slip predates order, …).
        //    We don't call SlipOK yet — saves an API hit on obvious fraud.
        $hardCheck = $verification->verify(
            file:        $file,
            order:       $order,
            fingerprint: $fingerprint,
            context:     [
                'transfer_amount'  => $request->transfer_amount,
                'order_amount'     => (float) $order->total,
                'ref_code'         => $request->ref_code,
                'transfer_date'    => $request->transfer_date,
                'order_created_at' => $order->created_at,
            ],
            slipokResult: null,    // not called yet; SlipOK runs in step 5
        );
        if ($hardCheck->isRejected()) {
            Log::warning('Slip upload rejected by verification service', [
                'user_id'      => Auth::id(),
                'order_id'     => $order->id,
                'reason'       => $hardCheck->rejectionReason,
                'fraud_flags'  => $hardCheck->fraudFlags,
                'sha256'       => $hash,
            ]);
            return response()->json([
                'success'      => false,
                'message'      => $hardCheck->rejectionReason,
                'fraud_flags'  => $hardCheck->fraudFlags,
            ], 422);
        }

        // 4. Upload to R2 (canonical path: payments/slips/user_{id}/order_{id}/…).
        try {
            $upload = $media->uploadPaymentSlip((int) Auth::id(), (int) $order->id, $file);
            $path   = $upload->key;
        } catch (\App\Services\Media\Exceptions\InvalidMediaFileException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        // 5. Existing SlipVerifier — produces the rich score (file size, EXIF,
        //    SlipOK call, receiver-account match, etc.) used for the
        //    auto-approve decision. The hard-reject layer above already
        //    eliminated the structural fraud cases, so anything that gets
        //    here is structurally legitimate; we use the score to decide
        //    auto-approve vs manual review.
        try {
            $verifier     = new SlipVerifier();
            $verifyResult = $verifier->verify($file, [
                'transfer_amount'  => $request->transfer_amount,
                'order_amount'     => (float) $order->total,
                'ref_code'         => $request->ref_code,
                'transfer_date'    => $request->transfer_date,
                'order_created_at' => $order->created_at,
            ]);

            $score       = $verifyResult['score'] ?? 0;
            $autoApprove = $verifyResult['auto_approve'] ?? false;
            $slipokData  = $verifyResult['slipok_data'] ?? null;
            $verifyMode  = $verifyResult['mode'] ?? $verifyMode;
        } catch (\Throwable $e) {
            Log::error('SlipVerifier failed, falling back to manual: ' . $e->getMessage(), [
                'order_id' => $order->id,
                'user_id'  => Auth::id(),
            ]);
            $verifyResult = [
                'score'        => 0,
                'auto_approve' => false,
                'mode'         => 'manual',
                'breakdown'    => ['error' => 'verifier_failed'],
                'fraud_flags'  => [],
            ];
        }

        // 6. Re-verify with SlipOK result this time — catches transRef
        //    cross-user duplicates that the early gate couldn't see (because
        //    SlipOK hadn't been called yet).
        if ($slipokData && !empty($slipokData['trans_ref'])) {
            $finalCheck = $verification->verify(
                file:         $file,
                order:        $order,
                fingerprint:  $fingerprint,
                context:      [
                    'transfer_amount'  => $request->transfer_amount,
                    'order_amount'     => (float) $order->total,
                    'transfer_date'    => $request->transfer_date,
                    'order_created_at' => $order->created_at,
                ],
                slipokResult: [
                    'success'         => $slipokData['success'] ?? true,
                    'trans_ref'       => $slipokData['trans_ref'],
                    'amount_verified' => $slipokData['amount_verified'] ?? false,
                ],
            );
            if ($finalCheck->isRejected()) {
                // Don't keep an R2 object that we just rejected.
                try { $media->forget($path); } catch (\Throwable) {}
                Log::warning('Slip rejected post-SlipOK by verification service', [
                    'order_id'    => $order->id,
                    'reason'      => $finalCheck->rejectionReason,
                    'trans_ref'   => $slipokData['trans_ref'],
                ]);
                return response()->json([
                    'success'      => false,
                    'message'      => $finalCheck->rejectionReason,
                    'fraud_flags'  => $finalCheck->fraudFlags,
                ], 422);
            }
        }

        // 7. Determine initial verify_status. We trust SlipVerifier's
        //    auto_approve when both gates passed AND the configured mode is auto.
        $verifyStatus = $autoApprove ? 'approved' : 'pending';

        // 8. Persist the slip record. The DB UNIQUE INDEX (Postgres-only)
        //    is a final defence against TOCTOU — if a race lets two
        //    requests reach here simultaneously, ONE insert wins.
        $slip = PaymentSlip::create([
            'order_id'         => $order->id,
            'slip_path'        => $path,
            'slip_hash'        => $hash,
            'amount'           => $request->transfer_amount,
            'transfer_date'    => $request->transfer_date,
            'reference_code'   => $request->ref_code,
            'verify_status'    => $verifyStatus,
            'verify_score'     => $score,
            'verified_at'      => $autoApprove ? now() : null,
            'fraud_flags'      => !empty($verifyResult['fraud_flags']) ? $verifyResult['fraud_flags'] : null,
            'verify_breakdown' => $verifyResult['breakdown'] ?? null,
            'slipok_trans_ref' => $slipokData['trans_ref']        ?? null,
            'receiver_account' => $slipokData['receiver_account'] ?? null,
            'receiver_name'    => $slipokData['receiver_name']    ?? null,
            'sender_name'      => $slipokData['sender_name']      ?? null,
        ]);

        // 8a. Async re-verify when SlipOK was supposed to run but didn't return
        //     a transRef (network hiccup, rate-limit, transient error). The
        //     job retries with exponential backoff out-of-band so transient
        //     SlipOK outages don't permanently strand a slip in the manual
        //     queue. Skip dispatch if SlipOK is disabled or already approved.
        $slipokWasAttempted = ($verifyResult['checks']['slipok_enabled'] ?? false);
        $slipokSucceeded    = !empty($slipokData['trans_ref'] ?? null);
        if ($verifyStatus === 'pending' && $slipokWasAttempted && !$slipokSucceeded) {
            try {
                \App\Jobs\Payment\VerifyPaymentSlipJob::dispatch($slip->id)
                    ->delay(now()->addMinute()); // small delay to let the slip COMMIT first
            } catch (\Throwable $e) {
                Log::warning('Failed to dispatch VerifyPaymentSlipJob', [
                    'slip_id' => $slip->id,
                    'error'   => $e->getMessage(),
                ]);
            }
        }

        // 8. Create / update payment_transactions record (status = pending)
        $transaction = PaymentTransaction::where('order_id', $order->id)
            ->where('status', 'pending')
            ->latest()
            ->first();

        if ($transaction) {
            $transaction->update([
                'payment_gateway' => $request->payment_method ?? 'bank_transfer',
                'status'          => 'pending',
            ]);
        } else {
            $transaction = PaymentTransaction::create([
                'transaction_id'  => 'TXN-' . strtoupper(Str::random(16)),
                'order_id'        => $order->id,
                'user_id'         => Auth::id(),
                'payment_gateway' => $request->payment_method ?? 'bank_transfer',
                'amount'          => $order->total,
                'currency'        => 'THB',
                'status'          => 'pending',
            ]);
        }

        // 9. Transition the order through OrderStateMachine — guards against
        //    invalid transitions (e.g. trying to move a 'paid' order back
        //    to 'pending_review') and serialises concurrent webhook+manual
        //    races via lockForUpdate.
        try {
            $stateMachine->transition(
                orderId:        (int) $order->id,
                toStatus:       'pending_review',
                idempotencyKey: "slip.{$slip->id}.submitted",
                auditContext:   ['slip_id' => $slip->id, 'sha256' => $hash],
            );
        } catch (\DomainException $e) {
            // Order may already be paid (legitimate retry from confused user).
            // Don't fail the upload — the slip is recorded, admin will sort.
            Log::info('Order already past pending_review; slip stored for audit', [
                'order_id' => $order->id,
                'reason'   => $e->getMessage(),
            ]);
        }

        // 10. Auto-approve path — only fires when verifier scored ≥ threshold
        //     AND mode='auto' AND zero fraud flags.
        if ($autoApprove) {
            try {
                $changed = $stateMachine->transitionToPaid(
                    orderId:        (int) $order->id,
                    idempotencyKey: "slip.{$slip->id}.auto-approved",
                    auditContext:   [
                        'slip_id'       => $slip->id,
                        'verify_score'  => $score,
                        'sha256'        => $hash,
                        'auto_approved' => true,
                    ],
                );
                if ($changed) {
                    $transaction->update([
                        'status'  => 'completed',
                        'paid_at' => now(),
                    ]);
                    try {
                        app(\App\Services\OrderFulfillmentService::class)->fulfill($order->fresh());
                    } catch (\Throwable $e) {
                        Log::warning('Auto-approve fulfillment failed order ' . $order->id . ': ' . $e->getMessage());
                    }
                }
            } catch (\DomainException $e) {
                Log::warning('Auto-approve transition refused — order in unexpected state', [
                    'order_id' => $order->id,
                    'reason'   => $e->getMessage(),
                ]);
            }
        }

        // 11. Notifications (LINE channel only — bell-icon notifications
        // are handled by AdminNotificationObserver on PaymentSlip::created
        // and Order::updated, so no direct calls here anymore. Auto-approve
        // status flip from pending → paid will trip the observer's
        // onOrderUpdated and fire paymentSuccess naturally.)
        try {
            $line = app(\App\Services\LineNotifyService::class);
            $line->notifyNewSlip([
                'order_number' => $order->order_number ?? $order->id,
                'total_amount' => $order->total,
            ]);
        } catch (\Throwable $e) {
            Log::error('Notification error: ' . $e->getMessage());
        }

        // Notify customer
        try {
            if (Schema::hasTable('user_notifications')) {
                $orderNum = $order->order_number ?? "#{$order->id}";
                if ($autoApprove) {
                    UserNotification::create([
                        'user_id'    => Auth::id(),
                        'type'       => 'payment',
                        'title'      => 'ชำระเงินสำเร็จ!',
                        'message'    => "คำสั่งซื้อ {$orderNum} ยอด ฿" . number_format($order->total, 0) . " ได้รับการยืนยันแล้ว พร้อมดาวน์โหลด",
                        'is_read'    => false,
                        'action_url' => "payment/status/{$order->id}",
                    ]);
                } else {
                    UserNotification::create([
                        'user_id'    => Auth::id(),
                        'type'       => 'slip',
                        'title'      => 'อัปโหลดสลิปสำเร็จ',
                        'message'    => "คำสั่งซื้อ {$orderNum} อยู่ระหว่างการตรวจสอบ",
                        'is_read'    => false,
                        'action_url' => "payment/status/{$order->id}",
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('User notification failed: ' . $e->getMessage());
        }

        $message = $autoApprove
            ? 'สลิปได้รับการตรวจสอบอัตโนมัติ ชำระเงินสำเร็จ!'
            : 'อัปโหลดสลิปสำเร็จ รอการตรวจสอบจากแอดมิน';

        // Classic form submit → redirect with flash
        if (!$request->ajax() && !$request->wantsJson()) {
            return redirect()->route('payment.status', $order->id)
                ->with($autoApprove ? 'success' : 'info', $message);
        }

        // AJAX / JSON client → JSON response
        return response()->json([
            'success'     => true,
            'status'      => $autoApprove ? 'approved' : 'pending_review',
            'message'     => $message,
            'score'       => $score,
            'verify_mode' => $verifyMode,
            'redirect'    => route('payment.status', $order->id),
        ]);
    }

    // -----------------------------------------------------------------------
    // Payment Status Page
    // -----------------------------------------------------------------------

    public function status($orderId)
    {
        $order = Order::where('id', $orderId)
            ->where('user_id', Auth::id())
            ->with(['items', 'slips' => function ($q) {
                $q->latest('id');
            }])
            ->firstOrFail();

        $latestSlip        = $order->slips->first();

        // Get the "all photos" token (photo_id IS NULL) first, fallback to any token
        $downloadTokens    = DownloadToken::where('order_id', $order->id)
            ->where('user_id', Auth::id())
            ->orderByRaw('photo_id IS NULL DESC')
            ->get();

        return view('public.payment.status', compact('order', 'latestSlip', 'downloadTokens'));
    }

    // -----------------------------------------------------------------------
    // Payment Status Polling (AJAX)
    // -----------------------------------------------------------------------

    public function checkStatus($orderId)
    {
        $order = Order::where('id', $orderId)
            ->where('user_id', Auth::id())
            ->with(['slips' => function ($q) {
                $q->latest('id');
            }])
            ->firstOrFail();

        $latestSlip = $order->slips->first();

        $downloadUrl = null;
        if ($order->status === 'paid') {
            // Prefer the "all photos" token (photo_id IS NULL)
            $token = DownloadToken::where('order_id', $order->id)
                ->where('user_id', Auth::id())
                ->whereNull('photo_id')
                ->first();
            // Fallback to latest token
            if (!$token) {
                $token = DownloadToken::where('order_id', $order->id)
                    ->where('user_id', Auth::id())
                    ->latest()
                    ->first();
            }
            if ($token) {
                $downloadUrl = route('download.show', $token->token);
            }
        }

        $messages = [
            'pending_payment' => 'รอการชำระเงิน',
            'pending_review'  => 'อยู่ระหว่างการตรวจสอบ',
            'paid'            => 'ชำระเงินสำเร็จ',
            'cancelled'       => 'คำสั่งซื้อถูกยกเลิก',
        ];

        return response()->json([
            'status'        => $order->status,
            'message'       => $messages[$order->status] ?? $order->status,
            'updated_at'    => $order->updated_at?->toIso8601String(),
            'download_url'  => $downloadUrl,
            'slip_status'   => $latestSlip?->verify_status,
            'reject_reason' => $latestSlip?->reject_reason,
        ]);
    }
}

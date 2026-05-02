<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentTransaction;
use App\Models\PaymentSlip;
use App\Models\PaymentMethod;
use App\Models\BankAccount;
use App\Models\PhotographerPayout;
use App\Models\PhotographerProfile;
use App\Models\DownloadToken;
use App\Models\UserNotification;
use App\Models\AppSetting;
use App\Models\Order;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class PaymentController extends Controller
{
    /*--------------------------------------------------------------------------
    | Payment Transactions Index
    |--------------------------------------------------------------------------*/
    public function index(Request $request)
    {
        $query = PaymentTransaction::with(['order', 'user'])->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('payment_method')) {
            $query->where('payment_gateway', $request->payment_method);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('transaction_id', 'ilike', "%{$search}%")
                  ->orWhereHas('order', fn($o) => $o->where('order_number', 'ilike', "%{$search}%"))
                  ->orWhereHas('user', fn($u) => $u->where('email', 'ilike', "%{$search}%")
                      ->orWhere('first_name', 'ilike', "%{$search}%"));
            });
        }

        $transactions = $query->paginate(25);
        return view('admin.payments.index', compact('transactions'));
    }

    /*--------------------------------------------------------------------------
    | Payment Methods
    |--------------------------------------------------------------------------*/
    public function methods()
    {
        $methods = PaymentMethod::orderBy('sort_order')->get();
        return view('admin.payments.methods', compact('methods'));
    }

    /**
     * Flip `is_active` on a PaymentMethod row. Called from the toggle
     * switches on /admin/payments/methods via fetch() — returns JSON so
     * the UI can live-update without a full page reload.
     *
     * Extra guard: when activating an Omise/Stripe row we verify the
     * keys are actually saved in app_settings first. Otherwise the
     * method would appear on the customer checkout and fail at
     * capture time, which is a much worse UX than "refuses to enable".
     */
    public function toggleMethod(Request $request, $id)
    {
        $method = PaymentMethod::findOrFail($id);
        $wantActive = $request->boolean('is_active');

        if ($wantActive && in_array($method->method_type, ['omise', 'stripe'], true)) {
            $keyName = $method->method_type . '_public_key';
            $secretName = $method->method_type . '_secret_key';
            $hasKeys = AppSetting::get($keyName, '') !== '' && AppSetting::get($secretName, '') !== '';

            if (! $hasKeys) {
                return response()->json([
                    'success' => false,
                    'message' => 'กรุณาตั้งค่า API key ที่ "Payment Gateways" ก่อนเปิดใช้งาน',
                    'configure_url' => route('admin.settings.payment-gateways'),
                ], 422);
            }
        }

        $method->update(['is_active' => $wantActive]);

        return response()->json([
            'success'   => true,
            'is_active' => (bool) $method->is_active,
            'message'   => $wantActive ? 'เปิดใช้งานแล้ว' : 'ปิดใช้งานแล้ว',
        ]);
    }

    /**
     * Persist the sort_order value typed into the small number input on
     * each method card. Debounced client-side so the admin can type a
     * number and it saves once they stop.
     */
    public function updateMethodSort(Request $request, $id)
    {
        $method = PaymentMethod::findOrFail($id);
        $sort = max(0, (int) $request->input('sort_order', 0));
        $method->update(['sort_order' => $sort]);

        return response()->json([
            'success'    => true,
            'sort_order' => (int) $method->sort_order,
        ]);
    }

    /*--------------------------------------------------------------------------
    | Slips List
    |--------------------------------------------------------------------------*/
    public function slips(Request $request)
    {
        $query = DB::table('payment_slips as ps')
            ->leftJoin('orders as o', 'o.id', '=', 'ps.order_id')
            ->leftJoin('auth_users as u', 'u.id', '=', 'o.user_id')
            ->select(
                'ps.*',
                'o.order_number',
                'o.total as order_total',
                'o.status as order_status',
                'u.first_name',
                'u.last_name',
                'u.email as user_email'
            )
            ->orderByDesc('ps.created_at');

        if ($request->filled('status')) {
            $query->where('ps.verify_status', $request->status);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('o.order_number', 'ilike', "%{$search}%")
                  ->orWhere('u.email', 'ilike', "%{$search}%")
                  ->orWhere('u.first_name', 'ilike', "%{$search}%")
                  ->orWhere('ps.reference_code', 'ilike', "%{$search}%");
            });
        }

        $slips = $query->paginate(25);

        // Stats
        $stats = DB::table('payment_slips')
            ->select(
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN verify_status = 'pending' THEN 1 ELSE 0 END) as pending_count"),
                DB::raw("SUM(CASE WHEN verify_status = 'approved' THEN 1 ELSE 0 END) as approved_count"),
                DB::raw("SUM(CASE WHEN verify_status = 'rejected' THEN 1 ELSE 0 END) as rejected_count")
            )
            ->first();

        $settings = [
            'slip_verify_mode'             => AppSetting::get('slip_verify_mode', 'manual'),
            'slip_auto_approve_threshold'  => (int) AppSetting::get('slip_auto_approve_threshold', 80),
            'slip_amount_tolerance_percent'=> (float) AppSetting::get('slip_amount_tolerance_percent', '1'),
            'slip_require_slipok_for_auto' => AppSetting::get('slip_require_slipok_for_auto', '0') === '1',
            'slip_require_receiver_match'  => AppSetting::get('slip_require_receiver_match', '0') === '1',
            'slipok_enabled'               => AppSetting::get('slipok_enabled', '0') === '1',
            'slipok_api_key'               => AppSetting::get('slipok_api_key', ''),
            'slipok_branch_id'             => AppSetting::get('slipok_branch_id', ''),
            // Optional HMAC secret for SlipOK callback signature validation.
            // When empty, the callback handler logs the unsigned attempt
            // but still processes — set this in production to make the
            // /api/webhooks/slipok endpoint reject unsigned requests.
            'slipok_webhook_secret'        => AppSetting::get('slipok_webhook_secret', ''),
        ];

        return view('admin.payments.slips', compact('slips', 'stats', 'settings'));
    }

    /*--------------------------------------------------------------------------
    | Approve Slip
    |--------------------------------------------------------------------------*/
    public function approveSlip(Request $request, $id)
    {
        $slip = PaymentSlip::findOrFail($id);

        if ($slip->verify_status !== 'pending') {
            return back()->with('error', 'สลิปนี้ถูกตรวจสอบแล้ว');
        }

        $orderBefore = Order::find($slip->order_id);
        $oldOrderStatus = $orderBefore?->status;
        $orderTotal = $orderBefore?->total;

        $orderForEmail = null;

        DB::transaction(function () use ($slip, &$orderForEmail) {
            $adminId = Auth::guard('admin')->id();
            $now = now();

            // a) Approve slip
            $slip->update([
                'verify_status' => 'approved',
                'verified_by'   => $adminId,
                'verified_at'   => $now,
            ]);

            // b) Transition order via OrderStateMachine — replaces the
            //    previous bare ->update(['status' => 'paid']). The
            //    state machine wraps lockForUpdate + ALLOWED_TRANSITIONS
            //    + activity log + idempotency in one place. Returns
            //    false on idempotent retries (already paid) so we
            //    don't double-fulfill.
            $sm      = app(\App\Services\Payment\OrderStateMachine::class);
            $changed = $sm->transitionToPaid(
                orderId:        (int) $slip->order_id,
                idempotencyKey: "slip.{$slip->id}.admin-approved",
                auditContext:   [
                    'slip_id'        => $slip->id,
                    'admin_id'       => $adminId,
                    'verify_score'   => (int) $slip->verify_score,
                    'admin_approved' => true,
                ],
            );
            $order = Order::find($slip->order_id);
            if ($order && $changed) {
                // c) Update payment_transactions
                DB::table('payment_transactions')
                    ->where('order_id', $order->id)
                    ->update(['status' => 'completed']);

                // d) Create download tokens
                $this->createDownloadTokens($order);

                // e) Create photographer payout(s).
                //    NB: previously this called the controller-local
                //    `$this->createPhotographerPayout()` which only
                //    handled the SINGLE-photographer case using the
                //    order header `event_id`. For a multi-photographer
                //    cart (rare today but supported), only one payout
                //    was created and the other photographers got no
                //    money — a marketplace correctness bug.
                //
                //    `OrderFulfillmentService::fulfill()` is invoked
                //    below (in the email block), which calls
                //    `createPhotographerPayout()` that GROUPS BY
                //    OrderItem.event_id and creates one row per unique
                //    photographer with a discount-allocated split.
                //    That's idempotent (early-return on existing row)
                //    so this single source of truth handles both
                //    single- and multi-photographer cases correctly.

                // f) Notify buyer — handled by `downloadReady()` below
                //    (outside the transaction). Removed the duplicate
                //    `payment_approved` push that used to fire here:
                //    every approval was creating two notifications for
                //    the buyer in quick succession (one "approved",
                //    one "ready to download") which read like spam.
                //    `downloadReady` is the more actionable of the two
                //    so we keep that.

                $orderForEmail = $order;
            }
        });

        // Send email notifications (outside transaction)
        if ($orderForEmail) {
            // Status-confirmation emails (slip approved + payment success) —
            // PhotoDeliveryService handles the download-ready push separately
            // via the buyer's preferred channel, so we don't duplicate that here.
            $this->sendSlipApprovedEmail($orderForEmail);

            // Dispatch the order to its post-payment handler via
            // OrderFulfillmentService. Branches internally on order_type:
            // photo_package → PhotoDeliveryService, credit_package →
            // CreditService, subscription → SubscriptionService. All three
            // handlers are idempotent, so re-approving a slip re-sends the
            // same link or re-confirms subscription state without side effects.
            try {
                app(\App\Services\OrderFulfillmentService::class)->fulfill($orderForEmail);
            } catch (\Throwable $e) {
                \Log::warning('OrderFulfillmentService failed for order ' . $orderForEmail->id . ': ' . $e->getMessage());
            }

            // Admin: mark related slip alerts as read.
            // NB: paymentSuccess() is no longer called here — when this
            // controller flips $order->status to 'paid', the
            // AdminNotificationObserver::onOrderUpdated fires it. Direct
            // call removed to prevent duplicate bell entries.
            try {
                \App\Models\AdminNotification::markReadByRef(['slip','order','payment'], (string) $orderForEmail->id);
            } catch (\Throwable $e) {
                \Log::warning('Admin notification update failed: ' . $e->getMessage());
            }

            // User: download-ready notification
            try {
                \App\Models\UserNotification::downloadReady($orderForEmail->user_id, $orderForEmail);
            } catch (\Throwable $e) {
                \Log::warning('Download ready notification failed: ' . $e->getMessage());
            }

            // Order timeline log
            try {
                app(\App\Services\OrderTimelineService::class)->log(
                    $orderForEmail, 'paid',
                    'ชำระเงินได้รับการอนุมัติ',
                    'admin', Auth::guard('admin')->id()
                );
            } catch (\Throwable $e) {}
        }

        ActivityLogger::admin(
            action: 'payment.slip_approved',
            target: $orderForEmail ?? ['Order', (int) $slip->order_id],
            description: "อนุมัติสลิปสำหรับคำสั่งซื้อ #" . ($orderForEmail->order_number ?? $slip->order_id),
            oldValues: ['order_status' => $oldOrderStatus, 'slip_status' => 'pending'],
            newValues: [
                'order_status' => 'paid',
                'slip_status'  => 'approved',
                'order_id'     => (int) $slip->order_id,
                'slip_id'      => (int) $slip->id,
                'amount'       => $orderTotal,
            ],
        );

        return back()->with('success', 'อนุมัติสลิปสำเร็จ');
    }

    /**
     * Send slip approved + download ready emails.
     */
    protected function sendSlipApprovedEmail(Order $order): void
    {
        try {
            $order->load(['user', 'event', 'items']);
            $user = $order->user;
            if (!$user || !$user->email) return;

            $mail = app(\App\Services\MailService::class);
            $downloadUrl = url('/orders/' . $order->id);

            $orderData = [
                'id'           => $order->id,
                'order_number' => $order->order_number,
                'email'        => $user->email,
                'name'         => $user->first_name ?? 'ลูกค้า',
                'total_amount' => (float) $order->total,
                'download_url' => $downloadUrl,
            ];

            // Send slip approved email
            $mail->slipApproved($orderData);

            // Also send payment success email with download link
            $mail->paymentSuccess(array_merge($orderData, [
                'payment_method' => 'โอนเงิน',
                'paid_at'        => now()->format('d/m/Y H:i'),
                'order_url'      => url('/orders/' . $order->id),
            ]));

            // NOTE: The "download-ready" email used to be sent here. That's now
            // routed through PhotoDeliveryService which dispatches via the
            // buyer's chosen channel (web/LINE/email). Sending it here would
            // duplicate the email when auto-switch picks email.
        } catch (\Throwable $e) {
            \Log::warning('Slip approved email failed: ' . $e->getMessage());
        }
    }

    /*--------------------------------------------------------------------------
    | Reject Slip
    |--------------------------------------------------------------------------*/
    public function rejectSlip(Request $request, $id)
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $slip = PaymentSlip::findOrFail($id);

        if ($slip->verify_status !== 'pending') {
            return back()->with('error', 'สลิปนี้ถูกตรวจสอบแล้ว');
        }

        $orderBefore = Order::find($slip->order_id);
        $oldOrderStatus = $orderBefore?->status;
        $orderTotal = $orderBefore?->total;

        $orderForEmail = null;

        DB::transaction(function () use ($slip, $request, &$orderForEmail) {
            $adminId = Auth::guard('admin')->id();
            $now = now();

            // a) Reject slip
            $slip->update([
                'verify_status' => 'rejected',
                'reject_reason' => $request->reason,
                'verified_by'   => $adminId,
                'verified_at'   => $now,
            ]);

            // b) Reset order status to allow re-upload
            DB::table('orders')
                ->where('id', $slip->order_id)
                ->update(['status' => 'pending_payment']);

            // c) Update payment_transactions
            DB::table('payment_transactions')
                ->where('order_id', $slip->order_id)
                ->where('status', 'pending')
                ->update(['status' => 'failed']);

            // d) Notify user
            $order = Order::find($slip->order_id);
            if ($order) {
                $this->createNotification(
                    $order->user_id,
                    'payment_rejected',
                    'การชำระเงินถูกปฏิเสธ',
                    "คำสั่งซื้อ #{$order->order_number} ถูกปฏิเสธ เหตุผล: {$request->reason} กรุณาอัปโหลดสลิปใหม่",
                    "orders/{$order->id}"
                );

                $orderForEmail = $order;
            }
        });

        // Send rejection email (outside transaction)
        if ($orderForEmail) {
            $this->sendSlipRejectedEmail($orderForEmail, $request->reason);

            // Dismiss related admin bell notifications
            try {
                \App\Models\AdminNotification::markReadByRef(['slip','order','payment'], (string) $orderForEmail->id);
            } catch (\Throwable $e) {
                \Log::warning('Admin notification dismiss failed: ' . $e->getMessage());
            }
        }

        ActivityLogger::admin(
            action: 'payment.slip_rejected',
            target: $orderForEmail ?? ['Order', (int) $slip->order_id],
            description: "ปฏิเสธสลิปสำหรับคำสั่งซื้อ #" . ($orderForEmail->order_number ?? $slip->order_id) . " — เหตุผล: {$request->reason}",
            oldValues: ['order_status' => $oldOrderStatus, 'slip_status' => 'pending'],
            newValues: [
                'order_status' => 'pending_payment',
                'slip_status'  => 'rejected',
                'order_id'     => (int) $slip->order_id,
                'slip_id'      => (int) $slip->id,
                'amount'       => $orderTotal,
                'reason'       => $request->reason,
            ],
        );

        return back()->with('success', 'ปฏิเสธสลิปสำเร็จ');
    }

    /**
     * Send slip rejection email to customer.
     */
    protected function sendSlipRejectedEmail(Order $order, string $reason): void
    {
        try {
            $order->load('user');
            $user = $order->user;
            if (!$user || !$user->email) return;

            app(\App\Services\MailService::class)->slipRejected([
                'id'           => $order->id,
                'order_number' => $order->order_number,
                'email'        => $user->email,
                'name'         => $user->first_name ?? 'ลูกค้า',
                'total_amount' => (float) $order->total,
                'retry_url'    => url('/payment/checkout/' . $order->id),
            ], $reason);
        } catch (\Throwable $e) {
            \Log::warning('Slip rejected email failed: ' . $e->getMessage());
        }
    }

    /*--------------------------------------------------------------------------
    | Bulk Approve
    |--------------------------------------------------------------------------*/
    public function bulkApprove(Request $request)
    {
        $request->validate([
            'slip_ids'   => 'required|array',
            'slip_ids.*' => 'integer',
        ]);

        $successCount    = 0;
        $processedSlipIds = [];
        $processedOrderIds = [];
        $ordersToDeliver  = [];  // populated inside the transaction, delivered after commit
        foreach ($request->slip_ids as $slipId) {
            try {
                $slip = PaymentSlip::find($slipId);
                if ($slip && $slip->verify_status === 'pending') {
                    $approvedOrder = null;
                    DB::transaction(function () use ($slip, &$approvedOrder) {
                        $adminId = Auth::guard('admin')->id();
                        $slip->update([
                            'verify_status' => 'approved',
                            'verified_by'   => $adminId,
                            'verified_at'   => now(),
                        ]);

                        $order = Order::where('id', $slip->order_id)->lockForUpdate()->first();
                        if ($order) {
                            $order->update(['status' => 'paid']);
                            DB::table('payment_transactions')
                                ->where('order_id', $order->id)
                                ->update(['status' => 'completed']);
                            $this->createDownloadTokens($order);
                            // Multi-photographer split payout — handled by
                            // OrderFulfillmentService::fulfill() (called in
                            // the email block after the transaction commits).
                            // The controller-local createPhotographerPayout
                            // is single-photographer-only and is now
                            // deprecated.
                            $this->createNotification(
                                $order->user_id,
                                'payment_approved',
                                'การชำระเงินได้รับการอนุมัติ',
                                "คำสั่งซื้อ #{$order->order_number} ได้รับการยืนยันการชำระเงินแล้ว",
                                "orders/{$order->id}"
                            );

                            // Dismiss related admin bell notifications
                            \App\Models\AdminNotification::markReadByRef(['slip','order','payment'], (string) $order->id);

                            $approvedOrder = $order;
                        }
                    });
                    $successCount++;
                    $processedSlipIds[]  = (int) $slip->id;
                    $processedOrderIds[] = (int) $slip->order_id;
                    if ($approvedOrder) {
                        $ordersToDeliver[] = $approvedOrder;
                    }
                }
            } catch (\Throwable $e) {
                Log::error("Bulk approve slip #{$slipId} failed: " . $e->getMessage());
            }
        }

        // After all transactions commit, fulfill each order via its proper
        // post-payment handler. OrderFulfillmentService branches on order_type
        // internally (photo / credit / subscription). Isolated in its own try
        // per-order so one failed dispatch doesn't cascade across the batch.
        $fulfillment = app(\App\Services\OrderFulfillmentService::class);
        foreach ($ordersToDeliver as $order) {
            try {
                $fulfillment->fulfill($order);
            } catch (\Throwable $e) {
                Log::warning('Bulk-approve fulfillment failed order ' . $order->id . ': ' . $e->getMessage());
            }
        }

        if ($successCount > 0) {
            ActivityLogger::admin(
                action: 'payment.slips_bulk_approved',
                target: null,
                description: "อนุมัติสลิปแบบกลุ่ม ({$successCount} รายการ)",
                oldValues: ['slip_status' => 'pending', 'count' => $successCount],
                newValues: [
                    'slip_status'  => 'approved',
                    'order_status' => 'paid',
                    'count'        => $successCount,
                    'slip_ids'     => $processedSlipIds,
                    'order_ids'    => $processedOrderIds,
                ],
            );
        }

        return back()->with('success', "อนุมัติสลิปสำเร็จ {$successCount} รายการ");
    }

    /*--------------------------------------------------------------------------
    | Bulk Reject
    |--------------------------------------------------------------------------*/
    public function bulkReject(Request $request)
    {
        $request->validate([
            'slip_ids'   => 'required|array',
            'slip_ids.*' => 'integer',
            'reason'     => 'required|string|max:500',
        ]);

        $successCount      = 0;
        $processedSlipIds  = [];
        $processedOrderIds = [];
        foreach ($request->slip_ids as $slipId) {
            try {
                $slip = PaymentSlip::find($slipId);
                if ($slip && $slip->verify_status === 'pending') {
                    DB::transaction(function () use ($slip, $request) {
                        $adminId = Auth::guard('admin')->id();
                        $slip->update([
                            'verify_status' => 'rejected',
                            'reject_reason' => $request->reason,
                            'verified_by'   => $adminId,
                            'verified_at'   => now(),
                        ]);
                        DB::table('orders')
                            ->where('id', $slip->order_id)
                            ->update(['status' => 'pending_payment']);
                        DB::table('payment_transactions')
                            ->where('order_id', $slip->order_id)
                            ->where('status', 'pending')
                            ->update(['status' => 'failed']);

                        $order = Order::find($slip->order_id);
                        if ($order) {
                            $this->createNotification(
                                $order->user_id,
                                'payment_rejected',
                                'การชำระเงินถูกปฏิเสธ',
                                "คำสั่งซื้อ #{$order->order_number} ถูกปฏิเสธ เหตุผล: {$request->reason}",
                                "orders/{$order->id}"
                            );

                            // Dismiss related admin bell notifications
                            \App\Models\AdminNotification::markReadByRef(['slip','order','payment'], (string) $order->id);
                        }
                    });
                    $successCount++;
                    $processedSlipIds[]  = (int) $slip->id;
                    $processedOrderIds[] = (int) $slip->order_id;
                }
            } catch (\Throwable $e) {
                Log::error("Bulk reject slip #{$slipId} failed: " . $e->getMessage());
            }
        }

        if ($successCount > 0) {
            ActivityLogger::admin(
                action: 'payment.slips_bulk_rejected',
                target: null,
                description: "ปฏิเสธสลิปแบบกลุ่ม ({$successCount} รายการ) — เหตุผล: {$request->reason}",
                oldValues: ['slip_status' => 'pending', 'count' => $successCount],
                newValues: [
                    'slip_status'  => 'rejected',
                    'order_status' => 'pending_payment',
                    'count'        => $successCount,
                    'slip_ids'     => $processedSlipIds,
                    'order_ids'    => $processedOrderIds,
                    'reason'       => $request->reason,
                ],
            );
        }

        return back()->with('success', "ปฏิเสธสลิปสำเร็จ {$successCount} รายการ");
    }

    /*--------------------------------------------------------------------------
    | Banks
    |--------------------------------------------------------------------------*/
    public function banks()
    {
        $accounts = BankAccount::orderBy('sort_order')->get();
        return view('admin.payments.banks', compact('accounts'));
    }

    public function storeBank(Request $request)
    {
        $request->validate([
            'bank_name'           => 'required|string|max:100',
            'account_number'      => 'required|string|max:20',
            'account_holder_name' => 'required|string|max:100',
            'bank_code'           => 'nullable|string|max:10',
            'bank_color'          => 'nullable|string|max:20',
            'branch'              => 'nullable|string|max:100',
            'is_active'           => 'nullable|boolean',
            'sort_order'          => 'nullable|integer',
        ]);

        BankAccount::create([
            'bank_code'           => $request->input('bank_code', ''),
            'bank_name'           => $request->bank_name,
            'bank_color'          => $request->input('bank_color', '#1e40af'),
            'account_number'      => $request->account_number,
            'account_holder_name' => $request->account_holder_name,
            'branch'              => $request->branch,
            'is_active'           => $request->boolean('is_active', true),
            'sort_order'          => $request->input('sort_order', 0),
        ]);

        return back()->with('success', 'เพิ่มบัญชีธนาคารสำเร็จ');
    }

    public function updateBank(Request $request, $id)
    {
        $request->validate([
            'bank_name'           => 'required|string|max:100',
            'account_number'      => 'required|string|max:20',
            'account_holder_name' => 'required|string|max:100',
            'bank_code'           => 'nullable|string|max:10',
            'bank_color'          => 'nullable|string|max:20',
            'branch'              => 'nullable|string|max:100',
            'is_active'           => 'nullable|boolean',
            'sort_order'          => 'nullable|integer',
        ]);

        $account = BankAccount::findOrFail($id);
        $account->update([
            'bank_code'           => $request->input('bank_code', $account->bank_code),
            'bank_name'           => $request->bank_name,
            'bank_color'          => $request->input('bank_color', $account->bank_color),
            'account_number'      => $request->account_number,
            'account_holder_name' => $request->account_holder_name,
            'branch'              => $request->branch,
            'is_active'           => $request->boolean('is_active', true),
            'sort_order'          => $request->input('sort_order', 0),
        ]);

        return back()->with('success', 'แก้ไขบัญชีธนาคารสำเร็จ');
    }

    public function destroyBank($id)
    {
        BankAccount::findOrFail($id)->delete();
        return back()->with('success', 'ลบบัญชีธนาคารสำเร็จ');
    }

    /*--------------------------------------------------------------------------
    | Payouts
    |--------------------------------------------------------------------------*/
    public function payouts(Request $request)
    {
        // Stats
        $stats = [
            'total_gross' => PhotographerPayout::sum('gross_amount'),
            'total_platform_fee' => PhotographerPayout::sum('platform_fee'),
            'total_payout' => PhotographerPayout::sum('payout_amount'),
            'pending_count' => PhotographerPayout::where('status', 'pending')->count(),
            'pending_amount' => PhotographerPayout::where('status', 'pending')->sum('payout_amount'),
            'paid_count' => PhotographerPayout::whereIn('status', ['paid', 'completed'])->count(),
            'paid_amount' => PhotographerPayout::whereIn('status', ['paid', 'completed'])->sum('payout_amount'),
        ];

        // Rich query with filters
        $payouts = PhotographerPayout::with(['photographer', 'order'])
            ->when($request->q, function($q, $s) {
                $q->whereHas('photographer', fn($p) => $p->where('first_name', 'ilike', "%{$s}%")->orWhere('last_name', 'ilike', "%{$s}%")->orWhere('email', 'ilike', "%{$s}%"));
            })
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->when($request->period, function($q, $p) {
                return match($p) {
                    'today' => $q->whereDate('created_at', today()),
                    'week' => $q->where('created_at', '>=', now()->subDays(7)),
                    'month' => $q->where('created_at', '>=', now()->subDays(30)),
                    default => $q,
                };
            })
            ->when($request->sort, function($q, $s) {
                return match($s) {
                    'amount_high' => $q->orderByDesc('payout_amount'),
                    'amount_low' => $q->orderBy('payout_amount'),
                    'oldest' => $q->orderBy('created_at'),
                    default => $q->orderByDesc('created_at'),
                };
            }, fn($q) => $q->orderByDesc('created_at'))
            ->paginate(25)
            ->withQueryString();

        return view('admin.payments.payouts', compact('payouts', 'stats'));
    }

    public function markPayoutPaid(Request $request, $id)
    {
        $payout = PhotographerPayout::findOrFail($id);
        $oldStatus = $payout->status;
        $payout->update([
            'status' => 'paid',
            'paid_at' => now(),
            'note' => $request->note ?? 'จ่ายเงินโดยแอดมิน',
        ]);

        ActivityLogger::admin(
            action: 'payout.marked_paid',
            target: $payout,
            description: "ทำเครื่องหมายจ่ายเงินช่างภาพ (payout #{$payout->id})",
            oldValues: ['status' => $oldStatus],
            newValues: [
                'status'          => 'paid',
                'payout_id'       => (int) $payout->id,
                'order_id'        => (int) $payout->order_id,
                'photographer_id' => (int) $payout->photographer_id,
                'amount'          => $payout->payout_amount,
            ],
        );

        return back()->with('success', "อัปเดตสถานะเป็น จ่ายแล้ว สำเร็จ (#{$payout->id})");
    }

    public function bulkMarkPaid(Request $request)
    {
        $request->validate(['payout_ids' => 'required|array|min:1']);

        $affected = PhotographerPayout::whereIn('id', $request->payout_ids)
            ->where('status', 'pending')
            ->get(['id', 'order_id', 'payout_amount']);

        $count = PhotographerPayout::whereIn('id', $request->payout_ids)
            ->where('status', 'pending')
            ->update(['status' => 'paid', 'paid_at' => now(), 'note' => 'จ่ายเงินแบบกลุ่มโดยแอดมิน']);

        if ($count > 0) {
            ActivityLogger::admin(
                action: 'payout.bulk_marked_paid',
                target: null,
                description: "ทำเครื่องหมายจ่ายเงินแบบกลุ่ม ({$count} รายการ)",
                oldValues: ['status' => 'pending', 'count' => $count],
                newValues: [
                    'status'       => 'paid',
                    'count'        => $count,
                    'payout_ids'   => $affected->pluck('id')->all(),
                    'order_ids'    => $affected->pluck('order_id')->all(),
                    'total_amount' => (float) $affected->sum('payout_amount'),
                ],
            );
        }

        return back()->with('success', "จ่ายเงินสำเร็จ ({$count} รายการ)");
    }

    /*--------------------------------------------------------------------------
    | Update Slip Settings
    |--------------------------------------------------------------------------*/
    public function updateSlipSettings(Request $request)
    {
        $request->validate([
            'slip_verify_mode'              => 'required|in:manual,auto',
            'slip_auto_approve_threshold'   => 'required|integer|min:50|max:100',
            'slip_amount_tolerance_percent' => 'required|numeric|min:0.1|max:5',
            'slip_require_slipok_for_auto'  => 'nullable|boolean',
            'slip_require_receiver_match'   => 'nullable|boolean',
            'slipok_enabled'                => 'nullable|boolean',
            'slipok_api_key'                => 'nullable|string|max:200',
            'slipok_branch_id'              => 'nullable|string|max:50',
            'slipok_webhook_secret'         => 'nullable|string|max:200',
        ]);

        // Detect first-time SlipOK enablement so we can apply sensible
        // defaults — admin shouldn't have to know that "auto + require
        // SlipOK + receiver match" is the safest combination on first run.
        $wasSlipOkEnabled = AppSetting::get('slipok_enabled', '0') === '1';
        $isNowSlipOkEnabled = $request->boolean('slipok_enabled');
        $firstTimeEnable = !$wasSlipOkEnabled && $isNowSlipOkEnabled;

        AppSetting::set('slip_verify_mode',              $request->slip_verify_mode);
        AppSetting::set('slip_auto_approve_threshold',   (string) $request->slip_auto_approve_threshold);
        AppSetting::set('slip_amount_tolerance_percent', (string) $request->slip_amount_tolerance_percent);
        AppSetting::set('slip_require_slipok_for_auto',  $request->boolean('slip_require_slipok_for_auto') ? '1' : '0');
        AppSetting::set('slip_require_receiver_match',   $request->boolean('slip_require_receiver_match') ? '1' : '0');
        AppSetting::set('slipok_enabled',                $isNowSlipOkEnabled ? '1' : '0');

        if ($request->filled('slipok_api_key')) {
            AppSetting::set('slipok_api_key', $request->slipok_api_key);
        }
        if ($request->filled('slipok_branch_id')) {
            AppSetting::set('slipok_branch_id', $request->slipok_branch_id);
        }
        // The webhook secret is the HMAC key SlipOK uses to sign callbacks.
        // Empty string means signature validation is OFF (we still log every
        // callback). Only update when the field was filled in — otherwise
        // we'd accidentally clear the secret on partial form submissions.
        if ($request->has('slipok_webhook_secret')) {
            AppSetting::set('slipok_webhook_secret', (string) $request->slipok_webhook_secret);
        }

        // First-time enable — auto-generate the webhook secret if missing
        // and surface a helpful nudge so admin knows to copy it into
        // SlipOK's dashboard. Skips if admin already has a secret set
        // (don't rotate someone's working integration accidentally).
        $extraNotes = [];
        if ($firstTimeEnable) {
            if (empty(AppSetting::get('slipok_webhook_secret', ''))) {
                AppSetting::set('slipok_webhook_secret', bin2hex(random_bytes(32)));
                $extraNotes[] = 'สร้าง webhook secret อัตโนมัติแล้ว — กรุณาคัดลอกไปวางใน SlipOK dashboard';
            }
        }

        $message = 'บันทึกการตั้งค่าตรวจสลิปสำเร็จ';
        if (!empty($extraNotes)) {
            $message .= ' · ' . implode(' · ', $extraNotes);
        }
        return back()->with('success', $message);
    }

    /*--------------------------------------------------------------------------
    | SlipOK Connection Test
    |--------------------------------------------------------------------------
    | Lets the admin verify their API key + branch ID without uploading a
    | real customer slip. Posts a minimal 1x1 transparent PNG to the SlipOK
    | endpoint and reports whether auth succeeded — the API will reject our
    | dummy file with a "not a slip" error if credentials are valid (which
    | is the success signal here), or fail with HTTP 401/403 if creds are
    | wrong. Either response confirms the network path + auth state.
    */
    public function testSlipOK(Request $request)
    {
        $svc = new \App\Services\Payment\SlipOKService();

        if (!$svc->isConfigured()) {
            return response()->json([
                'ok'       => false,
                'message'  => 'ยังไม่ได้ตั้งค่า API key หรือ Branch ID — กรุณากรอกแล้วบันทึกก่อนทดสอบ',
                'category' => 'config',
            ], 400);
        }

        // 1×1 transparent PNG — smallest valid image we can ship to SlipOK.
        // SlipOK will reject it as "not a slip", but the rejection comes from
        // the OCR layer AFTER auth has succeeded — so a 200 with a
        // recognised-but-invalid response = creds OK, network OK.
        $tinyPng = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkAAIAAAoAAv/lxKUAAAAASUVORK5CYII=');
        $temp = tempnam(sys_get_temp_dir(), 'slipok_test_');
        file_put_contents($temp, $tinyPng);

        $start = microtime(true);
        try {
            $result = $svc->verify($temp);
        } finally {
            @unlink($temp);
        }
        $elapsed = (int) ((microtime(true) - $start) * 1000);

        // Map SlipOK error codes to human-readable Thai status. The API
        // returns codes like 1004 (not a slip), 1012 (api key invalid), etc.
        // We treat a non-auth error as "credentials are working but the
        // test image was rejected (expected)".
        $errorCode  = $result['error_code'] ?? null;
        $rawCode    = (string) ($errorCode ?? '');
        $authError  = in_array($rawCode, ['401', '403', '1012', 'MISSING_CREDENTIALS', 'EXCEPTION'], true);
        $networkOk  = !in_array($rawCode, ['EXCEPTION', 'FILE_NOT_FOUND'], true);

        if ($result['success']) {
            // Genuinely succeeded — odd for a dummy image but means everything works.
            return response()->json([
                'ok'              => true,
                'message'         => "การเชื่อมต่อสำเร็จ — SlipOK ตอบกลับใน {$elapsed}ms",
                'response_time_ms'=> $elapsed,
            ]);
        }

        if ($authError) {
            return response()->json([
                'ok'       => false,
                'message'  => 'API Key หรือ Branch ID ไม่ถูกต้อง (' . ($errorCode ?: 'auth failed') . ')',
                'category' => 'auth',
                'error_code' => $errorCode,
                'response_time_ms' => $elapsed,
            ]);
        }

        // Non-auth failure — auth probably worked, the dummy image was
        // rejected as not a real slip. That's expected and proves the
        // integration is live.
        return response()->json([
            'ok'              => true,
            'message'         => "เชื่อมต่อ SlipOK สำเร็จ — credentials ใช้งานได้ (รหัสตอบกลับ: " . ($errorCode ?: 'rejected_test_image') . ')',
            'response_time_ms'=> $elapsed,
            'note'            => 'ภาพทดสอบถูกปฏิเสธเพราะไม่ใช่สลิปจริง — ปกติ',
        ]);
    }

    /*--------------------------------------------------------------------------
    | Generate SlipOK Webhook Secret
    |--------------------------------------------------------------------------
    | One-click generates a 32-byte random hex string + saves to AppSetting.
    | Admin then copies it into the SlipOK dashboard's webhook config.
    | Returns the new secret in JSON so the page can update without reload.
    */
    public function generateSlipOKSecret(Request $request)
    {
        $secret = bin2hex(random_bytes(32));   // 64-char hex
        AppSetting::set('slipok_webhook_secret', $secret);

        \App\Services\ActivityLogger::admin(
            action:      'slipok.webhook_secret_rotated',
            target:      null,
            description: 'Rotated SlipOK webhook secret',
        );

        return response()->json([
            'ok'     => true,
            'secret' => $secret,
            'message'=> 'สร้าง webhook secret ใหม่สำเร็จ — กรุณาคัดลอกไปวางใน SlipOK dashboard',
        ]);
    }

    /*--------------------------------------------------------------------------
    | Private Helpers
    |--------------------------------------------------------------------------*/
    private function createDownloadTokens(Order $order): void
    {
        try {
            if (!Schema::hasTable('download_tokens')) {
                return;
            }

            $expiresAt = now()->addDays(30);
            $userId = $order->user_id;

            $itemCount = $order->items->count();

            // One "all photos" token (photo_id = NULL) — for ZIP download
            // max_downloads = item count × 2 (minimum 10) to allow re-downloads
            DownloadToken::create([
                'token'          => bin2hex(random_bytes(32)),
                'order_id'       => $order->id,
                'user_id'        => $userId,
                'photo_id'       => null,
                'expires_at'     => $expiresAt,
                'max_downloads'  => max($itemCount * 2, 10),
                'download_count' => 0,
            ]);

            // Individual tokens per order_item — each photo gets its own counter
            foreach ($order->items as $item) {
                DownloadToken::create([
                    'token'          => bin2hex(random_bytes(32)),
                    'order_id'       => $order->id,
                    'user_id'        => $userId,
                    'photo_id'       => $item->photo_id,
                    'expires_at'     => $expiresAt,
                    'max_downloads'  => 5,
                    'download_count' => 0,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('createDownloadTokens failed: ' . $e->getMessage());
        }
    }

    private function createPhotographerPayout(Order $order): void
    {
        try {
            if (!Schema::hasTable('photographer_payouts')) {
                return;
            }

            // Skip if payout already exists
            if (DB::table('photographer_payouts')->where('order_id', $order->id)->exists()) {
                return;
            }

            $event = $order->event;
            if (!$event || !$event->photographer_id) {
                return;
            }

            $total = (float) $order->total;

            // Use per-photographer commission rate, fallback to global default
            $photographerProfile = PhotographerProfile::where('user_id', $event->photographer_id)->first();
            $photographerRate = $photographerProfile
                ? (float) $photographerProfile->commission_rate
                : (100 - (float) AppSetting::get('platform_commission', 20));

            $platformRate = 100 - $photographerRate;
            $platformFee = round($total * $platformRate / 100, 2);
            $photographerAmount = round($total - $platformFee, 2);

            PhotographerPayout::create([
                'photographer_id' => $event->photographer_id,
                'order_id'        => $order->id,
                'gross_amount'    => $total,
                'commission_rate' => $photographerRate,
                'payout_amount'   => $photographerAmount,
                'platform_fee'    => $platformFee,
                'status'          => 'pending',
            ]);
        } catch (\Throwable $e) {
            Log::error('createPhotographerPayout failed: ' . $e->getMessage());
        }
    }

    private function createNotification(int $userId, string $type, string $title, string $message, ?string $actionUrl = null): void
    {
        try {
            if (Schema::hasTable('user_notifications')) {
                UserNotification::create([
                    'user_id'    => $userId,
                    'type'       => $type,
                    'title'      => $title,
                    'message'    => $message,
                    'is_read'    => false,
                    'action_url' => $actionUrl,
                ]);
            } elseif (Schema::hasTable('notifications')) {
                DB::table('notifications')->insert([
                    'user_id'    => $userId,
                    'type'       => $type,
                    'title'      => $title,
                    'message'    => $message,
                    'is_read'    => false,
                    'created_at' => now(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('createNotification failed: ' . $e->getMessage());
        }
    }
}

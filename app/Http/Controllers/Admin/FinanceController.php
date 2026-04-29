<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\PaymentRefund;
use App\Models\PaymentSlip;
use App\Models\PhotographerPayout;
use App\Models\Event;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class FinanceController extends Controller
{
    public function index()
    {
        $totalRevenue   = Order::where('status', 'paid')->sum('total');
        $totalFees      = PhotographerPayout::sum('platform_fee');
        $totalPayouts   = PhotographerPayout::where('status', 'paid')->sum('payout_amount');
        $pendingPayouts = PhotographerPayout::where('status', 'pending')->count();

        return view('admin.finance.index', compact('totalRevenue', 'totalFees', 'totalPayouts', 'pendingPayouts'));
    }

    public function transactions()
    {
        $transactions = PaymentTransaction::with(['order', 'user'])->orderByDesc('created_at')->paginate(20);
        return view('admin.finance.transactions', compact('transactions'));
    }

    // ─── Finance Reports ────────────────────────────────────────────────────────

    public function reports(Request $request)
    {
        $from   = $request->input('from', now()->startOfMonth()->toDateString());
        $to     = $request->input('to', now()->toDateString());
        $period = $request->input('period', 'monthly'); // daily / weekly / monthly

        // Revenue by period — Postgres to_char() patterns.
        // ISO week (IYYY/IW) for "weekly" so weeks 53/01 cross years correctly.
        $dateFormat = match ($period) {
            'daily'  => 'YYYY-MM-DD',
            'weekly' => 'IYYY"-W"IW',
            default  => 'YYYY-MM',
        };

        $revenueByPeriod = Order::where('status', 'paid')
            ->whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->selectRaw('to_char(created_at, ?) as period_label', [$dateFormat])
            ->selectRaw('SUM(total) as revenue')
            ->selectRaw('COUNT(*) as orders_count')
            ->groupBy('period_label')
            ->orderBy('period_label')
            ->get();

        // Revenue by payment gateway
        $revenueByMethod = PaymentTransaction::where('payment_transactions.status', 'completed')
            ->whereBetween('payment_transactions.created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->select('payment_transactions.payment_gateway', DB::raw('SUM(payment_transactions.amount) as revenue'), DB::raw('COUNT(*) as orders_count'))
            ->groupBy('payment_transactions.payment_gateway')
            ->orderByDesc('revenue')
            ->get();

        // Top 10 events by revenue
        $topEvents = Order::where('orders.status', 'paid')
            ->whereBetween('orders.created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->join('event_events', 'orders.event_id', '=', 'event_events.id')
            ->select(
                'event_events.id as event_id',
                'event_events.name as event_name',
                DB::raw('SUM(orders.total) as revenue'),
                DB::raw('COUNT(orders.id) as orders_count')
            )
            ->groupBy('event_events.id', 'event_events.name')
            ->orderByDesc('revenue')
            ->limit(10)
            ->get();

        // Photographer earnings
        $photographerEarnings = PhotographerPayout::join('auth_users', 'photographer_payouts.photographer_id', '=', 'auth_users.id')
            ->whereBetween('photographer_payouts.created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->select(
                'photographer_payouts.photographer_id',
                // Postgres uses || for string concatenation.
                DB::raw("(auth_users.first_name || ' ' || auth_users.last_name) as photographer_name"),
                DB::raw('SUM(photographer_payouts.gross_amount) as total_sales'),
                DB::raw('SUM(photographer_payouts.platform_fee) as total_commission'),
                DB::raw('SUM(photographer_payouts.payout_amount) as net_earnings'),
                DB::raw('COUNT(photographer_payouts.id) as payout_count')
            )
            ->groupBy('photographer_payouts.photographer_id', 'auth_users.first_name', 'auth_users.last_name')
            ->orderByDesc('total_sales')
            ->get();

        // Summary stats
        $totalRevenue    = Order::where('status', 'paid')
            ->whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->sum('total');
        $totalOrders     = Order::where('status', 'paid')
            ->whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->count();
        $avgOrderValue   = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0;
        $totalCommission = PhotographerPayout::whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->sum('platform_fee');

        // Payout summary
        $payoutsPaid    = PhotographerPayout::where('status', 'paid')
            ->whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->sum('payout_amount');
        $payoutsPending = PhotographerPayout::where('status', 'pending')
            ->whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->sum('payout_amount');

        // Max revenue for bar chart scaling
        $maxRevenue = $revenueByPeriod->max('revenue') ?: 1;

        return view('admin.finance.reports', compact(
            'from', 'to', 'period',
            'revenueByPeriod', 'revenueByMethod', 'topEvents', 'photographerEarnings',
            'totalRevenue', 'totalOrders', 'avgOrderValue', 'totalCommission',
            'payoutsPaid', 'payoutsPending', 'maxRevenue'
        ));
    }

    // ─── Refunds ────────────────────────────────────────────────────────────────

    public function refunds(Request $request)
    {
        $status = $request->input('status', '');

        $query = PaymentRefund::with(['order', 'user'])->orderByDesc('created_at');
        if ($status !== '') {
            $query->where('status', $status);
        }
        $refunds = $query->paginate(25)->withQueryString();

        // Stats
        $stats = [
            'total'           => PaymentRefund::count(),
            'pending'         => PaymentRefund::where('status', 'pending')->count(),
            'approved'        => PaymentRefund::whereIn('status', ['approved', 'completed'])->count(),
            'total_amount'    => PaymentRefund::whereIn('status', ['approved', 'completed'])->sum('amount'),
        ];

        return view('admin.finance.refunds', compact('refunds', 'stats', 'status'));
    }

    public function processRefund(Request $request, $id)
    {
        $request->validate([
            'action' => 'required|in:approve,reject',
            'note'   => 'nullable|string|max:500',
        ]);

        $refund = PaymentRefund::findOrFail($id);
        $admin  = Auth::guard('admin')->user();

        $oldStatus = $refund->status;
        $orderOldStatus = null;

        if ($request->action === 'approve') {
            $refund->status       = 'approved';
            $refund->approved_at  = now();
            $refund->approved_by  = $admin ? $admin->id : null;
            $refund->note         = $request->note;

            // If full refund, update order status + reverse payouts
            $order = $refund->order;
            if ($order && (float) $refund->amount >= (float) $order->total) {
                $orderOldStatus = $order->status;
                $order->status = 'refunded';
                $order->save();

                // Reverse photographer_payouts so the disbursement engine
                // doesn't pay the photographer for a refunded order. Only
                // touch payouts that haven't been disbursed yet — once
                // money has actually moved (`disbursement_id` set), the
                // admin must claw it back manually.
                $this->reversePhotographerPayouts($order, $refund);
            }
        } else {
            $refund->status      = 'rejected';
            $refund->note        = $request->note;
            $refund->approved_by = $admin ? $admin->id : null;
        }

        $refund->processed_at = now();
        $refund->save();

        $order = $refund->order;
        ActivityLogger::admin(
            action: $request->action === 'approve' ? 'refund.approved' : 'refund.rejected',
            target: $refund,
            description: ($request->action === 'approve' ? 'อนุมัติ' : 'ปฏิเสธ') . "คำขอคืนเงิน refund #{$refund->id}",
            oldValues: [
                'status'       => $oldStatus,
                'order_status' => $orderOldStatus,
            ],
            newValues: [
                'status'          => $refund->status,
                'refund_id'       => (int) $refund->id,
                'order_id'        => (int) $refund->order_id,
                'amount'          => (float) $refund->amount,
                'gateway'         => $order->payment_gateway ?? null,
                'order_status'    => $order?->status,
                'note'            => $request->note,
            ],
        );

        return redirect()->route('admin.finance.refunds')
            ->with('success', $request->action === 'approve' ? 'อนุมัติคำขอคืนเงินแล้ว' : 'ปฏิเสธคำขอคืนเงินแล้ว');
    }

    /**
     * Mark all of an order's pending photographer payouts as 'reversed'
     * after a full refund is approved.
     *
     * Three buckets:
     *   1) status='pending' AND disbursement_id IS NULL
     *      → safe to reverse, photographer hasn't been paid yet
     *   2) disbursement_id IS NOT NULL (already settled)
     *      → DON'T touch — money has moved, log so admin can claw back
     *   3) Already 'reversed'
     *      → idempotent skip
     *
     * Also reverses the matching marketing referral reward (loyalty
     * points granted on order paid get debited back).
     */
    protected function reversePhotographerPayouts(Order $order, PaymentRefund $refund): void
    {
        try {
            $payouts = PhotographerPayout::where('order_id', $order->id)->get();

            $reversed = 0;
            $stuck    = 0;
            foreach ($payouts as $p) {
                if ($p->status === 'reversed') continue;

                if (!is_null($p->disbursement_id ?? null)) {
                    // Already paid out — admin needs to manually claw back.
                    $stuck++;
                    Log::warning('Refund: payout already disbursed, manual reversal required', [
                        'order_id' => $order->id, 'payout_id' => $p->id,
                        'photographer_id' => $p->photographer_id, 'amount' => $p->payout_amount,
                        'disbursement_id' => $p->disbursement_id,
                    ]);
                    continue;
                }

                $p->update([
                    'status' => 'reversed',
                    // Optional fields — only set if column exists
                    ...(($p->getConnection()->getSchemaBuilder()->hasColumn('photographer_payouts', 'reversed_at'))
                        ? ['reversed_at' => now()] : []),
                    ...(($p->getConnection()->getSchemaBuilder()->hasColumn('photographer_payouts', 'reversal_reason'))
                        ? ['reversal_reason' => "refund #{$refund->id}"] : []),
                ]);
                $reversed++;
            }

            // Also reverse the marketing referral reward, if any
            try {
                if (class_exists(\App\Services\Marketing\ReferralService::class)) {
                    app(\App\Services\Marketing\ReferralService::class)->reverseOnRefund($order->id);
                }
            } catch (\Throwable $e) {
                Log::warning('Refund: referral reverse failed', ['order_id' => $order->id, 'error' => $e->getMessage()]);
            }

            Log::info('Refund payout reversal', [
                'order_id' => $order->id, 'refund_id' => $refund->id,
                'reversed' => $reversed, 'already_disbursed' => $stuck,
            ]);
        } catch (\Throwable $e) {
            Log::error('Refund payout reversal failed', [
                'order_id' => $order->id, 'error' => $e->getMessage(),
            ]);
        }
    }

    public function createRefund(Request $request)
    {
        $request->validate([
            'order_id' => 'required|exists:orders,id',
            'amount'   => 'required|numeric|min:0.01',
            'reason'   => 'required|string|max:500',
        ]);

        $order = Order::findOrFail($request->order_id);
        $admin = Auth::guard('admin')->user();

        $refund = PaymentRefund::create([
            'order_id'     => $order->id,
            'user_id'      => $order->user_id,
            'amount'       => $request->amount,
            'reason'       => $request->reason,
            'status'       => 'pending',
            'requested_by' => $admin ? $admin->id : null,
        ]);

        ActivityLogger::admin(
            action: 'refund.created',
            target: $refund,
            description: "สร้างคำขอคืนเงินสำหรับคำสั่งซื้อ #{$order->order_number}",
            oldValues: null,
            newValues: [
                'refund_id' => (int) $refund->id,
                'order_id'  => (int) $order->id,
                'amount'    => (float) $request->amount,
                'gateway'   => $order->payment_gateway ?? null,
                'reason'    => $request->reason,
                'status'    => 'pending',
            ],
        );

        return redirect()->route('admin.finance.refunds')
            ->with('success', 'สร้างคำขอคืนเงินเรียบร้อยแล้ว');
    }

    // ─── Reconciliation ─────────────────────────────────────────────────────────

    public function reconciliation(Request $request)
    {
        $from = $request->input('from', now()->subDays(30)->toDateString());
        $to   = $request->input('to', now()->toDateString());

        $fromDt = $from . ' 00:00:00';
        $toDt   = $to   . ' 23:59:59';

        // 1. Orders marked paid but no completed transaction
        $paidOrdersNoTxn = Order::where('orders.status', 'paid')
            ->whereBetween('orders.created_at', [$fromDt, $toDt])
            ->leftJoin('payment_transactions', function ($join) {
                $join->on('payment_transactions.order_id', '=', 'orders.id')
                     ->where('payment_transactions.status', '=', 'completed');
            })
            ->whereNull('payment_transactions.id')
            ->select('orders.*')
            ->with('user')
            ->get()
            ->map(function ($o) {
                return [
                    'type'            => 'missing_transaction',
                    'type_label'      => 'ไม่มีธุรกรรม',
                    'order'           => $o,
                    'order_number'    => $o->order_number ?? '#' . $o->id,
                    'transaction_id'  => '-',
                    'expected_amount' => $o->total,
                    'actual_amount'   => 0,
                    'difference'      => $o->total,
                    'resolve_url'     => route('admin.orders.show', $o->id),
                ];
            });

        // 2. Completed transactions but order not paid
        $completedTxnOrderNotPaid = PaymentTransaction::where('payment_transactions.status', 'completed')
            ->whereBetween('payment_transactions.created_at', [$fromDt, $toDt])
            ->join('orders', 'payment_transactions.order_id', '=', 'orders.id')
            ->where('orders.status', '!=', 'paid')
            ->select('payment_transactions.*', 'orders.order_number', 'orders.total as order_total', 'orders.status as order_status')
            ->get()
            ->map(function ($t) {
                return [
                    'type'            => 'order_not_paid',
                    'type_label'      => 'คำสั่งซื้อยังไม่ชำระ',
                    'order'           => null,
                    'order_number'    => $t->order_number ?? '#' . $t->order_id,
                    'transaction_id'  => $t->transaction_id,
                    'expected_amount' => $t->order_total,
                    'actual_amount'   => $t->amount,
                    'difference'      => abs($t->order_total - $t->amount),
                    'resolve_url'     => route('admin.orders.show', $t->order_id),
                ];
            });

        // 3. Slip amount not matching order amount (>2% difference)
        $slipMismatches = PaymentSlip::where('payment_slips.verify_status', 'approved')
            ->whereBetween('payment_slips.verified_at', [$fromDt, $toDt])
            ->join('orders', 'payment_slips.order_id', '=', 'orders.id')
            ->select('payment_slips.*', 'orders.order_number', 'orders.total as order_total')
            ->get()
            ->filter(function ($slip) {
                $expected = (float) $slip->order_total;
                $actual   = (float) ($slip->transfer_amount ?? $slip->amount ?? 0);
                if ($expected <= 0) return false;
                return abs($expected - $actual) / $expected > 0.02;
            })
            ->map(function ($slip) {
                $expected = (float) $slip->order_total;
                $actual   = (float) ($slip->transfer_amount ?? $slip->amount ?? 0);
                return [
                    'type'            => 'amount_mismatch',
                    'type_label'      => 'ยอดไม่ตรง',
                    'order'           => null,
                    'order_number'    => $slip->order_number ?? '#' . $slip->order_id,
                    'transaction_id'  => 'slip#' . $slip->id,
                    'expected_amount' => $expected,
                    'actual_amount'   => $actual,
                    'difference'      => abs($expected - $actual),
                    'resolve_url'     => route('admin.payments.slips'),
                ];
            })->values();

        // 4. Orphaned transactions (no matching order)
        $orphanedTxns = PaymentTransaction::where('payment_transactions.status', 'completed')
            ->whereBetween('payment_transactions.created_at', [$fromDt, $toDt])
            ->leftJoin('orders', 'payment_transactions.order_id', '=', 'orders.id')
            ->whereNull('orders.id')
            ->select('payment_transactions.*')
            ->get()
            ->map(function ($t) {
                return [
                    'type'            => 'orphan',
                    'type_label'      => 'ไม่มีคำสั่งซื้อ',
                    'order'           => null,
                    'order_number'    => '-',
                    'transaction_id'  => $t->transaction_id,
                    'expected_amount' => 0,
                    'actual_amount'   => $t->amount,
                    'difference'      => $t->amount,
                    'resolve_url'     => route('admin.finance.transactions'),
                ];
            });

        $discrepancies = collect()
            ->concat($paidOrdersNoTxn)
            ->concat($completedTxnOrderNotPaid)
            ->concat($slipMismatches)
            ->concat($orphanedTxns);

        // Matched transactions (completed txn + paid order)
        $matched = PaymentTransaction::where('payment_transactions.status', 'completed')
            ->whereBetween('payment_transactions.created_at', [$fromDt, $toDt])
            ->join('orders', 'payment_transactions.order_id', '=', 'orders.id')
            ->where('orders.status', 'paid')
            ->select(
                'payment_transactions.*',
                'orders.order_number',
                'orders.status as order_status'
            )
            ->orderByDesc('payment_transactions.created_at')
            ->get();

        // Summary stats
        $totalMatched       = $matched->count();
        $totalDiscrepancies = $discrepancies->count();
        $unreconciledAmount = $discrepancies->sum('difference');
        $totalVerified      = PaymentSlip::where('verify_status', 'approved')
            ->whereBetween('verified_at', [$fromDt, $toDt])
            ->count();

        return view('admin.finance.reconciliation', compact(
            'from', 'to',
            'discrepancies', 'matched',
            'totalMatched', 'totalDiscrepancies', 'unreconciledAmount', 'totalVerified'
        ));
    }
}

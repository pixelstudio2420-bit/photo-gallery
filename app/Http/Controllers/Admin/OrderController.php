<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $query = Order::with('user')
            ->withCount('items')
            ->orderByDesc('created_at');

        if ($request->filled('q')) {
            $query->whereHas('user', function ($q) use ($request) {
                $q->where('first_name', 'ilike', "%{$request->q}%")
                  ->orWhere('last_name', 'ilike', "%{$request->q}%")
                  ->orWhere('email', 'ilike', "%{$request->q}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }

        $orders = $query->paginate(20)->withQueryString();

        return view('admin.orders.index', compact('orders'));
    }

    public function show(Order $order)
    {
        // Load every relation the show page renders so each section
        // doesn't re-query inside Blade. Each relation is independent
        // (no chain) so a single load() call expresses the whole graph.
        $order->load([
            'items',
            'user',
            'event',
            'package',
            'creditPackage',
            'transactions',
            'slips',
            'downloadTokens',
            'payout',
            'refund',
            'subscriptionInvoice',
            'coupon',
        ]);

        // Add-on purchase + photographer profile aren't standard
        // relations on Order, so look them up directly. Both are
        // best-effort — orders that aren't add-ons / aren't tied to a
        // photographer simply leave the variables null.
        $addonPurchase = null;
        if ($order->order_type === \App\Models\Order::TYPE_ADDON && $order->addon_purchase_id) {
            $addonPurchase = \Illuminate\Support\Facades\DB::table('photographer_addon_purchases')
                ->where('id', $order->addon_purchase_id)
                ->first();
        }

        // Audit log entries for this order — show the lifecycle in the
        // timeline section. Filter to the actions admins actually want
        // to see (slip approve/reject, status changes, delivery events).
        $timeline = \Illuminate\Support\Facades\DB::table('payment_audit_log')
            ->where('order_id', $order->id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        // Activity log entries (admin-side actions). Pre-Laravel
        // ActivityLogger reads. Filter to order-related actions.
        $activity = \Illuminate\Support\Facades\DB::table('activity_logs')
            ->where(function ($q) use ($order) {
                $q->where('target_type', 'App\\Models\\Order')
                  ->where('target_id', $order->id);
            })
            ->orderByDesc('created_at')
            ->limit(30)
            ->get();

        return view('admin.orders.show', compact(
            'order', 'addonPurchase', 'timeline', 'activity',
        ));
    }

    public function update(Request $request, Order $order)
    {
        $request->validate([
            'status' => 'required|in:paid,completed,cancelled',
        ]);

        $oldStatus = $order->status;
        $newStatus = $request->status;

        $order->update(['status' => $newStatus]);

        $label = match($newStatus) {
            'paid' => 'ชำระเงินแล้ว',
            'completed' => 'เสร็จสิ้น',
            'cancelled' => 'ยกเลิกแล้ว',
            default => $newStatus,
        };

        if ($oldStatus !== $newStatus) {
            ActivityLogger::admin(
                action: 'order.status_updated',
                target: $order,
                description: "เปลี่ยนสถานะคำสั่งซื้อ #{$order->order_number} จาก {$oldStatus} เป็น {$newStatus}",
                oldValues: ['status' => $oldStatus],
                newValues: [
                    'status'       => $newStatus,
                    'order_id'     => (int) $order->id,
                    'order_number' => $order->order_number,
                    'total'        => (float) $order->total,
                ],
            );
        }

        return back()->with('success', "อัปเดตสถานะคำสั่งซื้อ #{$order->id} เป็น {$label} แล้ว");
    }

    public function export()
    {
        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="orders-' . date('Y-m-d') . '.csv"',
        ];

        return new StreamedResponse(function () {
            $handle = fopen('php://output', 'w');

            // BOM for Excel UTF-8 compatibility
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            // Header row
            fputcsv($handle, ['ID', 'Order Number', 'User Email', 'Total', 'Status', 'Created At']);

            Order::with('user')->orderByDesc('created_at')->chunk(500, function ($orders) use ($handle) {
                foreach ($orders as $order) {
                    fputcsv($handle, [
                        $order->id,
                        $order->order_number,
                        $order->user->email ?? '',
                        $order->total,
                        $order->status,
                        $order->created_at?->format('Y-m-d H:i:s'),
                    ]);
                }
            });

            fclose($handle);
        }, 200, $headers);
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Pagination\LengthAwarePaginator;

class DigitalOrderController extends Controller
{
    /**
     * List all digital orders with stats and filters.
     */
    public function index(Request $request)
    {
        $status = $request->input('status', '');
        $search = trim($request->input('search', ''));

        try {
            $query = DB::table('digital_orders as do')
                ->join('digital_products as dp', 'dp.id', '=', 'do.product_id')
                ->join('auth_users as u', 'u.id', '=', 'do.user_id')
                ->select([
                    'do.id',
                    'do.order_number',
                    'do.amount',
                    'do.payment_method',
                    'do.slip_image',
                    'do.status',
                    'do.paid_at',
                    'do.note',
                    'do.created_at',
                    'do.product_id',
                    'do.user_id',
                    'do.download_token',
                    'do.downloads_remaining',
                    'do.expires_at',
                    'dp.name as product_name',
                    'dp.cover_image as product_cover',
                    'dp.file_source',
                    'u.first_name',
                    'u.last_name',
                    'u.email',
                    DB::raw("COALESCE(NULLIF(TRIM(CONCAT_WS(' ', u.first_name, u.last_name)), ''), u.email) as display_name"),
                ]);

            if ($status !== '') {
                $query->where('do.status', $status);
            }

            if ($search !== '') {
                $query->where(function ($q) use ($search) {
                    $q->where('do.order_number', 'ilike', "%{$search}%")
                      ->orWhere('u.email', 'ilike', "%{$search}%")
                      ->orWhere('dp.name', 'ilike', "%{$search}%");
                });
            }

            $orders = $query->orderByDesc('do.created_at')->paginate(25);

            // Stats (count any pending review/payment as "pending")
            // Postgres requires single quotes for string literals; double
            // quotes mean identifier (column/table name).
            $stats = DB::table('digital_orders')->selectRaw(
                "COUNT(*)                                                                          as total,
                 COUNT(*) FILTER (WHERE status IN ('pending_review','pending_payment'))            as pending_count,
                 COUNT(*) FILTER (WHERE status = 'paid')                                           as paid_count,
                 COALESCE(SUM(amount) FILTER (WHERE status = 'paid'), 0)                           as total_revenue"
            )->first();
        } catch (\Throwable $e) {
            $orders = new LengthAwarePaginator([], 0, 25);
            $stats = (object)[
                'total' => 0,
                'pending_count' => 0,
                'paid_count' => 0,
                'total_revenue' => 0,
            ];
        }

        return view('admin.digital-orders.index', compact('orders', 'stats', 'status', 'search'));
    }

    /**
     * Approve a digital order: mark as paid, create download token, update product stats.
     *
     * Implementation delegates to DigitalOrderApprovalService so the same
     * approval logic is shared with the SlipOK auto-approve path in
     * Public\ProductController::uploadSlip. Single source of truth for
     * "this order is now paid".
     */
    public function approve(Request $request, int $id)
    {
        $orderRow = DB::table('digital_orders')->where('id', $id)
            ->select('id', 'order_number', 'status')->first();
        if (!$orderRow) {
            return back()->with('error', 'ไม่พบคำสั่งซื้อ');
        }
        if (!in_array($orderRow->status, ['pending_review', 'pending_payment'], true)) {
            return back()->with('error', 'คำสั่งซื้อนี้ไม่สามารถอนุมัติได้ (สถานะปัจจุบัน: ' . $orderRow->status . ')');
        }

        $ok = app(\App\Services\DigitalOrderApprovalService::class)
            ->approve($id, \Illuminate\Support\Facades\Auth::guard('admin')->id(), 'admin_manual');

        if (!$ok) {
            return back()->with('error', 'เกิดข้อผิดพลาดในการอนุมัติคำสั่งซื้อ');
        }

        // Auto-dismiss admin notifications for this order (bell count in realtime)
        $this->dismissAdminNotifs(['digital_order', 'digital_slip'], (string) $id);

        return back()->with('success', 'อนุมัติคำสั่งซื้อ #' . $orderRow->order_number . ' สำเร็จ — ลิงก์ดาวน์โหลดถูกสร้างและส่งให้ลูกค้าแล้ว');
    }

    /**
     * Reject a digital order with a reason.
     */
    public function reject(Request $request, int $id)
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ], [
            'reason.required' => 'กรุณาระบุเหตุผลในการปฏิเสธ',
            'reason.max'      => 'เหตุผลต้องไม่เกิน 500 ตัวอักษร',
        ]);

        try {
            $order = DB::table('digital_orders as do')
                ->join('digital_products as dp', 'dp.id', '=', 'do.product_id')
                ->select([
                    'do.id',
                    'do.order_number',
                    'do.user_id',
                    'do.status',
                    'dp.name as product_name',
                ])
                ->where('do.id', $id)
                ->first();

            if (!$order) {
                return back()->with('error', 'ไม่พบคำสั่งซื้อ');
            }

            if (!in_array($order->status, ['pending_review', 'pending_payment'])) {
                return back()->with('error', 'คำสั่งซื้อนี้ไม่สามารถปฏิเสธได้ (สถานะปัจจุบัน: ' . $order->status . ')');
            }

            DB::table('digital_orders')->where('id', $id)->update([
                'status'     => 'cancelled',
                'note'       => $validated['reason'],
                'updated_at' => now(),
            ]);

            // Create notification for user
            $this->createNotification(
                $order->user_id,
                'order_rejected',
                'คำสั่งซื้อถูกปฏิเสธ',
                'คำสั่งซื้อ #' . $order->order_number . ' (' . $order->product_name . ') ถูกปฏิเสธ เหตุผล: ' . $validated['reason']
            );

            // Auto-dismiss admin notifications for this order
            $this->dismissAdminNotifs(['digital_order', 'digital_slip'], (string) $order->id);

            return back()->with('success', 'ปฏิเสธคำสั่งซื้อ #' . $order->order_number . ' เรียบร้อย');
        } catch (\Throwable $e) {
            \Log::error('DigitalOrderController@reject error: ' . $e->getMessage());
            return back()->with('error', 'เกิดข้อผิดพลาด: ' . $e->getMessage());
        }
    }

    /**
     * Mark admin notifications for a given resource as read.
     * Used to realtime-dismiss the bell count after actions.
     */
    private function dismissAdminNotifs(array $types, string $refId): void
    {
        try {
            if (!Schema::hasTable('admin_notifications')) return;
            DB::table('admin_notifications')
                ->whereIn('type', $types)
                ->where('ref_id', $refId)
                ->where('is_read', false)
                ->update(['is_read' => true, 'read_at' => now()]);
        } catch (\Throwable $e) {
            \Log::warning('dismissAdminNotifs failed: ' . $e->getMessage());
        }
    }

    /**
     * Insert a notification row into the notifications table.
     */
    private function createNotification(int $userId, string $type, string $title, string $message): void
    {
        $table = Schema::hasTable('notifications') ? 'notifications' : 'user_notifications';

        $data = [
            'user_id'    => $userId,
            'type'       => $type,
            'title'      => $title,
            'message'    => $message,
            'is_read'    => 0,
            'created_at' => now(),
        ];

        // user_notifications has updated_at but notifications may not
        if ($table === 'user_notifications') {
            $data['updated_at'] = now();
        }

        try {
            DB::table($table)->insert($data);
        } catch (\Throwable $e) {
            \Log::warning('DigitalOrderController: could not insert notification — ' . $e->getMessage());
        }
    }
}

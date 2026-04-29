<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RefundRequest;
use App\Services\RefundService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RefundRequestController extends Controller
{
    public function __construct(private RefundService $refunds)
    {
    }

    public function index(Request $request)
    {
        $query = RefundRequest::with('user', 'order');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        } else {
            // Default: show pending + under_review
            $query->whereIn('status', ['pending', 'under_review']);
        }

        if ($request->filled('q')) {
            $s = $request->q;
            $query->where(function ($q) use ($s) {
                $q->where('request_number', 'ilike', "%{$s}%")
                  ->orWhereHas('user', fn($u) => $u->where('first_name', 'ilike', "%{$s}%")->orWhere('email', 'ilike', "%{$s}%"))
                  ->orWhereHas('order', fn($o) => $o->where('order_number', 'ilike', "%{$s}%"));
            });
        }

        $refunds = $query->orderByDesc('created_at')->paginate(20)->withQueryString();

        $stats = [
            'total'       => RefundRequest::count(),
            'pending'     => RefundRequest::where('status', 'pending')->count(),
            'under_review'=> RefundRequest::where('status', 'under_review')->count(),
            'approved'    => RefundRequest::where('status', 'approved')->count(),
            'completed'   => RefundRequest::where('status', 'completed')->count(),
            'rejected'    => RefundRequest::where('status', 'rejected')->count(),
            'total_requested' => (float) RefundRequest::sum('requested_amount'),
            'total_approved'  => (float) RefundRequest::where('status', 'completed')->sum('approved_amount'),
        ];

        return view('admin.refunds.index', compact('refunds', 'stats'));
    }

    public function show(RefundRequest $refund)
    {
        $refund->load('user', 'order.items', 'reviewedByAdmin');
        return view('admin.refunds.show', compact('refund'));
    }

    public function approve(Request $request, RefundRequest $refund)
    {
        $request->validate([
            'approved_amount' => 'required|numeric|min:0|max:' . $refund->requested_amount,
            'admin_note'      => 'nullable|string|max:1000',
        ]);

        try {
            $this->refunds->approve(
                $refund,
                (float) $request->approved_amount,
                Auth::guard('admin')->id(),
                $request->admin_note ?? ''
            );
            return back()->with('success', 'อนุมัติคำขอเรียบร้อย');
        } catch (\Throwable $e) {
            return back()->with('error', 'เกิดข้อผิดพลาด: ' . $e->getMessage());
        }
    }

    public function reject(Request $request, RefundRequest $refund)
    {
        $request->validate(['reason' => 'required|string|max:500']);

        $this->refunds->reject($refund, Auth::guard('admin')->id(), $request->reason);

        return back()->with('success', 'ปฏิเสธคำขอเรียบร้อย');
    }

    public function markReview(RefundRequest $refund)
    {
        if ($refund->status === 'pending') {
            $refund->update([
                'status'                => 'under_review',
                'reviewed_by_admin_id'  => Auth::guard('admin')->id(),
                'reviewed_at'           => now(),
            ]);
        }
        return back()->with('success', 'เริ่มพิจารณาแล้ว');
    }
}

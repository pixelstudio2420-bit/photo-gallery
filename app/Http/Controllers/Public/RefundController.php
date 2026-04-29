<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\RefundRequest;
use App\Services\RefundService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RefundController extends Controller
{
    public function __construct(private RefundService $refunds)
    {
    }

    /**
     * List user's refund requests.
     */
    public function index(Request $request)
    {
        $requests = RefundRequest::with('order')
            ->where('user_id', Auth::id())
            ->orderByDesc('created_at')
            ->paginate(10);

        return view('public.refunds.index', compact('requests'));
    }

    /**
     * Show refund request form for a specific order.
     */
    public function create(Order $order)
    {
        abort_unless($order->user_id === Auth::id(), 403);

        $eligibility = $this->refunds->canRequestRefund($order);

        if (!$eligibility['allowed']) {
            return redirect()
                ->route('orders.show', $order->id)
                ->with('error', $eligibility['reason']);
        }

        return view('public.refunds.create', compact('order'));
    }

    /**
     * Submit refund request.
     */
    public function store(Request $request, Order $order)
    {
        abort_unless($order->user_id === Auth::id(), 403);

        $validated = $request->validate([
            'requested_amount' => 'required|numeric|min:1',
            'reason'           => 'required|in:wrong_order,duplicate_charge,poor_quality,not_as_described,never_received,other',
            'description'      => 'required|string|min:10|max:2000',
        ]);

        // Cap amount at order total
        $validated['requested_amount'] = min((float) $validated['requested_amount'], (float) $order->total);

        try {
            $refundRequest = $this->refunds->createRequest($order, $validated);
        } catch (\Throwable $e) {
            return back()->with('error', 'ไม่สามารถส่งคำขอได้: ' . $e->getMessage())->withInput();
        }

        return redirect()
            ->route('refunds.show', $refundRequest->id)
            ->with('success', "ส่งคำขอคืนเงินเรียบร้อย หมายเลข {$refundRequest->request_number}");
    }

    /**
     * Show single refund request (threaded view).
     */
    public function show(RefundRequest $refundRequest)
    {
        abort_unless($refundRequest->user_id === Auth::id(), 403);

        $refundRequest->load('order.items', 'reviewedByAdmin');

        return view('public.refunds.show', compact('refundRequest'));
    }

    /**
     * Cancel a pending refund request.
     */
    public function cancel(RefundRequest $refundRequest)
    {
        abort_unless($refundRequest->user_id === Auth::id(), 403);

        if (!$refundRequest->canBeCancelledByUser()) {
            return back()->with('error', 'ไม่สามารถยกเลิกคำขอในสถานะนี้ได้');
        }

        $this->refunds->cancel($refundRequest);

        return back()->with('success', 'ยกเลิกคำขอเรียบร้อย');
    }
}

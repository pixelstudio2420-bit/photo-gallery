<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\BookingService;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Admin oversight of bookings.
 *
 * Surface:
 *   GET  /admin/bookings              — list with filters + metrics
 *   GET  /admin/bookings/{id}         — single booking detail
 *   POST /admin/bookings/{id}/cancel  — admin cancellation (with reason)
 *   POST /admin/bookings/{id}/note    — admin notes (private)
 *   POST /admin/bookings/{id}/no-show — mark as no-show (audit)
 */
class BookingController extends Controller
{
    public function __construct(private BookingService $service) {}

    public function index(Request $request)
    {
        $q = Booking::query()->with(['customer', 'photographer', 'photographerProfile']);

        // ── Filters ────────────────────────────────────────────────────
        if ($status = $request->input('status')) {
            $q->where('status', $status);
        }
        if ($search = $request->input('q')) {
            $q->where(function ($qq) use ($search) {
                $qq->where('title', 'ilike', "%{$search}%")
                   ->orWhere('location', 'ilike', "%{$search}%")
                   ->orWhereHas('customer', fn ($c) => $c->where('email', 'ilike', "%{$search}%")->orWhere('first_name', 'ilike', "%{$search}%"));
            });
        }
        if ($from = $request->input('from')) {
            $q->where('scheduled_at', '>=', Carbon::parse($from));
        }
        if ($to = $request->input('to')) {
            $q->where('scheduled_at', '<=', Carbon::parse($to)->endOfDay());
        }

        $bookings = $q->orderByDesc('scheduled_at')->paginate(30)->withQueryString();

        // ── Metrics (across ALL bookings, not paginated subset) ────────
        $stats = $this->buildStats();

        return view('admin.bookings.index', [
            'bookings' => $bookings,
            'stats'    => $stats,
            'filters'  => $request->only(['status', 'q', 'from', 'to']),
        ]);
    }

    public function show(Booking $booking)
    {
        $booking->load(['customer', 'photographer', 'photographerProfile', 'event']);
        return view('admin.bookings.show', compact('booking'));
    }

    public function cancel(Request $request, Booking $booking)
    {
        $valid = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);
        try {
            $this->service->cancel($booking, Booking::CANCELLED_BY_ADMIN, $valid['reason']);
            $booking->update(['admin_id' => auth('admin')->id()]);
            return back()->with('success', 'ยกเลิก booking #' . $booking->id . ' (admin override)');
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function markNoShow(Request $request, Booking $booking)
    {
        $valid = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);
        try {
            // Route through the service so the photographer is notified,
            // GCal event is removed, sheet row is updated. Direct model
            // update was bypassing all of that.
            app(\App\Services\BookingService::class)->markNoShow(
                $booking,
                (int) auth('admin')->id() ?: null,
                $valid['reason'] ?? null,
            );
            return back()->with('success', 'Mark booking #' . $booking->id . ' เป็น no-show แล้ว');
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function updateNote(Request $request, Booking $booking)
    {
        $valid = $request->validate([
            'admin_notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $booking->update([
            'admin_notes' => $valid['admin_notes'],
            'admin_id'    => auth('admin')->id(),
        ]);
        return back()->with('success', 'บันทึก admin note แล้ว');
    }

    /**
     * Build the metrics shown on the dashboard cards. Cached in-request.
     */
    private function buildStats(): array
    {
        $thisMonth = now()->startOfMonth();
        $totalConfirmed = Booking::where('status', Booking::STATUS_CONFIRMED)->count();
        $totalCompleted = Booking::where('status', Booking::STATUS_COMPLETED)->count();
        $totalCancelled = Booking::where('status', Booking::STATUS_CANCELLED)->count();
        $totalNoShow    = Booking::where('status', Booking::STATUS_NO_SHOW)->count();
        $totalAll       = $totalConfirmed + $totalCompleted + $totalCancelled + $totalNoShow + Booking::where('status', Booking::STATUS_PENDING)->count();

        return [
            'total'            => $totalAll,
            'pending'          => Booking::where('status', Booking::STATUS_PENDING)->count(),
            'confirmed'        => $totalConfirmed,
            'completed'        => $totalCompleted,
            'cancelled'        => $totalCancelled,
            'no_show'          => $totalNoShow,
            'this_month'       => Booking::where('scheduled_at', '>=', $thisMonth)->count(),
            'completion_rate'  => $totalAll > 0 ? round(($totalCompleted / $totalAll) * 100, 1) : 0,
            'no_show_rate'     => ($totalCompleted + $totalNoShow) > 0
                ? round(($totalNoShow / ($totalCompleted + $totalNoShow)) * 100, 1)
                : 0,
            'revenue_completed'=> Booking::where('status', Booking::STATUS_COMPLETED)->sum('agreed_price'),
            'revenue_deposit'  => Booking::sum('deposit_paid'),
        ];
    }
}

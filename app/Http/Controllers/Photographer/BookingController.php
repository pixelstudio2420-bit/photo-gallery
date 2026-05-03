<?php

namespace App\Http\Controllers\Photographer;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\BookingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Photographer-side booking management.
 *
 * Routes (all under /photographer/bookings, photographer-auth required):
 *   GET  /            — calendar + list view
 *   GET  /api         — JSON feed for FullCalendar (events between $start..$end)
 *   GET  /{id}        — single booking detail
 *   POST /{id}/confirm — accept the booking (status pending → confirmed)
 *   POST /{id}/cancel  — cancel (any status; provides reason)
 *   POST /{id}/complete — mark shoot finished (status confirmed → completed)
 *   POST /{id}/notes   — quick-update photographer notes
 */
class BookingController extends Controller
{
    public function __construct(private BookingService $service) {}

    public function index(Request $request)
    {
        $photographerId = Auth::id();
        $search   = trim((string) $request->query('q', ''));
        $statusFilter = (string) $request->query('status', 'all');

        // ── Today's bookings (high-priority surface) ─────────────
        // Photographers want to see their TODAY shoots first thing
        // when opening the page. Filtered to confirmed only —
        // pending shoots in the past are an admin-attention case.
        $today = Booking::forPhotographer($photographerId)
            ->whereDate('scheduled_at', now()->toDateString())
            ->whereNotIn('status', [Booking::STATUS_CANCELLED, Booking::STATUS_NO_SHOW])
            ->with('customer')
            ->orderBy('scheduled_at')
            ->get();

        // ── Pending: needs confirm/cancel decision ────────────────
        $pending = Booking::forPhotographer($photographerId)
            ->where('status', Booking::STATUS_PENDING)
            ->when($search !== '', fn ($q) => $q->where(function ($w) use ($search) {
                $w->where('title', 'ilike', "%{$search}%")
                  ->orWhere('customer_phone', 'ilike', "%{$search}%")
                  ->orWhere('location', 'ilike', "%{$search}%");
            }))
            ->with('customer')
            ->orderBy('scheduled_at')
            ->get();

        // ── Upcoming list — applies status filter + search ───────
        $upcomingQuery = Booking::forPhotographer($photographerId)
            ->upcoming()
            ->with('customer');

        if ($statusFilter !== 'all' && in_array($statusFilter, [
            Booking::STATUS_PENDING, Booking::STATUS_CONFIRMED,
            Booking::STATUS_COMPLETED, Booking::STATUS_CANCELLED,
        ], true)) {
            $upcomingQuery->where('status', $statusFilter);
        }
        if ($search !== '') {
            $upcomingQuery->where(function ($q) use ($search) {
                $q->where('title', 'ilike', "%{$search}%")
                  ->orWhere('customer_phone', 'ilike', "%{$search}%")
                  ->orWhere('location', 'ilike', "%{$search}%");
            });
        }
        $upcoming = $upcomingQuery->orderBy('scheduled_at')->limit(30)->get();

        // ── Aggregate stats — single round-trip per metric ───────
        $base = Booking::forPhotographer($photographerId);

        // Revenue from confirmed/completed bookings this month —
        // gives photographer a sense of incoming money. Nullable
        // agreed_price coerced to 0.
        $revenueThisMonth = (float) Booking::forPhotographer($photographerId)
            ->whereIn('status', [Booking::STATUS_CONFIRMED, Booking::STATUS_COMPLETED])
            ->whereMonth('scheduled_at', now()->month)
            ->whereYear('scheduled_at', now()->year)
            ->sum('agreed_price');

        return view('photographer.bookings.index', [
            'today'    => $today,
            'upcoming' => $upcoming,
            'pending'  => $pending,
            'search'   => $search,
            'statusFilter' => $statusFilter,
            'stats'    => [
                'total'     => (clone $base)->count(),
                'upcoming'  => (clone $base)->upcoming()->count(),
                'pending'   => (clone $base)->where('status', Booking::STATUS_PENDING)->count(),
                'this_month'=> (clone $base)
                    ->whereMonth('scheduled_at', now()->month)
                    ->whereYear('scheduled_at', now()->year)
                    ->count(),
                'today_count' => $today->count(),
                'revenue_this_month' => $revenueThisMonth,
            ],
        ]);
    }

    /**
     * JSON feed for FullCalendar. Returns bookings whose `scheduled_at` falls
     * within the [start..end] range (FullCalendar passes these on each view
     * change). Output schema matches FullCalendar's event-input contract.
     */
    public function calendarFeed(Request $request)
    {
        $start = Carbon::parse($request->input('start', now()->subMonth()));
        $end   = Carbon::parse($request->input('end',   now()->addMonths(2)));

        $bookings = Booking::forPhotographer(Auth::id())
            ->whereBetween('scheduled_at', [$start, $end])
            ->with('customer')
            ->get()
            ->map(fn ($b) => [
                'id'              => $b->id,
                'title'           => $b->title,
                'start'           => $b->scheduled_at?->toIso8601String(),
                'end'             => $b->ends_at?->toIso8601String(),
                'color'           => $b->color,
                'extendedProps'   => [
                    'status'        => $b->status,
                    'status_label'  => $b->status_label,
                    'customer_name' => $b->customer?->first_name ?? '?',
                    'customer_phone'=> $b->customer_phone,
                    'location'      => $b->location,
                    'price'         => $b->agreed_price,
                ],
                'url'             => route('photographer.bookings.show', $b->id),
            ]);

        return response()->json($bookings);
    }

    public function show(Booking $booking)
    {
        $this->authorize($booking);
        $booking->load(['customer', 'event']);
        return view('photographer.bookings.show', compact('booking'));
    }

    public function confirm(Booking $booking)
    {
        $this->authorize($booking);
        try {
            $this->service->confirm($booking);
            return back()->with('success', 'ยืนยันคิวงาน #' . $booking->id . ' แล้ว — ลูกค้าได้รับ LINE notification');
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function cancel(Request $request, Booking $booking)
    {
        $this->authorize($booking);
        $valid = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);
        try {
            $this->service->cancel($booking, Booking::CANCELLED_BY_PHOTOGRAPHER, $valid['reason']);
            return redirect()->route('photographer.bookings')
                ->with('success', 'ยกเลิกคิวงาน #' . $booking->id . ' — ลูกค้าได้รับการแจ้งแล้ว');
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function complete(Booking $booking)
    {
        $this->authorize($booking);
        try {
            $this->service->complete($booking);
            return back()->with('success', 'mark งาน #' . $booking->id . ' เสร็จแล้ว — ลูกค้าจะได้รับการเชิญรีวิวพรุ่งนี้');
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function updateNotes(Request $request, Booking $booking)
    {
        $this->authorize($booking);
        $valid = $request->validate([
            'photographer_notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $booking->update($valid);
        return back()->with('success', 'บันทึกโน้ตเรียบร้อย');
    }

    /**
     * Guard against cross-photographer access.
     * Throws 403 if the booking belongs to someone else.
     */
    private function authorize(Booking $booking): void
    {
        if ($booking->photographer_id !== Auth::id()) {
            abort(403, 'Booking ไม่ใช่ของช่างภาพคุณ');
        }
    }
}

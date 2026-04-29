<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\PhotographerProfile;
use App\Services\BookingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Customer-side booking flow:
 *   GET  /photographers/{id}/book     — booking form (auth required)
 *   POST /photographers/{id}/book     — submit booking request
 *   GET  /profile/bookings            — customer's own bookings list
 *   GET  /profile/bookings/{id}       — single booking detail
 *   POST /profile/bookings/{id}/cancel — customer cancellation
 */
class BookingController extends Controller
{
    public function __construct(private BookingService $service) {}

    /**
     * Show the booking form for a given photographer profile.
     * Pre-fills any package/price hints from the photographer's profile.
     */
    public function create($photographerUserId)
    {
        if (!Auth::check()) {
            return redirect()->route('login')->with('error', 'กรุณา login ก่อนจองช่างภาพ');
        }

        $photographer = PhotographerProfile::where('user_id', $photographerUserId)
            ->where('status', 'approved')
            ->firstOrFail();

        return view('public.bookings.create', [
            'photographer' => $photographer,
            'minDate'      => now()->addDay()->format('Y-m-d\TH:i'),
            'defaultPrice' => null, // TODO: link to package pricing if exists
        ]);
    }

    public function store(Request $request, $photographerUserId)
    {
        if (!Auth::check()) abort(401);

        $photographer = PhotographerProfile::where('user_id', $photographerUserId)
            ->where('status', 'approved')
            ->firstOrFail();

        $valid = $request->validate([
            'title'             => ['required', 'string', 'max:255'],
            'description'       => ['nullable', 'string', 'max:2000'],
            'scheduled_at'      => ['required', 'date', 'after:+12 hours'],
            'duration_minutes'  => ['required', 'integer', 'between:30,1440'],
            'location'          => ['nullable', 'string', 'max:500'],
            'location_lat'      => ['nullable', 'numeric', 'between:-90,90'],
            'location_lng'      => ['nullable', 'numeric', 'between:-180,180'],
            'expected_photos'   => ['nullable', 'integer', 'between:1,10000'],
            'package_name'      => ['nullable', 'string', 'max:100'],
            'agreed_price'      => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'customer_phone'    => ['nullable', 'string', 'max:30'],
            'customer_notes'    => ['nullable', 'string', 'max:2000'],
        ]);

        $start = Carbon::parse($valid['scheduled_at']);
        $end   = $start->copy()->addMinutes((int) $valid['duration_minutes']);

        // Soft-check availability — photographer can still confirm & resolve
        // conflicts manually, but warn the customer up-front.
        if (!$this->service->isAvailable($photographer->user_id, $start, $end)) {
            return back()->withInput()
                ->with('warning', 'ช่างภาพมีงานในช่วงเวลานั้นแล้ว — คุณยังจองได้ แต่ช่างภาพอาจปฏิเสธ');
        }

        $booking = $this->service->create([
            'customer_user_id' => Auth::id(),
            'photographer_id'  => $photographer->user_id,
            'title'            => $valid['title'],
            'description'      => $valid['description'] ?? null,
            'scheduled_at'     => $start,
            'duration_minutes' => $valid['duration_minutes'],
            'location'         => $valid['location'] ?? null,
            'location_lat'     => $valid['location_lat'] ?? null,
            'location_lng'     => $valid['location_lng'] ?? null,
            'expected_photos'  => $valid['expected_photos'] ?? null,
            'package_name'     => $valid['package_name'] ?? null,
            'agreed_price'     => $valid['agreed_price'] ?? null,
            'customer_phone'   => $valid['customer_phone'] ?? Auth::user()?->phone,
            'customer_notes'   => $valid['customer_notes'] ?? null,
        ]);

        return redirect()->route('profile.bookings.show', $booking->id)
            ->with('success', 'จองคิวงานแล้ว — ระบบส่ง LINE หาช่างภาพให้ยืนยันใน 24 ชม.');
    }

    public function index()
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $bookings = Booking::forCustomer(Auth::id())
            ->with(['photographerProfile', 'photographer'])
            ->orderByDesc('scheduled_at')
            ->paginate(20);

        return view('public.bookings.index', compact('bookings'));
    }

    public function show(Booking $booking)
    {
        if ($booking->customer_user_id !== Auth::id()) {
            abort(403);
        }
        $booking->load(['photographerProfile', 'photographer']);
        return view('public.bookings.show', compact('booking'));
    }

    public function cancel(Request $request, Booking $booking)
    {
        if ($booking->customer_user_id !== Auth::id()) abort(403);

        $valid = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);
        try {
            $this->service->cancel($booking, Booking::CANCELLED_BY_CUSTOMER, $valid['reason']);
            return back()->with('success', 'ยกเลิกการจองแล้ว — ช่างภาพได้รับการแจ้งทาง LINE');
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}

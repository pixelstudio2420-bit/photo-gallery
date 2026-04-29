<?php
namespace App\Http\Controllers\Public;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PhotographerProfile;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ReviewController extends Controller
{
    public function index(Request $request)
    {
        $query = Review::with(['user', 'event', 'photographerProfile'])
            ->where('is_visible', 1)
            ->orderByDesc('created_at');

        // Filter by rating
        if ($request->filled('rating') && in_array($request->rating, [1, 2, 3, 4, 5])) {
            $query->where('rating', $request->rating);
        }

        // Filter by photographer
        if ($request->filled('photographer_id')) {
            $query->where('photographer_id', $request->photographer_id);
        }

        $reviews = $query->paginate(20)->withQueryString();

        // Stats
        $totalReviews = Review::where('is_visible', 1)->count();
        $avgRating    = Review::where('is_visible', 1)->avg('rating');

        // Rating distribution
        $ratingDistribution = Review::where('is_visible', 1)
            ->select('rating', DB::raw('count(*) as count'))
            ->groupBy('rating')
            ->pluck('count', 'rating')
            ->toArray();

        // Fill missing ratings with 0
        for ($i = 1; $i <= 5; $i++) {
            if (!isset($ratingDistribution[$i])) {
                $ratingDistribution[$i] = 0;
            }
        }
        krsort($ratingDistribution);

        // Photographers for filter dropdown (only those with visible reviews)
        $photographerIds = Review::where('is_visible', 1)
            ->pluck('photographer_id')
            ->unique();

        $photographers = PhotographerProfile::whereIn('user_id', $photographerIds)
            ->where('status', 'approved')
            ->orderBy('display_name')
            ->get();

        return view('public.reviews.index', compact(
            'reviews',
            'totalReviews',
            'avgRating',
            'ratingDistribution',
            'photographers'
        ));
    }

    public function create($order)
    {
        $order = Order::with('event')->findOrFail($order);

        // Verify order belongs to current user and is paid
        if ($order->user_id !== Auth::id()) {
            abort(403);
        }

        if (!$order->isPaid()) {
            return redirect()->route('orders.index')->with('error', 'ไม่สามารถรีวิวได้ เนื่องจากคำสั่งซื้อยังไม่ได้ชำระเงิน');
        }

        // Check no existing review for this order
        $existingReview = Review::where('order_id', $order->id)->where('user_id', Auth::id())->first();
        if ($existingReview) {
            return redirect()->route('orders.index')->with('error', 'คุณได้รีวิวคำสั่งซื้อนี้แล้ว');
        }

        return view('public.reviews.create', compact('order'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'order_id' => 'required|exists:orders,id',
            'rating'   => 'required|integer|min:1|max:5',
            'comment'  => 'nullable|string|max:2000',
        ]);

        $order = Order::with('event')->findOrFail($validated['order_id']);

        // Verify order belongs to user and is paid
        if ($order->user_id !== Auth::id()) {
            abort(403);
        }

        if (!$order->isPaid()) {
            return redirect()->route('orders.index')->with('error', 'ไม่สามารถรีวิวได้ เนื่องจากคำสั่งซื้อยังไม่ได้ชำระเงิน');
        }

        // Check no duplicate review
        $existingReview = Review::where('order_id', $order->id)->where('user_id', Auth::id())->first();
        if ($existingReview) {
            return redirect()->route('orders.index')->with('error', 'คุณได้รีวิวคำสั่งซื้อนี้แล้ว');
        }

        $review = Review::create([
            'user_id'         => Auth::id(),
            'photographer_id' => $order->event->photographer_id,
            'event_id'        => $order->event_id,
            'order_id'        => $order->id,
            'rating'          => $validated['rating'],
            'comment'         => $validated['comment'] ?? null,
            'is_visible'      => true,
        ]);

        // 1. Admin notification — fired automatically by
        // AdminNotificationObserver on Review::created. Direct call removed
        // to prevent duplicate bell-icon entries.

        // 2. Notify photographer (in-app + email)
        try {
            $photographer = \App\Models\PhotographerProfile::find($order->event->photographer_id);
            if ($photographer && $photographer->user_id) {
                // In-app
                \App\Models\UserNotification::newReview(
                    $photographer->user_id,
                    $validated['rating'],
                    $order->event->name ?? null
                );

                // Email
                $user = \App\Models\User::find($photographer->user_id);
                if ($user && $user->email) {
                    app(\App\Services\MailService::class)->photographerNewReview($user->email, $user->first_name, [
                        'rating'        => $validated['rating'],
                        'comment'       => $validated['comment'] ?? null,
                        'customer_name' => Auth::user()->first_name ?? 'ลูกค้า',
                        'event_name'    => $order->event->name ?? null,
                        'created_at'    => now()->format('d/m/Y H:i'),
                        'reply_url'     => url('/photographer/reviews'),
                    ]);
                }
            }
        } catch (\Throwable $e) {
            \Log::warning('Review photographer notification failed: ' . $e->getMessage());
        }

        return redirect()->route('orders.index')->with('success', 'ส่งรีวิวสำเร็จ');
    }

    /**
     * Toggle helpful vote for a review.
     */
    public function toggleHelpful(Request $request, Review $review)
    {
        if ($review->user_id === Auth::id()) {
            return response()->json(['success' => false, 'error' => 'ไม่สามารถโหวตรีวิวของตัวเองได้'], 422);
        }

        $added = $review->toggleHelpful(Auth::id());

        return response()->json([
            'success'       => true,
            'is_helpful'    => $added,
            'helpful_count' => $review->fresh()->helpful_count,
        ]);
    }

    /**
     * Report a review as inappropriate.
     */
    public function report(Request $request, Review $review)
    {
        $validated = $request->validate([
            'reason'      => 'required|in:spam,offensive,fake,irrelevant,private_info,other',
            'description' => 'nullable|string|max:500',
        ]);

        // Prevent duplicate report
        $existing = \App\Models\ReviewReport::where('review_id', $review->id)
            ->where('user_id', Auth::id())
            ->first();

        if ($existing) {
            return response()->json(['success' => false, 'error' => 'คุณได้รายงานรีวิวนี้แล้ว'], 422);
        }

        \App\Models\ReviewReport::create([
            'review_id'   => $review->id,
            'user_id'     => Auth::id(),
            'reason'      => $validated['reason'],
            'description' => $validated['description'] ?? null,
            'status'      => 'pending',
        ]);

        $review->increment('report_count');

        // Auto-flag if threshold reached
        if ($review->fresh()->report_count >= 3 && !$review->is_flagged) {
            $review->update(['is_flagged' => true, 'flag_reason' => 'ถูกรายงานหลายครั้ง']);
        }

        return response()->json([
            'success' => true,
            'message' => 'รายงานรีวิวเรียบร้อย ทีมงานจะตรวจสอบโดยเร็ว',
        ]);
    }
}

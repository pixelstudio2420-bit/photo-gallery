<?php

namespace App\Http\Controllers\Photographer;

use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReviewController extends Controller
{
    /**
     * List all reviews for the authenticated photographer.
     */
    public function index(Request $request)
    {
        $userId = Auth::id();

        $query = Review::with(['user', 'event'])
            ->where('photographer_id', $userId);

        // Filter by rating
        if ($request->filled('rating')) {
            $query->where('rating', (int) $request->rating);
        }

        // Filter by reply status
        if ($request->filled('reply')) {
            if ($request->reply === 'with_reply') {
                $query->whereNotNull('photographer_reply');
            } elseif ($request->reply === 'no_reply') {
                $query->whereNull('photographer_reply');
            }
        }

        $reviews = $query->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        // Stats
        $stats = Review::statsFor(Review::where('photographer_id', $userId)->where('status', 'approved'));
        $stats['no_reply_count'] = Review::where('photographer_id', $userId)
            ->whereNull('photographer_reply')
            ->count();

        return view('photographer.reviews.index', compact('reviews', 'stats'));
    }

    /**
     * Photographer replies to a review.
     */
    public function reply(Request $request, Review $review)
    {
        $request->validate(['reply' => 'required|string|max:2000']);

        // Verify ownership
        if ($review->photographer_id !== Auth::id()) {
            abort(403, 'ไม่มีสิทธิ์ตอบรีวิวนี้');
        }

        $review->update([
            'photographer_reply'    => $request->reply,
            'photographer_reply_at' => now(),
        ]);

        // Notify the reviewer
        try {
            \App\Models\UserNotification::notify(
                $review->user_id,
                'review',
                'ช่างภาพตอบรีวิวของคุณแล้ว',
                mb_substr($request->reply, 0, 150),
                'notifications'
            );
        } catch (\Throwable $e) {
            \Log::warning('Photographer reply notification failed: ' . $e->getMessage());
        }

        return back()->with('success', 'ตอบรีวิวเรียบร้อย ลูกค้าจะได้รับการแจ้งเตือน');
    }

    /**
     * Delete photographer's own reply.
     */
    public function deleteReply(Review $review)
    {
        if ($review->photographer_id !== Auth::id()) {
            abort(403);
        }

        $review->update([
            'photographer_reply'    => null,
            'photographer_reply_at' => null,
        ]);

        return back()->with('success', 'ลบคำตอบเรียบร้อย');
    }
}

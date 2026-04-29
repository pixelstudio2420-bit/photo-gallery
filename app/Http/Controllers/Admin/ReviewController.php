<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\ReviewReport;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReviewController extends Controller
{
    /**
     * Index with filters + stats.
     */
    public function index(Request $request)
    {
        $query = Review::with(['user', 'photographer', 'event']);

        if ($request->filled('q')) {
            $s = $request->q;
            $query->where(function ($q) use ($s) {
                $q->where('comment', 'ilike', "%{$s}%")
                  ->orWhereHas('user', fn($u) => $u->where('first_name', 'ilike', "%{$s}%")->orWhere('last_name', 'ilike', "%{$s}%"));
            });
        }

        if ($request->filled('rating')) {
            $query->where('rating', (int) $request->rating);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('visibility')) {
            $query->where('is_visible', $request->visibility === 'visible');
        }

        if ($request->boolean('reported')) {
            $query->where('report_count', '>', 0);
        }
        if ($request->boolean('flagged')) {
            $query->where('is_flagged', true);
        }

        $reviews = $query->orderByDesc('created_at')->paginate(20)->withQueryString();

        // Stats
        $stats = [
            'total'      => Review::count(),
            'pending'    => Review::where('status', 'pending')->count(),
            'approved'   => Review::where('status', 'approved')->count(),
            'hidden'     => Review::where('status', 'hidden')->count(),
            'flagged'    => Review::where('is_flagged', true)->count(),
            'reported'   => Review::where('report_count', '>', 0)->count(),
            'avg_rating' => round((float) Review::where('status', 'approved')->avg('rating'), 2),
        ];

        $ratingStats = Review::statsFor(Review::where('status', 'approved'));
        $pendingReports = ReviewReport::where('status', 'pending')->count();

        return view('admin.reviews.index', compact('reviews', 'stats', 'ratingStats', 'pendingReports'));
    }

    public function show(Review $review)
    {
        $review->load(['user', 'photographer', 'event', 'order', 'reports.user']);
        return view('admin.reviews.show', compact('review'));
    }

    public function approve(Review $review)
    {
        $oldStatus = $review->status;
        $review->update(['status' => 'approved', 'is_visible' => true, 'is_flagged' => false]);

        ActivityLogger::admin(
            action: 'review.approved',
            target: $review,
            description: "อนุมัติรีวิว #{$review->id} (rating: {$review->rating})",
            oldValues: ['status' => $oldStatus],
            newValues: ['status' => 'approved', 'is_visible' => true, 'is_flagged' => false],
        );

        return back()->with('success', 'อนุมัติรีวิวเรียบร้อย');
    }

    public function hide(Review $review)
    {
        $oldStatus = $review->status;
        $review->update(['status' => 'hidden', 'is_visible' => false]);

        ActivityLogger::admin(
            action: 'review.hidden',
            target: $review,
            description: "ซ่อนรีวิว #{$review->id}",
            oldValues: ['status' => $oldStatus],
            newValues: ['status' => 'hidden', 'is_visible' => false],
        );

        return back()->with('success', 'ซ่อนรีวิวเรียบร้อย');
    }

    public function reject(Request $request, Review $review)
    {
        $request->validate(['reason' => 'nullable|string|max:255']);

        $oldStatus = $review->status;
        $review->update(['status' => 'rejected', 'is_visible' => false, 'flag_reason' => $request->reason]);

        ActivityLogger::admin(
            action: 'review.rejected',
            target: $review,
            description: "ปฏิเสธรีวิว #{$review->id}" . ($request->reason ? " (เหตุผล: {$request->reason})" : ''),
            oldValues: ['status' => $oldStatus],
            newValues: ['status' => 'rejected', 'is_visible' => false, 'reason' => $request->reason],
        );

        return back()->with('success', 'ปฏิเสธรีวิวเรียบร้อย');
    }

    public function toggleFlag(Request $request, Review $review)
    {
        $request->validate(['reason' => 'nullable|string|max:255']);

        $wasFlagged = (bool) $review->is_flagged;

        $review->update([
            'is_flagged'  => !$review->is_flagged,
            'flag_reason' => $review->is_flagged ? null : $request->reason,
        ]);

        ActivityLogger::admin(
            action: 'review.flag_toggled',
            target: $review,
            description: ($wasFlagged ? 'ยกเลิกธง' : 'ติดธง') . " รีวิว #{$review->id}",
            oldValues: ['is_flagged' => $wasFlagged],
            newValues: ['is_flagged' => !$wasFlagged, 'reason' => $request->reason],
        );

        return back()->with('success', $review->fresh()->is_flagged ? 'ติดธงรีวิวเรียบร้อย' : 'ยกเลิกธงรีวิว');
    }

    public function reply(Request $request, Review $review)
    {
        $request->validate(['reply' => 'required|string|max:2000']);

        $review->update([
            'admin_reply'    => $request->reply,
            'admin_reply_at' => now(),
        ]);

        try {
            \App\Models\UserNotification::notify(
                $review->user_id,
                'review',
                'ทีมงานตอบรีวิวของคุณแล้ว',
                mb_substr($request->reply, 0, 150),
                'notifications'
            );
        } catch (\Throwable $e) {
            \Log::warning('Review reply notification failed: ' . $e->getMessage());
        }

        return back()->with('success', 'ตอบรีวิวเรียบร้อย');
    }

    public function destroy(Review $review)
    {
        $snapshot = [
            'id'             => $review->id,
            'user_id'        => $review->user_id,
            'photographer_id' => $review->photographer_id,
            'event_id'       => $review->event_id,
            'rating'         => $review->rating,
            'status'         => $review->status,
        ];

        $review->delete();

        ActivityLogger::admin(
            action: 'review.deleted',
            target: ['Review', (int) $snapshot['id']],
            description: "ลบรีวิว #{$snapshot['id']} (rating: {$snapshot['rating']})",
            oldValues: $snapshot,
            newValues: null,
        );

        return back()->with('success', 'ลบรีวิวเรียบร้อย');
    }

    public function bulkAction(Request $request)
    {
        $request->validate([
            'action' => 'required|in:approve,hide,reject,delete,toggle_flag',
            'ids'    => 'required|array',
            'ids.*'  => 'integer',
        ]);

        $ids = $request->ids;
        $query = Review::whereIn('id', $ids);

        switch ($request->action) {
            case 'approve':
                $count = $query->update(['status' => 'approved', 'is_visible' => true, 'is_flagged' => false]);
                $msg = "อนุมัติ {$count} รีวิว";
                break;
            case 'hide':
                $count = $query->update(['status' => 'hidden', 'is_visible' => false]);
                $msg = "ซ่อน {$count} รีวิว";
                break;
            case 'reject':
                $count = $query->update(['status' => 'rejected', 'is_visible' => false]);
                $msg = "ปฏิเสธ {$count} รีวิว";
                break;
            case 'delete':
                $count = $query->delete();
                $msg = "ลบ {$count} รีวิว";
                break;
            case 'toggle_flag':
                $items = $query->get();
                foreach ($items as $r) {
                    $r->update(['is_flagged' => !$r->is_flagged]);
                }
                $count = $items->count();
                $msg = "สลับสถานะธง {$count} รีวิว";
                break;
            default:
                $count = 0;
                $msg = '';
        }

        ActivityLogger::admin(
            action: 'review.bulk_' . $request->action,
            target: null,
            description: "Bulk {$request->action} รีวิว ({$count} รายการ)",
            oldValues: null,
            newValues: [
                'action'         => $request->action,
                'affected_count' => (int) $count,
                'review_ids'     => array_map('intval', $ids),
            ],
        );

        return back()->with('success', $msg);
    }

    public function reports(Request $request)
    {
        $query = ReviewReport::with(['review.user', 'review.photographer', 'user']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        } else {
            $query->where('status', 'pending');
        }

        if ($request->filled('reason')) {
            $query->where('reason', $request->reason);
        }

        $reports = $query->orderByDesc('created_at')->paginate(20)->withQueryString();
        return view('admin.reviews.reports', compact('reports'));
    }

    public function resolveReport(Request $request, ReviewReport $report)
    {
        $action = $request->input('action', 'dismiss');
        $adminId = Auth::guard('admin')->id();

        $report->update([
            'status'               => $action === 'dismiss' ? 'dismissed' : 'reviewed',
            'resolved_by_admin_id' => $adminId,
            'resolved_at'          => now(),
        ]);

        $reviewId = $report->review?->id;
        $reviewRating = $report->review?->rating;

        if ($action === 'hide_review') {
            $report->review?->update(['status' => 'hidden', 'is_visible' => false]);

            ActivityLogger::admin(
                action: 'review.report_resolved_hide',
                target: $report,
                description: "แก้ไขรายงาน #{$report->id} — ซ่อนรีวิว #{$reviewId}",
                oldValues: null,
                newValues: [
                    'report_id' => (int) $report->id,
                    'review_id' => $reviewId ? (int) $reviewId : null,
                    'reason'    => $report->reason,
                ],
            );

            return back()->with('success', 'ซ่อนรีวิวและดำเนินการรายงานเรียบร้อย');
        }

        if ($action === 'delete_review') {
            $report->review?->delete();

            ActivityLogger::admin(
                action: 'review.report_resolved_delete',
                target: $report,
                description: "แก้ไขรายงาน #{$report->id} — ลบรีวิว #{$reviewId}",
                oldValues: null,
                newValues: [
                    'report_id'       => (int) $report->id,
                    'deleted_review_id' => $reviewId ? (int) $reviewId : null,
                    'rating'          => $reviewRating,
                    'reason'          => $report->reason,
                ],
            );

            return back()->with('success', 'ลบรีวิวและดำเนินการรายงานเรียบร้อย');
        }

        // dismiss or default
        ActivityLogger::admin(
            action: 'review.report_dismissed',
            target: $report,
            description: "ยกเลิกรายงาน #{$report->id} (ไม่มีการดำเนินการกับรีวิว)",
            oldValues: null,
            newValues: [
                'report_id' => (int) $report->id,
                'review_id' => $reviewId ? (int) $reviewId : null,
                'reason'    => $report->reason,
            ],
        );

        return back()->with('success', 'ดำเนินการรายงานเรียบร้อย');
    }
}

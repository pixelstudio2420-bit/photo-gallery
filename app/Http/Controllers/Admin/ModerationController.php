<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ModeratePhotoJob;
use App\Models\Event;
use App\Models\EventPhoto;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Admin image-moderation dashboard.
 *
 * Two audiences:
 *   1. Flagged queue (50-90% confidence) — "decide this photo"
 *   2. Rejected archive & approved audit log — "see what the AI did"
 *
 * All write endpoints log via ActivityLogger so the decision trail survives
 * admin handoffs and can be diffed against AWS's auto-decisions.
 */
class ModerationController extends Controller
{
    /**
     * Dashboard + photo list with filters.
     *
     * Default filter = flagged (where the admin is actually needed). Other
     * statuses are a click away via the top pills.
     */
    public function index(Request $request)
    {
        $status = $request->get('status', 'flagged');
        $validStatuses = ['pending', 'flagged', 'rejected', 'approved', 'skipped', 'all'];
        if (!in_array($status, $validStatuses, true)) {
            $status = 'flagged';
        }

        $query = EventPhoto::with(['event:id,name,slug', 'uploader:id,first_name,last_name,email']);

        if ($status !== 'all') {
            $query->where('moderation_status', $status);
        }

        if ($request->filled('event_id')) {
            $query->where('event_id', (int) $request->event_id);
        }

        if ($request->filled('min_score')) {
            $query->where('moderation_score', '>=', (float) $request->min_score);
        }

        // Show highest-risk first for flagged; newest first for everything else
        // (flagged admin wants to triage biggest threats; other views are audit).
        $query = $status === 'flagged'
            ? $query->orderByDesc('moderation_score')->orderByDesc('created_at')
            : $query->orderByDesc('moderation_reviewed_at')->orderByDesc('created_at');

        $photos = $query->paginate(24)->withQueryString();

        $stats = [
            'pending'  => EventPhoto::where('moderation_status', 'pending')->count(),
            'flagged'  => EventPhoto::where('moderation_status', 'flagged')->count(),
            'rejected' => EventPhoto::where('moderation_status', 'rejected')->count(),
            'approved' => EventPhoto::where('moderation_status', 'approved')->count(),
            'skipped'  => EventPhoto::where('moderation_status', 'skipped')->count(),
            'total'    => EventPhoto::count(),
        ];

        // Optional event filter dropdown — only the events with at least one
        // moderated-in-some-way photo (keeps the dropdown relevant).
        $events = Event::whereIn('id', function ($q) {
                $q->select('event_id')->from('event_photos')
                  ->whereIn('moderation_status', ['flagged', 'rejected', 'pending']);
            })
            ->select('id', 'name')
            ->orderBy('name')
            ->limit(200)
            ->get();

        return view('admin.moderation.index', compact('photos', 'stats', 'status', 'events'));
    }

    /**
     * Detail view for a single flagged photo — shows the preview, the AWS
     * labels, and Approve/Reject/Reason controls.
     */
    public function show(int $id)
    {
        $photo = EventPhoto::with(['event', 'uploader'])->findOrFail($id);
        return view('admin.moderation.show', compact('photo'));
    }

    /**
     * Approve a flagged photo — marks it `approved`, keeps labels for audit.
     */
    public function approve(Request $request, int $id)
    {
        $photo = EventPhoto::findOrFail($id);

        $photo->forceFill([
            'moderation_status'      => 'approved',
            'moderation_reviewed_by' => Auth::guard('admin')->id(),
            'moderation_reviewed_at' => now(),
        ])->save();

        ActivityLogger::admin(
            action: 'moderation.approved',
            target: $photo,
            description: "Approved flagged photo #{$photo->id}"
        );

        return $this->redirectBack($request, 'อนุมัติภาพเรียบร้อย');
    }

    /**
     * Reject a flagged photo + optional reason. Photo is hidden from public
     * listings (scopeVisibleToPublic only allows approved|skipped).
     */
    public function reject(Request $request, int $id)
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $photo = EventPhoto::findOrFail($id);

        $photo->forceFill([
            'moderation_status'        => 'rejected',
            'moderation_reviewed_by'   => Auth::guard('admin')->id(),
            'moderation_reviewed_at'   => now(),
            'moderation_reject_reason' => $validated['reason'] ?? null,
        ])->save();

        ActivityLogger::admin(
            action: 'moderation.rejected',
            target: $photo,
            description: "Rejected photo #{$photo->id}"
                . (!empty($validated['reason']) ? " — reason: {$validated['reason']}" : '')
        );

        return $this->redirectBack($request, 'ปฏิเสธภาพเรียบร้อย');
    }

    /**
     * Skip moderation entirely for this photo — treats as approved but records
     * the decision as "admin intentionally bypassed the AI check".
     */
    public function skip(Request $request, int $id)
    {
        $photo = EventPhoto::findOrFail($id);

        $photo->forceFill([
            'moderation_status'      => 'skipped',
            'moderation_reviewed_by' => Auth::guard('admin')->id(),
            'moderation_reviewed_at' => now(),
        ])->save();

        ActivityLogger::admin(
            action: 'moderation.skipped',
            target: $photo,
            description: "Manually skipped moderation for photo #{$photo->id}"
        );

        return $this->redirectBack($request, 'ยกเว้นภาพจากการตรวจสอบ');
    }

    /**
     * Re-queue a single photo for a fresh AWS scan (admin override).
     */
    public function rescan(Request $request, int $id)
    {
        $photo = EventPhoto::findOrFail($id);

        $photo->forceFill([
            'moderation_status'      => 'pending',
            'moderation_reviewed_by' => null,
            'moderation_reviewed_at' => null,
        ])->save();

        ModeratePhotoJob::dispatch($photo->id);

        ActivityLogger::admin(
            action: 'moderation.rescan',
            target: $photo,
            description: "Queued re-scan for photo #{$photo->id}"
        );

        return $this->redirectBack($request, 'ส่งคิวสแกนใหม่แล้ว');
    }

    /**
     * Bulk action across a set of photo IDs.
     * Accepts 'action' ∈ {approve, reject, skip, rescan} and 'ids' array.
     */
    public function bulkAction(Request $request)
    {
        $validated = $request->validate([
            'action' => 'required|in:approve,reject,skip,rescan',
            'ids'    => 'required|array|min:1|max:500',
            'ids.*'  => 'integer',
            'reason' => 'nullable|string|max:500',
        ]);

        $adminId   = Auth::guard('admin')->id();
        $action    = $validated['action'];
        $ids       = $validated['ids'];
        $reason    = $validated['reason'] ?? null;
        $processed = 0;

        $photos = EventPhoto::whereIn('id', $ids)->get();

        foreach ($photos as $photo) {
            switch ($action) {
                case 'approve':
                    $photo->forceFill([
                        'moderation_status'      => 'approved',
                        'moderation_reviewed_by' => $adminId,
                        'moderation_reviewed_at' => now(),
                    ])->save();
                    break;

                case 'reject':
                    $photo->forceFill([
                        'moderation_status'        => 'rejected',
                        'moderation_reviewed_by'   => $adminId,
                        'moderation_reviewed_at'   => now(),
                        'moderation_reject_reason' => $reason,
                    ])->save();
                    break;

                case 'skip':
                    $photo->forceFill([
                        'moderation_status'      => 'skipped',
                        'moderation_reviewed_by' => $adminId,
                        'moderation_reviewed_at' => now(),
                    ])->save();
                    break;

                case 'rescan':
                    $photo->forceFill([
                        'moderation_status'      => 'pending',
                        'moderation_reviewed_by' => null,
                        'moderation_reviewed_at' => null,
                    ])->save();
                    ModeratePhotoJob::dispatch($photo->id);
                    break;
            }
            $processed++;
        }

        ActivityLogger::admin(
            action: "moderation.bulk.{$action}",
            target: null,
            description: "Bulk {$action} on {$processed} photos"
        );

        if ($request->expectsJson()) {
            return response()->json([
                'success'   => true,
                'processed' => $processed,
                'message'   => "ดำเนินการ {$processed} รายการเรียบร้อย",
            ]);
        }

        return back()->with('success', "ดำเนินการ {$processed} รายการเรียบร้อย");
    }

    /**
     * Redirect helper — supports both JSON (AJAX) and form submits.
     */
    private function redirectBack(Request $request, string $message)
    {
        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => $message]);
        }
        return back()->with('success', $message);
    }
}

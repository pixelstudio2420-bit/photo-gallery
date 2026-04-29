<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{
    /**
     * Admin notifications management page.
     */
    public function index(Request $request)
    {
        $query = AdminNotification::query()->orderByDesc('created_at');

        // Filters
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('status')) {
            if ($request->status === 'unread') {
                $query->where('is_read', false);
            } elseif ($request->status === 'read') {
                $query->where('is_read', true);
            }
        }

        if ($request->filled('q')) {
            $q = $request->q;
            $query->where(function ($sub) use ($q) {
                $sub->where('title', 'LIKE', "%{$q}%")
                    ->orWhere('message', 'LIKE', "%{$q}%");
            });
        }

        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }
        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        $notifications = $query->paginate(30)->withQueryString();

        // Stats
        $stats = [
            'total'        => AdminNotification::count(),
            'unread'       => AdminNotification::where('is_read', false)->count(),
            'today'        => AdminNotification::whereDate('created_at', today())->count(),
            'this_week'    => AdminNotification::where('created_at', '>=', now()->startOfWeek())->count(),
        ];

        // Type breakdown
        $typeBreakdown = AdminNotification::select('type', DB::raw('COUNT(*) as count'))
            ->groupBy('type')
            ->orderByDesc('count')
            ->pluck('count', 'type')
            ->toArray();

        return view('admin.notifications.index', compact('notifications', 'stats', 'typeBreakdown'));
    }

    /**
     * Mark a notification as read (web form).
     */
    public function markRead($id)
    {
        AdminNotification::where('id', $id)->update(['is_read' => true, 'read_at' => now()]);
        return back()->with('success', 'ทำเครื่องหมายเป็นอ่านแล้ว');
    }

    /**
     * Mark all as read.
     */
    public function markAllRead()
    {
        AdminNotification::where('is_read', false)
            ->update(['is_read' => true, 'read_at' => now()]);
        return back()->with('success', 'ทำเครื่องหมายเป็นอ่านแล้วทั้งหมด');
    }

    /**
     * Delete a notification.
     */
    public function destroy($id)
    {
        AdminNotification::where('id', $id)->delete();
        return back()->with('success', 'ลบการแจ้งเตือนเรียบร้อย');
    }

    /**
     * Bulk action (delete / mark read / mark unread).
     */
    public function bulkAction(Request $request)
    {
        $request->validate([
            'action'     => 'required|in:delete,mark_read,mark_unread',
            'ids'        => 'required|array',
            'ids.*'      => 'integer',
        ]);

        $query = AdminNotification::whereIn('id', $request->ids);

        $count = 0;
        switch ($request->action) {
            case 'delete':
                $count = $query->delete();
                $msg = "ลบ {$count} รายการเรียบร้อย";
                break;
            case 'mark_read':
                $count = $query->update(['is_read' => true, 'read_at' => now()]);
                $msg = "ทำเครื่องหมายอ่านแล้ว {$count} รายการ";
                break;
            case 'mark_unread':
                $count = $query->update(['is_read' => false, 'read_at' => null]);
                $msg = "ทำเครื่องหมายยังไม่อ่าน {$count} รายการ";
                break;
            default:
                $msg = '';
        }

        return back()->with('success', $msg);
    }

    /**
     * Clean up old read notifications.
     */
    public function cleanup(Request $request)
    {
        $days = (int) $request->input('days', 90);
        $count = AdminNotification::cleanup($days);
        return back()->with('success', "ลบการแจ้งเตือนเก่า {$count} รายการ (เก่ากว่า {$days} วัน)");
    }

    /**
     * Send broadcast notification to users (system announcement).
     */
    public function broadcast(Request $request)
    {
        $request->validate([
            'title'        => 'required|string|max:255',
            'message'      => 'required|string|max:1000',
            'action_url'   => 'nullable|string|max:500',
            'target'       => 'required|in:all,customers,photographers',
        ]);

        $userQuery = DB::table('auth_users');
        if ($request->target === 'photographers') {
            $userQuery->whereIn('id', DB::table('photographer_profiles')->where('status', 'approved')->pluck('user_id'));
        } elseif ($request->target === 'customers') {
            $userQuery->whereNotIn('id', DB::table('photographer_profiles')->where('status', 'approved')->pluck('user_id'));
        }

        $userIds = $userQuery->pluck('id');
        $batch = [];
        $now = now();

        foreach ($userIds as $userId) {
            $batch[] = [
                'user_id'    => $userId,
                'type'       => 'system',
                'title'      => $request->title,
                'message'    => $request->message,
                'action_url' => $request->action_url,
                'is_read'    => false,
                'created_at' => $now,
            ];

            // Insert in chunks of 500 to avoid max query size
            if (count($batch) >= 500) {
                DB::table('user_notifications')->insert($batch);
                $batch = [];
            }
        }
        if (!empty($batch)) {
            DB::table('user_notifications')->insert($batch);
        }

        return back()->with('success', "ส่งการแจ้งเตือนไปยัง " . count($userIds) . " ผู้ใช้เรียบร้อย");
    }
}

<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\UserNotification;

class NotificationController extends Controller
{
    /**
     * Notification centre page with filter and pagination.
     */
    public function index(Request $request)
    {
        $user   = Auth::user();
        $filter = $request->get('filter', 'all'); // all | unread

        $query = UserNotification::where('user_id', $user->id)
            ->orderByDesc('created_at');

        if ($filter === 'unread') {
            $query->where('is_read', false);
        }

        $notifications = $query->paginate(20)->withQueryString();

        $unreadCount = UserNotification::where('user_id', $user->id)
            ->where('is_read', false)
            ->count();

        return view('public.notifications.index', compact('notifications', 'unreadCount', 'filter'));
    }
}

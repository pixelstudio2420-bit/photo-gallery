<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Models\UserNotification;

class NotificationApiController extends Controller
{
    /**
     * List the authenticated user's notifications.
     *
     * Cursor modes (mirrors the admin endpoint):
     *
     *   1. ?since_id=42  → return rows with id > 42 as `new_items`.
     *      Preferred — id-based, no timezone issues, idempotent.
     *
     *   2. ?since=YYYY-MM-DD HH:MM:SS → legacy time-based cursor.
     *      Honoured only if the cursor is within the last 24 hours;
     *      stale clients get no `new_items` so we don't flood toasts
     *      after a long break.
     *
     *   No cursor → returns the panel snapshot + `latest_id` baseline.
     *
     * Always returns `latest_id` so the client can baseline its cursor
     * on the very first poll.
     */
    public function index(Request $request)
    {
        $userId = Auth::id();
        if (!$userId) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $base = UserNotification::where('user_id', $userId);

        $newItems = null;

        // Preferred id-based cursor.
        if ($request->filled('since_id')) {
            $sinceId = (int) $request->query('since_id');
            if ($sinceId > 0) {
                $newItems = (clone $base)
                    ->where('id', '>', $sinceId)
                    ->orderByDesc('id')
                    ->limit(20)
                    ->get();
            }
        }
        // Legacy time-based cursor — guarded against stale floods.
        elseif ($request->filled('since')) {
            try {
                $sinceDate = \Carbon\Carbon::parse($request->query('since'));
                if ($sinceDate->greaterThanOrEqualTo(now()->subDay())) {
                    $newItems = (clone $base)
                        ->where('created_at', '>', $sinceDate)
                        ->orderByDesc('created_at')
                        ->limit(20)
                        ->get();
                }
            } catch (\Throwable $e) {
                // Bad cursor — let the client re-baseline.
            }
        }

        $notifications = (clone $base)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        $unreadCount = $this->cachedUnreadCount($userId);

        $latestId = (int) ((clone $base)->max('id') ?? 0);

        $response = [
            'success'       => true,
            'unread_count'  => $unreadCount,
            'notifications' => $notifications,
            'timestamp'     => now()->toDateTimeString(),
            'latest_id'     => $latestId,
        ];

        if ($newItems !== null) {
            $response['new_items'] = $newItems;
        }

        return response()->json($response);
    }

    /**
     * Lightweight endpoint: return only unread count (for badge polling).
     * Caches per-user for 10 seconds — bell badge isn't transactional so
     * a 10s lag is fine, and it cuts DB load when the navbar polls every
     * 30s across many open tabs.
     */
    public function unreadCount()
    {
        $userId = Auth::id();
        if (!$userId) {
            return response()->json(['success' => false, 'unread_count' => 0]);
        }
        return response()->json([
            'success'      => true,
            'unread_count' => $this->cachedUnreadCount($userId),
        ]);
    }

    /**
     * Helper: per-user unread count with a 10-second cache.
     * Cache busted on markRead / markAllRead / new notification creation
     * so changes feel instant when the user is actively interacting.
     */
    private function cachedUnreadCount(int $userId): int
    {
        return (int) Cache::remember(
            $this->unreadCacheKey($userId),
            now()->addSeconds(10),
            fn () => UserNotification::where('user_id', $userId)
                ->where('is_read', false)
                ->count()
        );
    }

    private function unreadCacheKey(int $userId): string
    {
        return "user.unread_notifications:{$userId}";
    }

    public function markRead($id)
    {
        $userId = Auth::id();
        UserNotification::where('id', $id)
            ->where('user_id', $userId)
            ->update(['is_read' => true, 'read_at' => now()]);
        // Bust the cache so the badge updates immediately rather than
        // waiting up to 10s for the cached count to expire.
        if ($userId) Cache::forget($this->unreadCacheKey($userId));
        return response()->json(['success' => true]);
    }

    public function markAllRead()
    {
        $userId = Auth::id();
        UserNotification::where('user_id', $userId)
            ->where('is_read', false)
            ->update(['is_read' => true, 'read_at' => now()]);
        if ($userId) Cache::forget($this->unreadCacheKey($userId));
        return response()->json(['success' => true]);
    }

    /**
     * Track the authenticated user's presence (last seen) via cache.
     * Returns a list of which user IDs are currently online (active in the last 5 minutes).
     */
    public function presence(Request $request)
    {
        $user = Auth::user();
        if ($user) {
            // Store a presence key in cache for 5 minutes
            $cacheKey = 'presence:user:' . $user->id;
            Cache::put($cacheKey, now()->toIso8601String(), now()->addMinutes(5));

            // Update last_login_at column if it exists (auth_users has last_login_at)
            try {
                DB::table('auth_users')
                    ->where('id', $user->id)
                    ->update(['last_login_at' => now()]);
            } catch (\Throwable $e) {
                // Column may not exist in all environments; silently skip
            }
        }

        return response()->json([
            'online'    => true,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    // =====================================================================
    // Admin Notification Endpoints
    // =====================================================================

    /**
     * List admin notifications.
     *
     * Two cursor modes for polling:
     *
     *   1. ?since_id=42  → return rows with id > 42 as new_items.
     *      This is the preferred mode: ID-based, no timezone issues,
     *      and inherently deduplicated (id is monotonic).
     *
     *   2. ?since=2026-04-26+10:00:00  → legacy time-based cursor.
     *      Kept for backward compatibility but **only honoured if the
     *      timestamp is within the last 24 hours** to defend against
     *      stale clients that send a UTC time which the server then
     *      mis-interprets as local time (=> 7-hour shift => floods of
     *      "new" toasts on first load). Beyond 24h we return no
     *      new_items and let the client re-baseline its cursor.
     *
     * Without any cursor, no new_items array is returned (just the
     * panel snapshot + the latest_id baseline). This is what every
     * fresh page load uses now — pull the snapshot, learn the cursor,
     * then poll incrementally.
     */
    public function adminIndex(Request $request)
    {
        $query = DB::table('admin_notifications')->orderByDesc('created_at');

        $newItems = null;

        // Preferred: id-based cursor (timezone-safe, idempotent).
        if ($request->filled('since_id')) {
            $sinceId = (int) $request->query('since_id');
            if ($sinceId > 0) {
                $newItems = DB::table('admin_notifications')
                    ->where('id', '>', $sinceId)
                    ->orderByDesc('id')
                    ->limit(20)
                    ->get();
            }
        }
        // Legacy: time-based cursor — guarded against the timezone
        // floor-drop. We only return new_items when the cursor is
        // recent (≤24h old in server-local time).
        elseif ($request->filled('since')) {
            try {
                $sinceDate = \Carbon\Carbon::parse($request->query('since'));
                if ($sinceDate->greaterThanOrEqualTo(now()->subDay())) {
                    $newItems = (clone $query)
                        ->where('created_at', '>', $sinceDate)
                        ->limit(20)
                        ->get();
                }
            } catch (\Throwable $e) {
                // Bad cursor — let the client re-baseline, no items.
            }
        }

        $notifications = $query->limit(50)->get();
        $unreadCount   = DB::table('admin_notifications')
            ->where('is_read', false)
            ->count();
        $latestId      = (int) (DB::table('admin_notifications')->max('id') ?? 0);

        $response = [
            'success'       => true,
            'unread_count'  => $unreadCount,
            'notifications' => $notifications,
            'timestamp'     => now()->toDateTimeString(),
            'latest_id'     => $latestId,   // ← Client uses this as cursor
        ];

        if ($newItems !== null) {
            $response['new_items'] = $newItems;
        }

        return response()->json($response);
    }

    /**
     * Mark a specific admin notification as read.
     */
    public function adminMarkRead($id)
    {
        $updated = DB::table('admin_notifications')
            ->where('id', $id)
            ->update(['is_read' => true, 'read_at' => now()]);

        return response()->json(['success' => $updated > 0]);
    }

    /**
     * Mark all admin notifications as read.
     */
    public function adminMarkAllRead()
    {
        DB::table('admin_notifications')
            ->where('is_read', false)
            ->update(['is_read' => true, 'read_at' => now()]);

        return response()->json(['success' => true]);
    }

    /**
     * Mark admin notifications as read by type(s) and optional ref_id.
     * Used for auto-dismissing the bell count when related actions are performed.
     * Body: { types: ["digital_order","digital_slip"], ref_id: "12" }
     */
    public function adminMarkByRef(Request $request)
    {
        $types  = (array) $request->input('types', []);
        $refId  = $request->input('ref_id');

        if (empty($types) && empty($refId)) {
            return response()->json(['success' => false, 'message' => 'types or ref_id required'], 422);
        }

        $q = DB::table('admin_notifications')->where('is_read', false);
        if (!empty($types))    $q->whereIn('type', $types);
        if ($refId !== null && $refId !== '') $q->where('ref_id', (string) $refId);

        $affected = $q->update(['is_read' => true, 'read_at' => now()]);
        $unread   = DB::table('admin_notifications')->where('is_read', false)->count();

        return response()->json([
            'success'      => true,
            'affected'     => $affected,
            'unread_count' => $unread,
        ]);
    }

    /**
     * Dashboard live stats for admin polling.
     *
     * Cached for 30 seconds to match the polling cadence in
     * admin-notifications.js. With 3 admins × 3 tabs each = 9 polls/30s,
     * this drops the DB load from 5 aggregates × 9 = 45 queries to 5
     * queries per 30-second window. Stats inherently lag a few seconds
     * already (admin clicks → reload), so freshness is unaffected.
     *
     * Cache invalidation isn't needed — 30s TTL is short enough that
     * stale numbers self-correct quickly. If a future feature needs
     * "instant" stats (e.g. live leaderboard), call
     * Cache::forget('admin.live_stats') from the relevant write path.
     */
    public function adminStats()
    {
        $stats = \Illuminate\Support\Facades\Cache::remember(
            'admin.live_stats',
            now()->addSeconds(30),
            fn () => [
                'pending_orders' => DB::table('orders')->where('status', 'pending_payment')->count(),
                'pending_slips'  => DB::table('payment_slips')->where('verify_status', 'pending')->count(),
                'today_orders'   => DB::table('orders')->whereDate('created_at', today())->count(),
                'today_revenue'  => DB::table('orders')->where('status', 'paid')->whereDate('created_at', today())->sum('total'),
                'total_users'    => DB::table('auth_users')->count(),
            ]
        );

        return response()->json([
            'success' => true,
            'stats'   => $stats,
        ]);
    }
}

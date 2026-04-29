<?php

namespace App\Http\Middleware;

use App\Services\UserPresenceService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Track per-user "last seen" activity.
 *
 * Performance guardrail:
 * - Previously this wrote an UPSERT to user_sessions on EVERY request. With
 *   1000 concurrent users that's ~10k writes/second against MySQL.
 * - Now we throttle via cache: each user writes to the DB at most once every
 *   `DB_WRITE_INTERVAL` seconds. AJAX-heavy pages (polling, autosave) no longer
 *   hammer the session table.
 * - Background AJAX polls (GET /notifications, /online-count, etc.) are also
 *   skipped — they already run on a timer and would double-count.
 */
class TrackUserPresence
{
    /** Write to DB at most once per user per this many seconds. */
    private const DB_WRITE_INTERVAL = 60;

    /** URL prefixes that should NOT trigger a presence write (polling/keep-alive). */
    private const SKIP_PATH_PREFIXES = [
        'api/presence',
        'api/online',
        'api/notifications/poll',
        'admin/online-users/api',
        'livewire',
        '_debugbar',
    ];

    public function __construct(private UserPresenceService $presence) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Skip noisy polling endpoints — don't do work after response is sent either.
        if ($this->shouldSkip($request)) {
            return $response;
        }

        $userId = $this->resolveUserId();
        if ($userId === null) {
            return $response;
        }

        // Per-user throttle: only hit the DB once per minute.
        $cacheKey = "presence:lock:{$userId}";
        if (Cache::has($cacheKey)) {
            return $response;
        }

        // Take the lock FIRST so concurrent requests don't all write.
        Cache::put($cacheKey, 1, self::DB_WRITE_INTERVAL);

        try {
            $this->presence->recordActivity($userId, $request);
        } catch (\Throwable $e) {
            // Never let presence tracking crash the request
        }

        return $response;
    }

    private function shouldSkip(Request $request): bool
    {
        // Asset-ish requests (shouldn't normally reach here but be safe)
        if ($request->isMethod('OPTIONS')) {
            return true;
        }
        $path = ltrim($request->path(), '/');
        foreach (self::SKIP_PATH_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }
        return false;
    }

    private function resolveUserId(): ?int
    {
        try {
            if (Auth::guard('web')->check()) {
                return (int) Auth::guard('web')->id();
            }
            if (Auth::guard('admin')->check()) {
                return (int) Auth::guard('admin')->id();
            }
        } catch (\Throwable $e) {
            // Guard not defined
        }
        return null;
    }
}

<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UserPresenceService
{
    /** Hard cap on online users returned in a single query to protect memory. */
    private const MAX_ONLINE_USERS = 500;

    /**
     * Record user activity (call on each request via middleware).
     */
    public function recordActivity(int $userId, Request $request): void
    {
        try {
            if (!Schema::hasTable('user_sessions')) {
                return;
            }

            $ua      = $request->userAgent() ?? '';
            $parsed  = $this->parseUserAgent($ua);

            DB::table('user_sessions')->upsert(
                [
                    'user_id'       => $userId,
                    'ip_address'    => $request->ip(),
                    'user_agent'    => mb_substr($ua, 0, 500),
                    'device_type'   => $parsed['device_type'],
                    'browser'       => $parsed['browser'],
                    'os'            => $parsed['os'],
                    'last_activity' => now(),
                    'is_online'     => 1,
                    'created_at'    => now(),
                ],
                ['user_id'],                          // unique key
                ['ip_address', 'user_agent', 'device_type', 'browser', 'os', 'last_activity', 'is_online']
            );
        } catch (\Throwable $e) {
            // Table may not exist
        }
    }

    /**
     * Get online users (active in last 5 minutes).
     *
     * Capped at {@see self::MAX_ONLINE_USERS} rows to protect memory on large
     * sites. Result is cached for 10 seconds so the admin dashboard + public
     * online-count polling don't each trigger a full JOIN on every tick.
     *
     * @param int|null $limit Override the default cap (1..MAX_ONLINE_USERS)
     */
    public function getOnlineUsers(?int $limit = null): Collection
    {
        $limit = max(1, min($limit ?? self::MAX_ONLINE_USERS, self::MAX_ONLINE_USERS));

        return Cache::remember("presence:online_users:{$limit}", 10, function () use ($limit) {
            try {
                if (!Schema::hasTable('user_sessions')) {
                    return collect();
                }

                $threshold = now()->subMinutes(5);

                // Correct table is auth_users (not "users") — the previous
                // LEFT JOIN against "users" silently returned nulls for name/email.
                return DB::table('user_sessions as us')
                    ->leftJoin('auth_users as u', 'us.user_id', '=', 'u.id')
                    ->where('us.last_activity', '>=', $threshold)
                    ->select(
                        'us.*',
                        'u.first_name',
                        'u.last_name',
                        'u.email'
                    )
                    ->orderByDesc('us.last_activity')
                    ->limit($limit)
                    ->get();
            } catch (\Throwable $e) {
                return collect();
            }
        });
    }

    /**
     * Get online user count (cached 10s).
     */
    public function getOnlineCount(): int
    {
        return (int) Cache::remember('presence:online_count', 10, function () {
            try {
                if (!Schema::hasTable('user_sessions')) {
                    return 0;
                }
                return DB::table('user_sessions')
                    ->where('last_activity', '>=', now()->subMinutes(5))
                    ->count();
            } catch (\Throwable $e) {
                return 0;
            }
        });
    }

    /**
     * Get user's last activity timestamp.
     */
    public function getLastActivity(int $userId): ?Carbon
    {
        try {
            if (!Schema::hasTable('user_sessions')) {
                return null;
            }

            $row = DB::table('user_sessions')
                ->where('user_id', $userId)
                ->value('last_activity');

            return $row ? Carbon::parse($row) : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Check if a user is currently online.
     */
    public function isOnline(int $userId): bool
    {
        try {
            if (!Schema::hasTable('user_sessions')) {
                return false;
            }

            return DB::table('user_sessions')
                ->where('user_id', $userId)
                ->where('last_activity', '>=', now()->subMinutes(5))
                ->exists();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Log a specific user action to user_activity_log.
     */
    public function logAction(int $userId, string $action, string $description = ''): void
    {
        try {
            if (!Schema::hasTable('user_activity_log')) {
                return;
            }

            DB::table('user_activity_log')->insert([
                'user_id'     => $userId,
                'action'      => $action,
                'description' => $description,
                'ip_address'  => request()->ip(),
                'user_agent'  => mb_substr(request()->userAgent() ?? '', 0, 500),
                'created_at'  => now(),
            ]);
        } catch (\Throwable $e) {
            // Silently fail
        }
    }

    /**
     * Parse User-Agent string for device type, browser, and OS.
     */
    private function parseUserAgent(string $ua): array
    {
        $ua = strtolower($ua);

        // Device type
        $deviceType = 'desktop';
        if (preg_match('/(tablet|ipad|playbook|silk)/', $ua)) {
            $deviceType = 'tablet';
        } elseif (preg_match('/(mobile|android|iphone|ipod|blackberry|windows phone|opera mini|iemobile)/', $ua)) {
            $deviceType = 'mobile';
        }

        // Browser
        $browser = 'Unknown';
        if (str_contains($ua, 'edg/') || str_contains($ua, 'edge/')) {
            $browser = 'Edge';
        } elseif (str_contains($ua, 'opr/') || str_contains($ua, 'opera')) {
            $browser = 'Opera';
        } elseif (str_contains($ua, 'chrome') || str_contains($ua, 'crios')) {
            $browser = 'Chrome';
        } elseif (str_contains($ua, 'firefox') || str_contains($ua, 'fxios')) {
            $browser = 'Firefox';
        } elseif (str_contains($ua, 'safari')) {
            $browser = 'Safari';
        } elseif (str_contains($ua, 'msie') || str_contains($ua, 'trident')) {
            $browser = 'IE';
        }

        // OS
        $os = 'Unknown';
        if (str_contains($ua, 'windows nt')) {
            $os = 'Windows';
        } elseif (str_contains($ua, 'mac os x') || str_contains($ua, 'macos')) {
            $os = 'macOS';
        } elseif (str_contains($ua, 'android')) {
            $os = 'Android';
        } elseif (str_contains($ua, 'iphone') || str_contains($ua, 'ipad') || str_contains($ua, 'ipod')) {
            $os = 'iOS';
        } elseif (str_contains($ua, 'linux')) {
            $os = 'Linux';
        }

        return compact('device_type', 'browser', 'os');
    }

    /**
     * Clean up old sessions:
     * - Set is_online=0 where last_activity > 5 min ago
     * - Delete sessions older than 24 hours
     */
    public function cleanup(): void
    {
        try {
            if (!Schema::hasTable('user_sessions')) {
                return;
            }

            // Mark offline
            DB::table('user_sessions')
                ->where('last_activity', '<', now()->subMinutes(5))
                ->update(['is_online' => 0]);

            // Delete very old sessions
            DB::table('user_sessions')
                ->where('last_activity', '<', now()->subHours(24))
                ->delete();
        } catch (\Throwable $e) {
            // Silently fail
        }
    }
}

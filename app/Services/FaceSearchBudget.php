<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\FaceSearchLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * FaceSearchBudget
 *
 * All cost/abuse controls for the public face-search endpoint live here so
 * FaceSearchController stays a thin coordinator. One service, two jobs:
 *
 *   1. preflight() — called BEFORE any AWS call. Enforces every cap (global
 *      kill-switch, monthly ceiling, per-event/user/IP daily quotas, fallback-
 *      path photo cap) and returns either `['allowed' => true]` or
 *      `['allowed' => false, 'status' => ..., 'message' => ...]` so the caller
 *      can bail out with a clean 429 response.
 *
 *   2. cacheLookup() / cacheStore() — dedup repeat searches by sha256 of the
 *      selfie bytes. Repeat hits cost $0 instead of the usual ~$0.002.
 *
 *   3. logResult() — append a single row to face_search_logs. Keeps the
 *      schema centralised in one call site (easier to migrate later).
 *
 * All caps live in AppSetting so admins can tune them without a deploy. Any
 * cap set to '0' disables that specific cap — useful for lifting the global
 * ceiling during a known-safe event burst.
 */
class FaceSearchBudget
{
    /**
     * Stage 1 — called BEFORE AWS. Returns the first cap that blocks the
     * request, or `['allowed' => true]` when every cap has headroom.
     *
     * Short-circuits at the first failure so we don't waste queries. Order
     * matters:
     *   kill-switch → monthly global → per-user → per-IP → per-event → OK.
     * Cheapest/broadest caps first so the hot path of an allowed request
     * runs through the fewest queries.
     */
    public function preflight(Request $request, int $eventId): array
    {
        // 1. Global kill switch — if '0' nothing else matters.
        if (AppSetting::get('face_search_enabled_globally', '1') !== '1') {
            return $this->deny('denied_kill_switch',
                'ระบบค้นหาด้วยใบหน้าถูกปิดใช้งานชั่วคราว กรุณาลองใหม่ภายหลัง');
        }

        // 2. Global monthly ceiling. Uses a 30-day rolling window keyed off
        //    `created_at` — not calendar month — because attackers rolling
        //    past midnight UTC shouldn't get a reset they didn't earn.
        $monthlyCap = (int) AppSetting::get('face_search_monthly_global_cap', '10000');
        if ($monthlyCap > 0) {
            $used = $this->countSinceCached('global_monthly', null, now()->subDays(30), 60);
            if ($used >= $monthlyCap) {
                return $this->deny('denied_monthly_global',
                    'ระบบถึงโควต้าการใช้งานประจำเดือนแล้ว กรุณาติดต่อผู้ดูแลระบบ');
            }
        }

        // 3. Per-user daily cap (for logged-in users) — prevents a single
        //    account from burning the entire per-IP budget across VPN hops.
        $userId = Auth::id();
        $userCap = (int) AppSetting::get('face_search_daily_cap_per_user', '50');
        if ($userId && $userCap > 0) {
            $used = $this->countToday('user', $userId);
            if ($used >= $userCap) {
                return $this->deny('denied_daily_cap_user',
                    "คุณค้นหาด้วยใบหน้าวันนี้ถึงโควต้าแล้ว ({$userCap} ครั้ง/วัน) กรุณาลองใหม่พรุ่งนี้");
            }
        }

        // 4. Per-IP daily cap (always enforced — catches guests and abusive
        //    authenticated users on the same network).
        $ipCap = (int) AppSetting::get('face_search_daily_cap_per_ip', '100');
        if ($ipCap > 0) {
            $used = $this->countToday('ip', $request->ip());
            if ($used >= $ipCap) {
                return $this->deny('denied_daily_cap_ip',
                    "IP นี้ค้นหาด้วยใบหน้าวันนี้ถึงโควต้าแล้ว ({$ipCap} ครั้ง/วัน) กรุณาลองใหม่พรุ่งนี้");
            }
        }

        // 5. Per-event daily cap (catches the "one popular event gets
        //    scraped" case — even when no single IP hits its limit, the
        //    event itself might be bleeding money).
        $eventCap = (int) AppSetting::get('face_search_daily_cap_per_event', '500');
        if ($eventCap > 0) {
            $used = $this->countToday('event', $eventId);
            if ($used >= $eventCap) {
                return $this->deny('denied_daily_cap_event',
                    'งานนี้ถึงโควต้าการค้นหาประจำวันแล้ว กรุณาลองใหม่พรุ่งนี้');
            }
        }

        return ['allowed' => true];
    }

    /**
     * Stage 1b — called once we know the event photo count but still BEFORE
     * the expensive fallback path fires. The primary `searchFacesByImage`
     * path is a flat 1 API call regardless of photo count, but the fallback
     * `compareFaces` path is linear in photo count. Refuse the fallback
     * outright when the event is too large to cheaply compare.
     *
     * Returns `null` when the fallback is safe, or a deny payload when not.
     */
    public function shouldRefuseFallback(int $photoCount): ?array
    {
        $max = (int) AppSetting::get('face_search_fallback_max_photos', '20');
        if ($max <= 0) {
            // Admin explicitly disabled the cap — allow anything. Rarely
            // what you want, but respect the setting.
            return null;
        }

        if ($photoCount > $max) {
            return $this->deny('fallback_too_large',
                'รูปในงานยังไม่ได้ประมวลผลสำหรับค้นหาด้วยใบหน้า กรุณาติดต่อผู้ดูแลระบบเพื่อเริ่มประมวลผล');
        }

        return null;
    }

    /**
     * Stage 2 — cache lookup.
     *
     * Cache key includes event_id so two different events with the same
     * selfie produce different results. TTL is read from AppSetting so
     * admins can widen/narrow the cache window live.
     *
     * Returns the previously-stored matches array, or null if this is a
     * cold lookup / cache disabled / cache miss.
     */
    public function cacheLookup(int $eventId, string $selfieHash): ?array
    {
        $ttl = (int) AppSetting::get('face_search_cache_ttl_minutes', '10');
        if ($ttl <= 0) {
            return null;
        }

        $key = $this->cacheKey($eventId, $selfieHash);
        $cached = Cache::get($key);
        return is_array($cached) ? $cached : null;
    }

    public function cacheStore(int $eventId, string $selfieHash, array $matches): void
    {
        $ttl = (int) AppSetting::get('face_search_cache_ttl_minutes', '10');
        if ($ttl <= 0) {
            return;
        }

        Cache::put(
            $this->cacheKey($eventId, $selfieHash),
            $matches,
            now()->addMinutes($ttl)
        );
    }

    /**
     * Stage 3 — write one append-only row to face_search_logs.
     *
     * We swallow failures: a logging error should never turn a successful
     * (or cleanly-denied) search into a 500. The table is an auxiliary
     * ledger — budgets are re-computed from it but the live search itself
     * does not depend on the write succeeding.
     */
    public function logResult(array $fields): void
    {
        try {
            FaceSearchLog::create(array_merge(
                ['created_at' => now()],
                $fields
            ));
        } catch (\Throwable $e) {
            Log::warning('FaceSearchBudget::logResult failed: ' . $e->getMessage());
        }
    }

    /**
     * Quick snapshot for the admin dashboard. Returned as a plain array so
     * the caller can shove it straight into a Blade view with no
     * ceremony. Uses a short TTL cache (60s) — admin hits this on every
     * page view, and the numbers don't need real-time accuracy.
     */
    public function snapshot(): array
    {
        return Cache::remember('face_search_budget_snapshot', 60, function () {
            $today = now()->startOfDay();
            $monthStart = now()->subDays(30);

            $byStatusToday = FaceSearchLog::where('created_at', '>=', $today)
                ->selectRaw('status, COUNT(*) as c')
                ->groupBy('status')
                ->pluck('c', 'status')
                ->toArray();

            $apiCallsToday = (int) FaceSearchLog::where('created_at', '>=', $today)->sum('api_calls');
            $apiCalls30d   = (int) FaceSearchLog::where('created_at', '>=', $monthStart)->sum('api_calls');

            $topEvents = FaceSearchLog::where('created_at', '>=', $today)
                ->whereNotNull('event_id')
                ->selectRaw('event_id, COUNT(*) as total, SUM(api_calls) as calls')
                ->groupBy('event_id')
                ->orderByDesc('total')
                ->limit(10)
                ->get();

            $topIps = FaceSearchLog::where('created_at', '>=', $today)
                ->selectRaw('ip_address, COUNT(*) as total, SUM(api_calls) as calls')
                ->groupBy('ip_address')
                ->orderByDesc('total')
                ->limit(10)
                ->get();

            // Rough cost estimate — AWS Rekognition is ~$0.001 per Image API
            // call for the first 1M per month. Good enough for a dashboard;
            // the real number comes from AWS Cost Explorer.
            $usdPerCall    = 0.001;
            $estCostToday  = round($apiCallsToday * $usdPerCall, 2);
            $estCost30d    = round($apiCalls30d   * $usdPerCall, 2);

            return [
                'today'       => $byStatusToday,
                'api_today'   => $apiCallsToday,
                'api_30d'     => $apiCalls30d,
                'cost_today'  => $estCostToday,
                'cost_30d'    => $estCost30d,
                'top_events'  => $topEvents,
                'top_ips'     => $topIps,
                'computed_at' => now()->toIso8601String(),
            ];
        });
    }

    // ─────────────────────────────────────────────────────────────────────
    // Internal helpers
    // ─────────────────────────────────────────────────────────────────────

    /** Shape the deny response — always include both status + Thai message. */
    private function deny(string $status, string $message): array
    {
        return [
            'allowed' => false,
            'status'  => $status,
            'message' => $message,
        ];
    }

    /**
     * Count rows for a given dimension since start-of-day. We count ANY row
     * (including denied and cache_hit) because the caps protect against the
     * user/IP/event themselves generating load — a denied attempt is already
     * "they tried", and cache_hit attempts represent users who would have
     * tried again anyway.
     */
    private function countToday(string $dimension, $value): int
    {
        $q = FaceSearchLog::where('created_at', '>=', now()->startOfDay());
        match ($dimension) {
            'event' => $q->where('event_id', $value),
            'user'  => $q->where('user_id',  $value),
            'ip'    => $q->where('ip_address', $value),
        };
        return (int) $q->count();
    }

    /**
     * Cached row-count since $since — used for the monthly global cap to
     * avoid running a 30-day-range aggregate on every single request. TTL
     * is short (60s) so admins see near-real-time numbers on the dashboard
     * but the hot path runs off a cache hit 99% of the time.
     */
    private function countSinceCached(string $scopeTag, $value, $since, int $ttlSeconds): int
    {
        $key = "face_search_count:{$scopeTag}:" . md5((string) $value . '|' . $since->toIso8601String());
        return (int) Cache::remember($key, $ttlSeconds, function () use ($scopeTag, $value, $since) {
            $q = FaceSearchLog::where('created_at', '>=', $since);
            if ($scopeTag === 'event') $q->where('event_id', $value);
            if ($scopeTag === 'user')  $q->where('user_id', $value);
            if ($scopeTag === 'ip')    $q->where('ip_address', $value);
            return (int) $q->count();
        });
    }

    private function cacheKey(int $eventId, string $selfieHash): string
    {
        return "face_search_result:{$eventId}:{$selfieHash}";
    }
}

<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\Event;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Per-photographer retention status surface.
 *
 * Powers the "events expiring soon" widget on the photographer dashboard.
 * Returns:
 *   • Events scheduled for purge in the next N days
 *   • Days remaining for each
 *   • A summary count of "expiring soon" by bucket (today, this week, this month)
 *
 * Memoisation
 * ───────────
 * Per-photographer cache, 60s TTL. Short because the cron runs nightly and
 * the dashboard pull is the only consumer — we want fresh numbers shortly
 * after each purge round, not stale ones for 5 min.
 */
class PhotographerRetentionService
{
    private const CACHE_PREFIX = 'photographer_retention_status';
    private const CACHE_TTL = 60;

    /**
     * Get the retention status for a photographer's events.
     *
     * @return array{
     *   tier: string,
     *   tier_label: string,
     *   retention_days: int,
     *   retention_mode: string,
     *   expiring_today: int,
     *   expiring_this_week: int,
     *   expiring_this_month: int,
     *   upcoming: array<int, array{id:int, name:string, eta:string, days_left:int, photo_count:int}>,
     *   already_archived: int,
     * }
     */
    public function statusFor(int $photographerUserId, int $lookaheadDays = 30, int $limit = 10): array
    {
        return Cache::remember(
            self::CACHE_PREFIX . ":u={$photographerUserId}:la={$lookaheadDays}:lim={$limit}",
            self::CACHE_TTL,
            function () use ($photographerUserId, $lookaheadDays, $limit) {
                return $this->compute($photographerUserId, $lookaheadDays, $limit);
            }
        );
    }

    public function flushCache(int $photographerUserId): void
    {
        // Clear all variants we might have cached for this photographer.
        // Cheap: we know the prefix + we know the most common limits used.
        foreach ([7, 14, 30, 60, 90] as $la) {
            foreach ([5, 10, 20, 50] as $lim) {
                Cache::forget(self::CACHE_PREFIX . ":u={$photographerUserId}:la={$la}:lim={$lim}");
            }
        }
    }

    private function compute(int $photographerUserId, int $lookaheadDays, int $limit): array
    {
        $now = now();
        $cutoff = $now->copy()->addDays($lookaheadDays);

        // Pull the photographer's NON-archived, non-exempt events. Filter
        // by ETA in PHP because effectiveDeleteAt() reads AppSettings +
        // does match arithmetic that's not trivially expressible in SQL.
        $events = Event::query()
            ->where('photographer_id', $photographerUserId)
            ->where('auto_delete_exempt', false)
            ->whereNull('originals_purged_at')
            ->orderBy('shoot_date')
            ->limit(200) // safety cap — most photographers have <50 active events
            ->get(['id', 'name', 'shoot_date', 'created_at', 'auto_delete_at', 'retention_days_override', 'photographer_id']);

        $alreadyArchived = Event::query()
            ->where('photographer_id', $photographerUserId)
            ->whereNotNull('originals_purged_at')
            ->count();

        $expiringToday      = 0;
        $expiringThisWeek   = 0;
        $expiringThisMonth  = 0;
        $upcoming           = [];

        // Use the first event (or a synthesised placeholder) to read tier
        // info — same per-photographer answer regardless of event row.
        $sampleEvent = $events->first() ?: new Event(['photographer_id' => $photographerUserId]);
        $tier         = $sampleEvent->effectiveRetentionTier();
        $retentionDays = $sampleEvent->tierRetentionDays();
        $retentionMode = $sampleEvent->tierRetentionMode();

        foreach ($events as $ev) {
            $eta = $ev->effectiveDeleteAt();
            if (!$eta) continue;
            if ($eta->gt($cutoff)) continue;

            $daysLeft = (int) $now->diffInDays($eta, false);

            if ($daysLeft <= 0) {
                $expiringToday++;
            } elseif ($daysLeft <= 7) {
                $expiringThisWeek++;
            } elseif ($daysLeft <= 30) {
                $expiringThisMonth++;
            }

            if (count($upcoming) < $limit) {
                $upcoming[] = [
                    'id'          => (int) $ev->id,
                    'name'        => (string) $ev->name,
                    'eta'         => $eta->format('Y-m-d'),
                    'days_left'   => max(0, $daysLeft),
                    'photo_count' => 0, // skip per-event count query for performance
                ];
            }
        }

        // Annotate the top N with photo counts (one extra query for the
        // limited set, not all 200 events).
        if (!empty($upcoming)) {
            $ids = array_column($upcoming, 'id');
            $counts = \Illuminate\Support\Facades\DB::table('event_photos')
                ->whereIn('event_id', $ids)
                ->whereNotIn('status', ['deleted', 'removed'])
                ->select('event_id', \Illuminate\Support\Facades\DB::raw('COUNT(*) AS c'))
                ->groupBy('event_id')
                ->pluck('c', 'event_id');
            foreach ($upcoming as &$u) {
                $u['photo_count'] = (int) ($counts[$u['id']] ?? 0);
            }
            unset($u);
        }

        return [
            'tier'                => $tier,
            'tier_label'          => $this->tierLabel($tier),
            'retention_days'      => $retentionDays,
            'retention_mode'      => $retentionMode,
            'expiring_today'      => $expiringToday,
            'expiring_this_week'  => $expiringThisWeek,
            'expiring_this_month' => $expiringThisMonth,
            'upcoming'            => $upcoming,
            'already_archived'    => $alreadyArchived,
        ];
    }

    private function tierLabel(string $tier): string
    {
        return match ($tier) {
            \App\Models\PhotographerProfile::TIER_PRO     => 'Pro',
            \App\Models\PhotographerProfile::TIER_SELLER  => 'Seller',
            \App\Models\PhotographerProfile::TIER_CREATOR => 'Free (Creator)',
            default => 'ไม่ระบุ',
        };
    }
}

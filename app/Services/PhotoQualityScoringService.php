<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventPhoto;
use Illuminate\Support\Facades\DB;

/**
 * Computes a composite 0–100 quality score for event photos and assigns
 * a dense rank within each event (1 = best). Runs cheaply with zero ML:
 * every signal is already sitting in the database from uploads, moderation,
 * and the download-token system.
 *
 * Weights (sum = 100):
 *   technical   35   — resolution + file size + aspect ratio
 *   moderation  25   — moderation_score clamped into [0,1]
 *   engagement  30   — aggregate download_token downloads (log-scaled per event)
 *   freshness   10   — newer photos get a light bump (decays over 30 days)
 */
class PhotoQualityScoringService
{
    private const W_TECHNICAL  = 35;
    private const W_MODERATION = 25;
    private const W_ENGAGEMENT = 30;
    private const W_FRESHNESS  = 10;

    /**
     * Score & rank every photo inside one event. Returns count scored.
     */
    public function scoreEvent(Event|int $event): int
    {
        $eventId = $event instanceof Event ? $event->id : (int) $event;

        $photos = EventPhoto::where('event_id', $eventId)
            ->where('status', '!=', 'deleted')
            ->get();

        if ($photos->isEmpty()) return 0;

        // Pull engagement counts for photos in this event in one query
        $dlCounts = DB::table('download_tokens')
            ->select('photo_id', DB::raw('COALESCE(SUM(download_count), 0) as n'))
            ->whereIn('photo_id', $photos->pluck('id'))
            ->groupBy('photo_id')
            ->pluck('n', 'photo_id');

        $maxDl = max(1, (int) ($dlCounts->max() ?? 0));
        $logMax = log1p($maxDl);
        $now = now();

        // Compute raw scores
        $scored = $photos->map(function (EventPhoto $p) use ($dlCounts, $logMax, $now) {
            $signals = [];

            // — Technical (resolution + file size + aspect ratio) —
            $mp = (($p->width ?? 0) * ($p->height ?? 0)) / 1_000_000;    // megapixels
            $mpScore = min(1.0, $mp / 24.0);                             // cap at 24 MP
            $sizeMb = (($p->file_size ?? 0) / 1_048_576);
            $sizeScore = min(1.0, $sizeMb / 8.0);                        // cap at 8 MB
            $ar = ($p->height ?? 0) > 0 ? ($p->width / $p->height) : 0;
            // favor 3:2 (1.5) and 4:3 (1.33) within a tolerance
            $arScore = 0.5;
            if ($ar > 0) {
                $near = min(abs($ar - 1.5), abs($ar - 1.333), abs($ar - (1/1.5)), abs($ar - (1/1.333)));
                $arScore = max(0.0, 1.0 - ($near / 0.5));
            }
            $technical = ($mpScore * 0.55 + $sizeScore * 0.25 + $arScore * 0.20);
            $signals['technical'] = [
                'mp'         => round($mp, 2),
                'size_mb'    => round($sizeMb, 2),
                'aspect'     => round($ar, 3),
                'mp_score'   => round($mpScore, 3),
                'size_score' => round($sizeScore, 3),
                'ar_score'   => round($arScore, 3),
                'composite'  => round($technical, 3),
            ];

            // — Moderation —
            $mod = (float) ($p->moderation_score ?? 0.85);
            // flagged/rejected photos are hard-capped
            if ($p->moderation_status === 'rejected') $mod = 0.10;
            elseif ($p->moderation_status === 'flagged') $mod = min($mod, 0.50);
            $signals['moderation'] = [
                'score'  => round($mod, 3),
                'status' => $p->moderation_status ?? 'approved',
            ];

            // — Engagement (log-scaled) —
            $dl = (int) ($dlCounts[$p->id] ?? 0);
            $engagement = $logMax > 0 ? log1p($dl) / $logMax : 0;
            $signals['engagement'] = [
                'downloads' => $dl,
                'score'     => round($engagement, 3),
            ];

            // — Freshness (newer = better, decays 30d) —
            $ageDays = $p->created_at ? max(0, $now->diffInDays($p->created_at)) : 30;
            $fresh = max(0.0, 1.0 - ($ageDays / 30));
            $signals['freshness'] = [
                'age_days' => $ageDays,
                'score'    => round($fresh, 3),
            ];

            // Composite 0-100
            $composite = ($technical * self::W_TECHNICAL)
                       + ($mod        * self::W_MODERATION)
                       + ($engagement * self::W_ENGAGEMENT)
                       + ($fresh      * self::W_FRESHNESS);

            return [
                'photo'   => $p,
                'score'   => round($composite, 2),
                'signals' => $signals,
            ];
        })
        ->sortByDesc('score')
        ->values();

        // Persist scores + dense rank
        DB::transaction(function () use ($scored) {
            $rank = 1;
            foreach ($scored as $row) {
                /** @var EventPhoto $p */
                $p = $row['photo'];
                $p->quality_score     = $row['score'];
                $p->quality_signals   = $row['signals'];
                $p->quality_scored_at = now();
                $p->rank_position     = $rank++;
                $p->saveQuietly(); // avoid cache invalidation churn
            }
        });

        return $scored->count();
    }

    /**
     * Re-score every active event. Returns array [events, photos_scored].
     */
    public function scoreAllEvents(?callable $progress = null): array
    {
        $eventIds = Event::query()
            ->where('status', '!=', 'archived')
            ->pluck('id');

        $photosTotal = 0;
        foreach ($eventIds as $i => $eid) {
            $n = $this->scoreEvent((int) $eid);
            $photosTotal += $n;
            if ($progress) $progress($i + 1, count($eventIds), (int) $eid, $n);
        }

        return [
            'events_scored' => count($eventIds),
            'photos_scored' => $photosTotal,
        ];
    }

    /**
     * Admin dashboard: list top-N photos across all events, grouped by event.
     */
    public function topByEvent(int $eventId, int $limit = 24)
    {
        return EventPhoto::where('event_id', $eventId)
            ->whereNotNull('quality_score')
            ->orderBy('rank_position')
            ->limit($limit)
            ->get();
    }

    /**
     * Photos flagged as "low-quality" candidates for cleanup (bottom decile).
     */
    public function lowQualityCandidates(int $eventId, float $threshold = 30.0)
    {
        return EventPhoto::where('event_id', $eventId)
            ->whereNotNull('quality_score')
            ->where('quality_score', '<', $threshold)
            ->orderBy('quality_score')
            ->get();
    }

    /**
     * KPI for admin dashboard.
     */
    public function kpis(): array
    {
        $row = DB::table('event_photos')
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN quality_score IS NOT NULL THEN 1 ELSE 0 END) as scored,
                AVG(quality_score) as avg_score,
                SUM(CASE WHEN quality_score >= 75 THEN 1 ELSE 0 END) as high,
                SUM(CASE WHEN quality_score BETWEEN 40 AND 75 THEN 1 ELSE 0 END) as mid,
                SUM(CASE WHEN quality_score < 40 THEN 1 ELSE 0 END) as low
            ')
            ->first();

        return [
            'total'     => (int) ($row->total ?? 0),
            'scored'    => (int) ($row->scored ?? 0),
            'avg_score' => round((float) ($row->avg_score ?? 0), 2),
            'high'      => (int) ($row->high ?? 0),
            'mid'       => (int) ($row->mid ?? 0),
            'low'       => (int) ($row->low ?? 0),
        ];
    }
}

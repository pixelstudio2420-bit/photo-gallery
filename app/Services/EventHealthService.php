<?php

namespace App\Services;

use App\Models\Event;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Computes a composite health score 0-100 per event.
 * Dimensions:
 *  - Moderation: % photos moderation_status=approved (vs flagged/rejected/pending)
 *  - Face coverage: % photos with rekognition_face_id set (only matters if face_search_enabled)
 *  - Photo quality: avg file size (proxy), % photos with dimensions filled in
 *  - Engagement: orders_count per 100 photos, downloads per 100 photos
 *  - Freshness: penalty if shoot_date is much older than released/sold (but not if is_portfolio)
 */
class EventHealthService
{
    /** Max photos to scan per event for deep metrics (for speed on big events). */
    const SAMPLE_SIZE = 500;

    /**
     * Score + breakdown for a single event.
     */
    public function score(Event $event): array
    {
        $photoTable = Schema::hasTable('event_photos') ? 'event_photos' : null;

        // event_photos stores byte count in `file_size` (not `size_bytes` —
        // that's the user_files convention). We alias it to size_bytes here
        // so the rest of this method can stay column-name-agnostic.
        $photos = $photoTable
            ? DB::table($photoTable)
                ->where('event_id', $event->id)
                ->where('status', 'active')
                ->select(['moderation_status', 'rekognition_face_id', 'file_size as size_bytes', 'width', 'height'])
                ->limit(self::SAMPLE_SIZE)
                ->get()
            : collect();

        $photoCount = $photos->count();

        // ── Moderation ────────────────────────────────────────────
        $approved = $photos->where('moderation_status', 'approved')->count();
        $flagged  = $photos->whereIn('moderation_status', ['flagged', 'pending'])->count();
        $rejected = $photos->where('moderation_status', 'rejected')->count();
        $modScore = $photoCount > 0 ? round(($approved / $photoCount) * 100, 1) : 100;

        // ── Face coverage (only if enabled) ───────────────────────
        $faceScore = null;
        if ($event->face_search_enabled ?? false) {
            $withFaceId = $photos->whereNotNull('rekognition_face_id')->count();
            $faceScore = $photoCount > 0 ? round(($withFaceId / $photoCount) * 100, 1) : 0;
        }

        // ── Technical quality ─────────────────────────────────────
        $withDims = $photos->filter(fn ($p) => ($p->width ?? 0) > 0 && ($p->height ?? 0) > 0)->count();
        $dimsScore = $photoCount > 0 ? round(($withDims / $photoCount) * 100, 1) : 0;
        $avgSizeMb = $photoCount > 0 ? round($photos->avg('size_bytes') / 1048576, 2) : 0;

        // ── Engagement ────────────────────────────────────────────
        $ordersCount = Schema::hasTable('orders')
            ? (int) DB::table('orders')->where('event_id', $event->id)->where('status', 'paid')->count()
            : 0;
        $downloadsCount = Schema::hasTable('download_logs')
            ? (int) DB::table('download_logs')->where('event_id', $event->id)->count()
            : 0;

        $totalPhotosInEvent = $photoTable
            ? (int) DB::table($photoTable)->where('event_id', $event->id)->where('status', 'active')->count()
            : $photoCount;

        $ordersPer100 = $totalPhotosInEvent > 0 ? round($ordersCount / $totalPhotosInEvent * 100, 2) : 0;
        $dlsPer100    = $totalPhotosInEvent > 0 ? round($downloadsCount / $totalPhotosInEvent * 100, 2) : 0;

        // ── Composite score: weighted average ─────────────────────
        //     moderation 35% + dims 15% + face 15% (if enabled, else 0 redistributed) + engagement 35%
        $engagementScore = min(100, $ordersPer100 * 10 + $dlsPer100 * 2); // saturates easily on hot events

        if ($faceScore !== null) {
            $composite = ($modScore * 0.35) + ($dimsScore * 0.15) + ($faceScore * 0.15) + ($engagementScore * 0.35);
        } else {
            // Redistribute the 15% face weight to moderation + engagement
            $composite = ($modScore * 0.42) + ($dimsScore * 0.18) + ($engagementScore * 0.40);
        }
        $composite = round($composite, 1);

        // ── Grade A-F ─────────────────────────────────────────────
        $grade = match (true) {
            $composite >= 90 => 'A',
            $composite >= 80 => 'B',
            $composite >= 65 => 'C',
            $composite >= 50 => 'D',
            default          => 'F',
        };

        $issues = [];
        if ($photoCount === 0) $issues[] = 'ยังไม่มีรูป';
        if ($modScore < 70) $issues[] = 'รูปที่ผ่าน moderation น้อย (' . $modScore . '%)';
        if ($faceScore !== null && $faceScore < 60 && $totalPhotosInEvent > 0) $issues[] = 'รูปมี face_id ต่ำ (' . $faceScore . '%)';
        if ($dimsScore < 80 && $totalPhotosInEvent > 0) $issues[] = 'รูปไม่มี dimensions (' . $dimsScore . '%)';
        if ($ordersCount === 0 && $totalPhotosInEvent > 20) $issues[] = 'ยังไม่มียอดขาย';
        if ($avgSizeMb < 1 && $totalPhotosInEvent > 0) $issues[] = 'ไฟล์เล็กผิดปกติ (' . $avgSizeMb . ' MB)';
        if ($avgSizeMb > 8) $issues[] = 'ไฟล์ใหญ่มาก (' . $avgSizeMb . ' MB) — อาจช้าบน mobile';

        return [
            'event_id'          => $event->id,
            'name'              => $event->name,
            'shoot_date'        => $event->shoot_date?->format('Y-m-d'),
            'status'            => $event->status,
            'composite'         => $composite,
            'grade'             => $grade,
            'photo_count'       => $totalPhotosInEvent,
            'moderation'        => [
                'score'    => $modScore,
                'approved' => $approved,
                'flagged'  => $flagged,
                'rejected' => $rejected,
            ],
            'face_coverage'     => $faceScore,
            'face_enabled'      => (bool) ($event->face_search_enabled ?? false),
            'dimensions_pct'    => $dimsScore,
            'avg_size_mb'       => $avgSizeMb,
            'orders'            => $ordersCount,
            'downloads'         => $downloadsCount,
            'orders_per_100'    => $ordersPer100,
            'downloads_per_100' => $dlsPer100,
            'engagement_score'  => round($engagementScore, 1),
            'issues'            => $issues,
        ];
    }

    /**
     * Scoreboard across many events.
     */
    public function scoreboard(int $limit = 50, ?string $status = null): array
    {
        $q = Event::query();
        if ($status) $q->where('status', $status);
        $events = $q->orderByDesc('shoot_date')->limit($limit)->get();

        $rows = [];
        foreach ($events as $e) {
            $rows[] = $this->score($e);
        }

        usort($rows, fn ($a, $b) => $b['composite'] <=> $a['composite']);

        return $rows;
    }
}

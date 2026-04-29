<?php

namespace App\Services;

use App\Models\Event;

/**
 * Best Shot — composite ranking of photos in an event.
 *
 * Reads existing AI signals on event_photos and computes a 0..100
 * "best shot" score. Photos with the top scores are flagged so
 * the photographer can quickly pick highlights for the cover/lookbook.
 *
 * Inputs (whichever exist):
 *   - quality_score      (0..100, set by QualityFilterAi)
 *   - is_blurry          (boolean — heavy negative)
 *   - face_count         (set by FaceSearchAiPhotographer — more faces ≈ group photo)
 *   - phash              (used to penalise duplicates of higher-scoring shots)
 *   - moderation_score   (existing — content quality from earlier pipeline)
 *
 * Output:
 *   - event_photos.best_shot_score (0..100)
 *   - event_photos.rank_position   (top-N photos numbered 1..N)
 *
 * Pure heuristic — no external API.
 */
class BestShotAi
{
    public const TOP_N = 10; // photos to mark with rank_position

    public function run(Event $event): array
    {
        $photos = $event->photos()
            ->where('status', 'active')
            ->get();

        if ($photos->isEmpty()) {
            return ['processed' => 0, 'top_shots' => []];
        }

        // Score each photo
        $scored = $photos->map(function ($p) {
            $score = 50; // baseline
            $q = (int) ($p->quality_score ?? 50);

            // Quality is the dominant signal (weighted 0.7)
            $score = $q * 0.7 + 30 * 0.3;

            // Blurry photos are heavily penalised
            if ($p->is_blurry) $score -= 30;

            // Faces present → small boost (group/portrait shots are valuable)
            if ($p->face_count > 0) {
                $score += min(15, $p->face_count * 3);
            }

            // Higher resolution → small boost
            $megapixels = ($p->width * $p->height) / 1_000_000;
            if ($megapixels >= 6) $score += 5;

            // Existing moderation score (from earlier moderation pipeline)
            if (!is_null($p->moderation_score)) {
                $score = ($score + (int) $p->moderation_score) / 2;
            }

            return ['photo' => $p, 'score' => max(0, min(100, (int) round($score)))];
        });

        // Sort highest first
        $sorted = $scored->sortByDesc('score')->values();

        // Penalise duplicates — for each pHash group, only the highest-
        // scoring photo keeps its rank. Others get the score capped to 50.
        $seenHashes = [];
        $sorted = $sorted->map(function ($row) use (&$seenHashes) {
            $hash = $row['photo']->phash;
            if ($hash) {
                if (isset($seenHashes[$hash])) {
                    $row['score'] = min($row['score'], 50);
                } else {
                    $seenHashes[$hash] = true;
                }
            }
            return $row;
        })->sortByDesc('score')->values();

        // Persist scores + top-N ranks
        $topShotIds = [];
        foreach ($sorted as $i => $row) {
            $rank = ($i < self::TOP_N) ? ($i + 1) : null;
            $row['photo']->forceFill([
                'best_shot_score' => $row['score'],
                'rank_position'   => $rank,
            ])->save();
            if ($rank) $topShotIds[] = $row['photo']->id;
        }

        return [
            'processed' => $scored->count(),
            'top_shots' => $topShotIds,
        ];
    }
}

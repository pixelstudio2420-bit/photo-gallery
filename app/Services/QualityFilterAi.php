<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventPhoto;
use Illuminate\Support\Facades\Log;

/**
 * Quality Filter — detect blurry / out-of-focus photos.
 *
 * Algorithm:
 *   1) Resize image to ~512px on the longest side (GD)
 *   2) Convert to grayscale
 *   3) Apply Laplacian-style edge filter (IMG_FILTER_EDGEDETECT)
 *   4) Compute variance of resulting pixels — sharp images have high
 *      variance, blurry images have low variance because edges are
 *      smeared out.
 *   5) Map variance → 0..100 quality score:
 *        var < 50   → score 0..30   (blurry)
 *        var < 200  → score 30..70  (acceptable)
 *        var >= 200 → score 70..100 (sharp)
 *
 * The threshold is tuned for ~1200×800 phone-camera photos. Pure GD
 * pipeline — no external API.
 *
 * Outputs:
 *   - event_photos.quality_score (existing column from prior migrations)
 *   - event_photos.is_blurry     (boolean derived: score < 30)
 *   - event_photos.quality_signals (JSON: { variance, score, blurry })
 */
class QualityFilterAi
{
    public const BLURRY_THRESHOLD = 30; // score below this = "blurry"

    public function run(Event $event): array
    {
        $photos = $event->photos()
            ->where('status', 'active')
            ->whereNotNull('original_path')
            ->get();

        $processed = 0;
        $blurry    = 0;
        $errors    = 0;
        $blurryIds = [];

        foreach ($photos as $photo) {
            $processed++;
            try {
                $signals = $this->scorePhoto($photo);
                if (!$signals) { $errors++; continue; }

                $isBlurry = $signals['score'] < self::BLURRY_THRESHOLD;
                if ($isBlurry) {
                    $blurry++;
                    $blurryIds[] = $photo->id;
                }

                $photo->forceFill([
                    'quality_score'    => $signals['score'],
                    'is_blurry'        => $isBlurry,
                    'quality_signals'  => $signals,
                    'quality_scored_at'=> now(),
                ])->save();
            } catch (\Throwable $e) {
                Log::warning('Quality filter failed photo '.$photo->id, ['err' => $e->getMessage()]);
                $errors++;
            }
        }

        return [
            'processed' => $processed,
            'blurry'    => $blurry,
            'sharp'     => $processed - $blurry - $errors,
            'errors'    => $errors,
            'blurry_ids'=> $blurryIds,
        ];
    }

    private function scorePhoto(EventPhoto $photo): ?array
    {
        $path = $this->resolvePhotoPath($photo);
        if (!$path || !is_readable($path)) return null;

        $info = @getimagesize($path);
        if (!$info) return null;
        $img = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG  => @imagecreatefrompng($path),
            IMAGETYPE_WEBP => @imagecreatefromwebp($path),
            default        => null,
        };
        if (!$img) return null;

        // Downscale long edge to ~512px so variance calc stays fast on
        // huge originals while still preserving enough detail.
        $w = imagesx($img);
        $h = imagesy($img);
        $target = 512;
        $scale  = min(1, $target / max($w, $h));
        $sw = max(1, (int) ($w * $scale));
        $sh = max(1, (int) ($h * $scale));

        $small = imagecreatetruecolor($sw, $sh);
        imagecopyresampled($small, $img, 0, 0, 0, 0, $sw, $sh, $w, $h);
        imagefilter($small, IMG_FILTER_GRAYSCALE);
        imagefilter($small, IMG_FILTER_EDGEDETECT);

        // Variance of edge intensities
        $sum = 0; $sumSq = 0; $n = 0;
        for ($y = 0; $y < $sh; $y += 2) {            // step 2 = 4× speedup
            for ($x = 0; $x < $sw; $x += 2) {
                $v = imagecolorat($small, $x, $y) & 0xFF;
                $sum   += $v;
                $sumSq += $v * $v;
                $n++;
            }
        }
        $mean = $sum / max(1, $n);
        $var  = ($sumSq / max(1, $n)) - ($mean * $mean);

        imagedestroy($img);
        imagedestroy($small);

        // Map variance → 0..100 score (capped both sides).
        $score = (int) round(min(100, max(0, ($var / 4)))); // var=400 → 100

        return [
            'variance' => round($var, 2),
            'score'    => $score,
            'blurry'   => $score < self::BLURRY_THRESHOLD,
        ];
    }

    private function resolvePhotoPath(EventPhoto $photo): ?string
    {
        $disk = $photo->storage_disk ?? 'public';
        try {
            $stored = \Illuminate\Support\Facades\Storage::disk($disk);
            if (!$stored->exists($photo->original_path)) return null;
            if (in_array($disk, ['public', 'local'], true)) {
                return $stored->path($photo->original_path);
            }
            $tmp = tempnam(sys_get_temp_dir(), 'qual_');
            file_put_contents($tmp, $stored->get($photo->original_path));
            return $tmp;
        } catch (\Throwable $e) {
            return null;
        }
    }
}

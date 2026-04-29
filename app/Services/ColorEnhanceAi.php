<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventPhoto;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Color Enhance — auto-adjust contrast/saturation/brightness on a copy.
 *
 * Algorithm (pure GD, no external API):
 *   1) Load original
 *   2) Apply IMG_FILTER_CONTRAST (-5)        → mild contrast pop
 *   3) Apply IMG_FILTER_BRIGHTNESS (+8)      → lift shadows slightly
 *   4) Apply IMG_FILTER_COLORIZE (saturation boost via channel scaling)
 *   5) Save to <originalDir>/enhanced/<filename>
 *   6) Stamp event_photos.color_enhanced_path
 *
 * The original is NEVER modified — the enhanced image lives at a
 * separate path so the photographer can A/B compare and revert.
 *
 * GD's filter set is limited but produces a reasonable "warm pop" look
 * suitable for event/portrait gallery use. For pro-grade adjustments
 * the photographer would still use Lightroom — this is a one-click
 * "make my batch look nicer" tool.
 */
class ColorEnhanceAi
{
    public function run(Event $event): array
    {
        $photos = $event->photos()
            ->where('status', 'active')
            ->whereNull('color_enhanced_path')
            ->get();

        $processed = 0;
        $enhanced  = 0;
        $errors    = 0;

        foreach ($photos as $photo) {
            $processed++;
            try {
                $newPath = $this->enhanceOne($photo);
                if ($newPath) {
                    $photo->forceFill(['color_enhanced_path' => $newPath])->save();
                    $enhanced++;
                }
            } catch (\Throwable $e) {
                Log::warning('Color enhance failed for photo '.$photo->id, ['err' => $e->getMessage()]);
                $errors++;
            }
        }

        return [
            'processed' => $processed,
            'enhanced'  => $enhanced,
            'errors'    => $errors,
        ];
    }

    private function enhanceOne(EventPhoto $photo): ?string
    {
        $disk = $photo->storage_disk ?? 'public';
        $stored = Storage::disk($disk);
        if (!$stored->exists($photo->original_path)) return null;

        $tmpIn = tempnam(sys_get_temp_dir(), 'enh_in_');
        file_put_contents($tmpIn, $stored->get($photo->original_path));

        $info = @getimagesize($tmpIn);
        if (!$info) { @unlink($tmpIn); return null; }

        $img = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($tmpIn),
            IMAGETYPE_PNG  => @imagecreatefrompng($tmpIn),
            IMAGETYPE_WEBP => @imagecreatefromwebp($tmpIn),
            default        => null,
        };
        if (!$img) { @unlink($tmpIn); return null; }

        // Apply our enhancement chain — values tuned to be subtle
        // (a noticeable boost without obvious "Instagram filter" look).
        imagefilter($img, IMG_FILTER_CONTRAST, -5);   // negative = MORE contrast
        imagefilter($img, IMG_FILTER_BRIGHTNESS, 5);  // very small lift

        // Saturation boost via channel scaling — multiply R/G/B by ~1.08
        // around the average. GD doesn't have a native saturation filter
        // so we do it manually on the pixels.
        $this->boostSaturation($img, 1.08);

        // Save out as JPEG (safer for size + univ compat)
        $tmpOut = tempnam(sys_get_temp_dir(), 'enh_out_');
        imagejpeg($img, $tmpOut, 88);
        imagedestroy($img);

        // Path: <originalDir>/enhanced/<basename>.jpg
        $newPath = dirname($photo->original_path).'/enhanced/'.pathinfo($photo->filename, PATHINFO_FILENAME).'.jpg';
        $stored->put($newPath, file_get_contents($tmpOut));

        @unlink($tmpIn);
        @unlink($tmpOut);
        return $newPath;
    }

    /**
     * Multiply chroma by $factor while keeping luma stable.
     * Simple Y'CbCr-ish approximation that avoids the cost of a real
     * RGB→HSL→RGB roundtrip per pixel.
     */
    private function boostSaturation($img, float $factor): void
    {
        $w = imagesx($img);
        $h = imagesy($img);
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $rgb = imagecolorat($img, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                $avg = ($r + $g + $b) / 3;
                $r = max(0, min(255, $avg + ($r - $avg) * $factor));
                $g = max(0, min(255, $avg + ($g - $avg) * $factor));
                $b = max(0, min(255, $avg + ($b - $avg) * $factor));
                imagesetpixel($img, $x, $y, ((int)$r << 16) | ((int)$g << 8) | (int)$b);
            }
        }
    }
}

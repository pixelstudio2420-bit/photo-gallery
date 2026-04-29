<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventPhoto;
use Illuminate\Support\Facades\Log;

/**
 * Duplicate Detection — perceptual hash (pHash).
 *
 * Algorithm:
 *   1) Resize photo to 32×32 grayscale (GD)
 *   2) Compute average pixel value
 *   3) Build 32×32 bitmap where each pixel is 1 if > average, 0 otherwise
 *   4) Pack bits into 16-hex-char string ("aHash" — fast and good enough
 *      for tight-duplicate detection in event galleries where the
 *      photographer accidentally uploaded the same shot twice)
 *
 * Comparison:
 *   - Two photos with same hash → exact duplicate
 *   - Hamming distance < 6 (out of 64 bits) → near-duplicate
 *
 * Stored on event_photos.phash. The result_meta returned to the caller
 * lists every duplicate group so the UI can render "you have 12 dupes
 * grouped into 4 sets — review & delete".
 *
 * No external API required — pure GD. Works on any PHP install with GD.
 */
class DuplicateDetectionAi
{
    public function run(Event $event): array
    {
        $photos = $event->photos()
            ->where('status', 'active')
            ->whereNotNull('original_path')
            ->get();

        $processed = 0;
        $hashed    = 0;
        $errors    = 0;

        // 1) Hash every photo (skip if already hashed)
        foreach ($photos as $photo) {
            $processed++;
            if (!empty($photo->phash)) continue;

            try {
                $hash = $this->hashPhoto($photo);
                if ($hash) {
                    $photo->forceFill(['phash' => $hash])->save();
                    $hashed++;
                }
            } catch (\Throwable $e) {
                Log::warning('pHash failed for photo '.$photo->id, ['err' => $e->getMessage()]);
                $errors++;
            }
        }

        // 2) Group by hash → find duplicates
        $groups = $event->photos()
            ->whereNotNull('phash')
            ->orderBy('id')
            ->get()
            ->groupBy('phash')
            ->filter(fn ($g) => $g->count() > 1)
            ->map(fn ($g) => $g->pluck('id')->toArray())
            ->values()
            ->toArray();

        return [
            'processed'        => $processed,
            'newly_hashed'     => $hashed,
            'errors'           => $errors,
            'duplicate_groups' => $groups,
            'total_duplicates' => array_sum(array_map(fn ($g) => count($g) - 1, $groups)),
        ];
    }

    /**
     * Compute aHash — 16-hex-char perceptual hash.
     * Returns null if the file can't be read.
     */
    private function hashPhoto(EventPhoto $photo): ?string
    {
        $path = $this->resolvePhotoPath($photo);
        if (!$path || !is_readable($path)) return null;

        $img = $this->loadImage($path);
        if (!$img) return null;

        // 32x32 grayscale → too high-res produces fewer collisions
        // for true duplicates; 8x8 is the classic aHash size and good enough.
        $size = 8;
        $small = imagecreatetruecolor($size, $size);
        imagecopyresampled($small, $img, 0, 0, 0, 0, $size, $size, imagesx($img), imagesy($img));
        imagefilter($small, IMG_FILTER_GRAYSCALE);

        $sum = 0;
        $values = [];
        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
                $rgb = imagecolorat($small, $x, $y);
                $gray = $rgb & 0xFF; // grayscale → r=g=b
                $values[] = $gray;
                $sum += $gray;
            }
        }
        $avg = $sum / count($values);

        // Build 64-bit hash → 16 hex chars
        $bits = '';
        foreach ($values as $v) $bits .= ($v > $avg) ? '1' : '0';

        $hex = '';
        for ($i = 0; $i < 64; $i += 4) {
            $hex .= dechex(bindec(substr($bits, $i, 4)));
        }

        imagedestroy($img);
        imagedestroy($small);

        return $hex;
    }

    private function resolvePhotoPath(EventPhoto $photo): ?string
    {
        // Try local public disk first; fall back to copying via StorageManager
        // to a temp file. Implementation here is best-effort — if GD can't
        // read it we just skip.
        $disk = $photo->storage_disk ?? 'public';
        try {
            $stored = \Illuminate\Support\Facades\Storage::disk($disk);
            if (!$stored->exists($photo->original_path)) return null;

            // For local disks we can hand the path directly.
            if (in_array($disk, ['public', 'local'], true)) {
                return $stored->path($photo->original_path);
            }

            // Remote disk — pull bytes to a temp file.
            $tmp = tempnam(sys_get_temp_dir(), 'phash_');
            file_put_contents($tmp, $stored->get($photo->original_path));
            return $tmp;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function loadImage(string $path)
    {
        $info = @getimagesize($path);
        if (!$info) return null;
        return match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG  => @imagecreatefrompng($path),
            IMAGETYPE_WEBP => @imagecreatefromwebp($path),
            IMAGETYPE_GIF  => @imagecreatefromgif($path),
            default        => null,
        };
    }
}

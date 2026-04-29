<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventPhoto;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Video Thumbnails — extract a poster frame from each video file
 * uploaded to an event.
 *
 * Requires FFmpeg on the server. Detects with `which ffmpeg` (Linux)
 * or by checking common Windows paths. If unavailable, returns a clean
 * "configure FFmpeg" message so the photographer understands.
 *
 * For each video file in the event:
 *   - Run `ffmpeg -i <input> -ss 00:00:01 -vframes 1 <thumb>.jpg`
 *   - Upload thumb back to the event_photos.thumbnail_path slot
 */
class VideoThumbnailsAi
{
    public function isConfigured(): bool
    {
        return !empty($this->ffmpegBinary());
    }

    public function run(Event $event): array
    {
        $bin = $this->ffmpegBinary();
        if (!$bin) {
            return [
                'processed'      => 0,
                'thumbnails'     => 0,
                'errors'         => 0,
                'mode'           => 'unconfigured',
                'configure_hint' => 'ต้องติดตั้ง FFmpeg บน server ก่อนใช้ฟีเจอร์นี้ — แอดมิน apt install ffmpeg (Linux) หรือดาวน์โหลด ffmpeg.exe (Windows)',
            ];
        }

        $videos = $event->photos()
            ->where('status', 'active')
            ->where('mime_type', 'LIKE', 'video/%')
            ->whereNull('thumbnail_path')
            ->get();

        $processed = 0;
        $made      = 0;
        $errors    = 0;

        foreach ($videos as $vid) {
            $processed++;
            try {
                $thumbPath = $this->extractThumbnail($vid, $bin);
                if ($thumbPath) {
                    $vid->forceFill(['thumbnail_path' => $thumbPath])->save();
                    $made++;
                }
            } catch (\Throwable $e) {
                Log::warning('Video thumb failed photo '.$vid->id, ['err' => $e->getMessage()]);
                $errors++;
            }
        }

        return [
            'processed'  => $processed,
            'thumbnails' => $made,
            'errors'     => $errors,
            'mode'       => 'ffmpeg',
        ];
    }

    /** Return absolute path to ffmpeg, or null if not found. */
    private function ffmpegBinary(): ?string
    {
        // Linux/Mac
        $which = @shell_exec('which ffmpeg 2>/dev/null');
        if ($which && trim($which) !== '') return trim($which);

        // Windows common paths
        foreach ([
            'C:\\ffmpeg\\bin\\ffmpeg.exe',
            'C:\\Program Files\\ffmpeg\\bin\\ffmpeg.exe',
            'C:\\xampp\\ffmpeg\\ffmpeg.exe',
        ] as $p) {
            if (file_exists($p)) return $p;
        }

        return null;
    }

    private function extractThumbnail(EventPhoto $vid, string $bin): ?string
    {
        $disk = $vid->storage_disk ?? 'public';
        $stored = Storage::disk($disk);
        if (!$stored->exists($vid->original_path)) return null;

        // Pull video to local temp (FFmpeg needs a file, not a stream)
        $tmpIn = tempnam(sys_get_temp_dir(), 'vid_in_').'.bin';
        file_put_contents($tmpIn, $stored->get($vid->original_path));

        $tmpOut = tempnam(sys_get_temp_dir(), 'vid_thumb_').'.jpg';
        // -ss 1 = grab frame at 1 second mark; -vframes 1 = single frame
        $cmd = escapeshellarg($bin)
             .' -y -i '.escapeshellarg($tmpIn)
             .' -ss 00:00:01 -vframes 1 '
             .escapeshellarg($tmpOut)
             .' 2>&1';
        @exec($cmd, $output, $exitCode);
        @unlink($tmpIn);

        if ($exitCode !== 0 || !file_exists($tmpOut) || filesize($tmpOut) === 0) {
            @unlink($tmpOut);
            return null;
        }

        $newPath = dirname($vid->original_path).'/thumbnails/'.pathinfo($vid->filename, PATHINFO_FILENAME).'.jpg';
        $stored->put($newPath, file_get_contents($tmpOut));
        @unlink($tmpOut);

        return $newPath;
    }
}

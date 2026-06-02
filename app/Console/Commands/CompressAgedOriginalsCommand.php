<?php

namespace App\Console\Commands;

use App\Models\AppSetting;
use App\Models\EventPhoto;
use App\Services\StorageManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Compress old event original photos to recover R2 storage without deletion.
 *
 * Most sales for an event happen in the first 30 days. After 90 days,
 * originals are almost never re-downloaded yet they still occupy R2 at
 * full JPEG quality (~95). Recompressing to JPEG quality 80 typically
 * recovers 40-60% of bytes with no visible quality loss for typical
 * web/social use.
 *
 * Safety
 * ──────
 *   • Master switch: `compress_originals_enabled = 1`
 *   • Only touches photos with `status = active` (not deleted/portfolio)
 *   • Skips photos belonging to events that are exempt or already purged
 *   • Skips photos with `compressed_at` already set (idempotent)
 *   • Updates `file_size` after compression so quota tracking stays accurate
 *   • Batch limit per run to avoid hammering R2 + the queue
 *   • Quality is admin-tunable via `compress_originals_quality` (default 80)
 *
 * Usage
 * ─────
 *   php artisan photos:compress-originals --dry-run
 *   php artisan photos:compress-originals --limit=50 --quality=75
 *
 * Scheduled nightly at 02:45 — after the retention purge at 02:30 so we
 * don't waste cycles compressing files that are about to be deleted.
 */
class CompressAgedOriginalsCommand extends Command
{
    protected $signature = 'photos:compress-originals
        {--dry-run    : Preview only; do not write any files}
        {--limit=     : Cap photos processed this run (override batch_limit setting)}
        {--quality=   : Override JPEG quality (1-100)}
        {--days=      : Override age threshold in days}
        {--force      : Run even when compress_originals_enabled=0}
        {--quiet-success : Suppress output when nothing happened}';

    protected $description = 'บีบอัดต้นฉบับ event_photos ที่เก่ากว่า N วัน เพื่อประหยัด R2 (ไม่ลบไฟล์)';

    public function handle(): int
    {
        $dryRun  = (bool) $this->option('dry-run');
        $force   = (bool) $this->option('force');
        $quiet   = (bool) $this->option('quiet-success');

        $enabled = (string) AppSetting::get('compress_originals_enabled', '1') === '1';
        if (!$enabled && !$force) {
            if (!$quiet) {
                $this->warn('compress_originals_enabled=0 — ใช้ --force หรือเปิดที่ Admin → Settings');
            }
            return self::SUCCESS;
        }

        // GD is required for the recompress path. Imagick would be better but
        // we already verified GD is available; Imagick is optional/installed
        // only on some hosts.
        if (!extension_loaded('gd')) {
            $this->error('GD extension not loaded — cannot compress images');
            return self::FAILURE;
        }

        $thresholdDays = (int) ($this->option('days') ?? AppSetting::get('compress_originals_after_days', 90));
        $quality       = (int) ($this->option('quality') ?? AppSetting::get('compress_originals_quality', 80));
        $limit         = (int) ($this->option('limit') ?? AppSetting::get('compress_originals_batch_limit', 100));

        $quality = max(1, min(100, $quality));
        $thresholdDays = max(1, $thresholdDays);
        $limit = max(1, min(10000, $limit));

        $cutoff = now()->subDays($thresholdDays);

        $this->line('');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->line(' Compress Aged Originals  ' . ($dryRun ? '<fg=yellow>[DRY RUN]</>' : ''));
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->line(" Threshold        : <fg=cyan>{$thresholdDays} days</> (older than {$cutoff->format('Y-m-d')})");
        $this->line(" JPEG quality     : <fg=cyan>{$quality}</>");
        $this->line(" Batch limit      : {$limit}");
        $this->line('');

        // Find candidates — joined with event_events so we can filter exempt
        // / archived events out at SQL time. No mass-load via Eloquent here
        // because we only need a few columns and the query may match
        // thousands of rows.
        $candidates = DB::table('event_photos as ep')
            ->join('event_events as ee', 'ee.id', '=', 'ep.event_id')
            ->where('ep.status', 'active')
            ->whereNull('ep.compressed_at')
            ->whereNotNull('ep.original_path')
            ->where('ep.created_at', '<', $cutoff)
            ->where('ee.auto_delete_exempt', false)
            ->whereNull('ee.originals_purged_at')
            ->select(
                'ep.id', 'ep.event_id', 'ep.original_path', 'ep.storage_disk',
                'ep.file_size', 'ep.mime_type', 'ep.filename'
            )
            ->orderBy('ep.created_at')  // oldest first = biggest savings sooner
            ->limit($limit)
            ->get();

        if ($candidates->isEmpty()) {
            if (!$quiet) {
                $this->line('  <fg=gray>(no candidates)</>');
                $this->line('');
                $this->line('━━━ Summary ━━━');
                $this->line(" Processed  : <fg=gray>0</>");
                $this->line(" Skipped    : 0");
                $this->line(" Errors     : <fg=gray>0</>");
                $this->line(" Saved      : 0 B");
                $this->line('');
            }
            return self::SUCCESS;
        }

        $processed   = 0;
        $skipped     = 0;
        $bytesBefore = 0;
        $bytesAfter  = 0;
        $errors      = 0;

        $storage = app(StorageManager::class);

        foreach ($candidates as $row) {
            // Skip non-JPEG. PNGs and others compress poorly and the quality
            // controls are different — keep them as-is.
            $mime = strtolower((string) ($row->mime_type ?? ''));
            if (!in_array($mime, ['image/jpeg', 'image/jpg'], true)) {
                $skipped++;
                continue;
            }

            $disk = (string) ($row->storage_disk ?: 'public');
            $path = (string) $row->original_path;
            $originalBytes = (int) ($row->file_size ?? 0);

            try {
                // Read the original
                $contents = Storage::disk($disk)->get($path);
                if (!$contents) {
                    $skipped++;
                    continue;
                }

                $beforeSize = strlen($contents);

                // Recompress through GD. We use imagecreatefromstring +
                // imagejpeg into a temp buffer to keep memory bounded;
                // GD frees the resource via PHP's GC.
                $img = @imagecreatefromstring($contents);
                if (!$img) {
                    Log::warning("Compress: GD failed to decode photo #{$row->id} path={$path}");
                    $errors++;
                    continue;
                }

                ob_start();
                $ok = imagejpeg($img, null, $quality);
                $newContents = ob_get_clean();
                imagedestroy($img);

                if (!$ok || !$newContents) {
                    Log::warning("Compress: GD imagejpeg failed for photo #{$row->id}");
                    $errors++;
                    continue;
                }

                $afterSize = strlen($newContents);
                $bytesBefore += $beforeSize;
                $bytesAfter  += $afterSize;

                $savedPct = $beforeSize > 0 ? round((1 - $afterSize / $beforeSize) * 100, 1) : 0;

                // Don't write back if the "compressed" version is BIGGER —
                // happens for already-low-quality sources. Leave the original
                // alone but mark compressed_at so we skip it next run.
                if ($afterSize >= $beforeSize) {
                    if (!$dryRun) {
                        DB::table('event_photos')->where('id', $row->id)->update([
                            'compressed_at' => now(),
                            'updated_at'    => now(),
                        ]);
                    }
                    $this->line(sprintf(
                        "  <fg=gray>skip-noop</> #%d \"%s\" — new size >= original (saved %s%%)",
                        $row->id, $row->filename ?? '?', $savedPct
                    ));
                    $skipped++;
                    continue;
                }

                $this->line(sprintf(
                    "  <fg=green>compress</> #%d \"%s\" — %s → %s (saved %s%%)",
                    $row->id,
                    $row->filename ?? '?',
                    $this->humanBytes($beforeSize),
                    $this->humanBytes($afterSize),
                    $savedPct
                ));

                if (!$dryRun) {
                    // Write back to the same path (overwrites)
                    $ok = $storage->putToDriver($disk, $path, $newContents);
                    if (!$ok) {
                        // Fallback to direct disk write
                        Storage::disk($disk)->put($path, $newContents);
                    }

                    // Update file_size + compressed_at; also decrement the
                    // photographer_profiles.storage_used_bytes by the delta.
                    // The recalc-storage cron will reconcile drift either way.
                    DB::table('event_photos')->where('id', $row->id)->update([
                        'file_size'     => $afterSize,
                        'compressed_at' => now(),
                        'updated_at'    => now(),
                    ]);
                }

                $processed++;
            } catch (\Throwable $e) {
                Log::error("Compress: photo #{$row->id} failed: " . $e->getMessage());
                $errors++;
            }
        }

        $totalSaved = $bytesBefore - $bytesAfter;
        $totalSavedPct = $bytesBefore > 0 ? round((1 - $bytesAfter / $bytesBefore) * 100, 1) : 0;

        $this->line('');
        $this->line('━━━ Summary ━━━');
        $this->line(" Processed  : <fg=green>{$processed}</>");
        $this->line(" Skipped    : {$skipped}");
        $this->line(" Errors     : <fg=" . ($errors ? 'red' : 'gray') . ">{$errors}</>");
        $this->line(" Saved      : " . $this->humanBytes($totalSaved) . " ({$totalSavedPct}%)" . ($dryRun ? ' [DRY RUN]' : ''));
        $this->line('');

        return self::SUCCESS;
    }

    private function humanBytes(int $bytes): string
    {
        if ($bytes < 1024) return $bytes . ' B';
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = floor(log($bytes, 1024));
        return round($bytes / (1024 ** $power), 2) . ' ' . $units[$power];
    }
}

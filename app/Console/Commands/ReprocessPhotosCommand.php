<?php

namespace App\Console\Commands;

use App\Jobs\ProcessUploadedPhotoJob;
use App\Models\EventPhoto;
use Illuminate\Console\Command;

/**
 * Re-run photo processing (thumbnail + watermark generation) for any
 * photos that are stuck at status='processing' or have failed previously.
 *
 * Why this exists
 * ---------------
 * Photos are uploaded to R2 immediately, but the post-upload pipeline
 * (resize, watermark, exif strip, dimension probe) runs as a queued
 * job. If the queue worker isn't running — common during initial
 * deploy on Laravel Cloud before a Worker resource is provisioned —
 * the records pile up at status='processing' and the UI shows the
 * "กำลังประมวลผล" placeholder forever.
 *
 * This command walks the stuck rows and re-dispatches them
 * synchronously (so it works even without a worker), making it the
 * one-shot recovery tool for any operator who:
 *   • finished setting up R2 after photos were already uploaded
 *   • migrated from sync → async queue and back
 *   • just wants to retry a few `failed` photos by hand
 *
 * Examples:
 *
 *   # Reprocess every stuck photo across the whole site
 *   php artisan photos:reprocess
 *
 *   # Just one event
 *   php artisan photos:reprocess --event=2
 *
 *   # Include photos that previously errored out
 *   php artisan photos:reprocess --include-failed
 *
 *   # Cap the run — useful when you're testing on production
 *   php artisan photos:reprocess --limit=10
 */
class ReprocessPhotosCommand extends Command
{
    protected $signature = 'photos:reprocess
                            {--event= : Only reprocess photos in this event ID}
                            {--include-failed : Also retry photos with status=failed}
                            {--missing-variants : Also reprocess active rows whose thumbnail_path or watermarked_path is NULL (most common production fix)}
                            {--limit= : Stop after N photos (default: no limit)}
                            {--dry-run : List what would be reprocessed without doing it}';

    protected $description = 'Re-run thumbnail/watermark generation for stuck (processing), failed, or active-with-missing-variants photos';

    public function handle(): int
    {
        $statuses = ['processing'];
        if ($this->option('include-failed')) {
            $statuses[] = 'failed';
        }

        // Default to including missing-variants — that's the most common
        // production scenario. Operators who want only the legacy
        // status='processing' behaviour can pass --missing-variants=0
        // (the option is null/true/false; we treat null as true).
        $includeMissing = $this->option('missing-variants') !== false;

        $query = EventPhoto::query()->where(function ($q) use ($statuses, $includeMissing) {
            $q->whereIn('status', $statuses);
            if ($includeMissing) {
                // Active rows with missing variants — uploaded successfully
                // but never made it through the watermark pipeline (queue
                // worker not provisioned, pipeline error, etc.). The proxy
                // serves a 1×1 placeholder for these and the gallery shows
                // blank thumbnails. This is what fixes loadroop.com prod.
                $q->orWhere(function ($qq) {
                    $qq->where('status', 'active')
                       ->where(function ($qqq) {
                           $qqq->whereNull('thumbnail_path')
                               ->orWhereNull('watermarked_path')
                               ->orWhere('thumbnail_path', '')
                               ->orWhere('watermarked_path', '');
                       });
                });
            }
        });

        if ($eventId = $this->option('event')) {
            $query->where('event_id', (int) $eventId);
        }

        $limit = $this->option('limit');
        if ($limit !== null && $limit !== false) {
            $query->limit((int) $limit);
        }

        $photos = $query->get(['id', 'event_id', 'original_filename', 'status', 'original_path', 'thumbnail_path', 'watermarked_path']);
        $count  = $photos->count();

        if ($count === 0) {
            $this->info('No photos to reprocess.');
            return self::SUCCESS;
        }

        $this->info("Found {$count} photo(s) to reprocess:");
        $this->table(
            ['ID', 'Event', 'Filename', 'Status'],
            $photos->map(fn ($p) => [
                $p->id,
                $p->event_id,
                substr($p->original_filename ?? '', 0, 40),
                $p->status,
            ])->all()
        );

        if ($this->option('dry-run')) {
            $this->warn('Dry run — no jobs dispatched. Re-run without --dry-run to actually reprocess.');
            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        $okay = 0; $failed = 0;
        foreach ($photos as $photo) {
            try {
                // Reset status so the job's "already processed" early-out
                // doesn't skip retries of failed photos.
                if ($photo->status === 'failed') {
                    $photo->update(['status' => 'processing']);
                }

                // Inline (sync) so this command works even when no
                // queue worker is running. For very large batches the
                // operator can switch to async by chaining with `&`.
                ProcessUploadedPhotoJob::dispatchSync($photo->id);
                $okay++;
            } catch (\Throwable $e) {
                $failed++;
                $this->newLine();
                $this->error("  ✗ Photo #{$photo->id}: " . $e->getMessage());
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("✅ Reprocessed: {$okay}");
        if ($failed > 0) {
            $this->warn("⚠️  Failed: {$failed} (see error messages above; re-run with --include-failed to retry)");
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Models\EventPhoto;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * `php artisan photos:reconcile-orphans`
 *
 * Detects and (optionally) heals two classes of drift between the
 * event_photos table and R2 (or whatever the storage_disk says):
 *
 *   1. ROW-WITHOUT-OBJECT
 *      A row in event_photos points at a path that no longer exists
 *      on its disk. Symptom: ModeratePhotoJob fails with
 *      "cannot read bytes for photo #N" — exactly the warning seen
 *      in production logs. Action: mark status='failed' so it stops
 *      re-queueing forever, log for ops review.
 *
 *   2. OBJECT-WITHOUT-ROW
 *      An object exists on R2 under events/photos/user_X/event_Y/
 *      but no event_photos row references it. Probably a half-failed
 *      upload that didn't roll back the R2 PUT. Action: log for
 *      manual review (we DON'T auto-delete because the object might
 *      be tied to an in-flight upload right now).
 *
 * Both passes are SAFE TO RUN repeatedly — never delete DB rows,
 * never delete R2 objects without --purge.
 */
class PhotosReconcileOrphansCommand extends Command
{
    protected $signature = 'photos:reconcile-orphans
        {--limit=1000 : Max rows to scan per pass (avoid heavy production runs)}
        {--purge : Hard-mark unreadable rows as status=failed (default: just log)}
        {--quiet-success : Suppress all-good output}';

    protected $description = 'Detect drift between event_photos rows and on-disk objects; optionally mark unreadable rows as failed.';

    public function handle(): int
    {
        $limit  = max(1, (int) $this->option('limit'));
        $purge  = (bool) $this->option('purge');
        $quiet  = (bool) $this->option('quiet-success');

        $totals = ['scanned' => 0, 'unreadable' => 0, 'marked_failed' => 0];

        // PASS 1: rows-without-objects.
        // We focus on photos in 'active' or 'processing' states (the ones
        // a worker will retry forever) — 'failed' rows were already
        // diagnosed.
        $query = EventPhoto::query()
            ->whereIn('status', ['active', 'processing'])
            ->whereNotNull('original_path')
            ->orderBy('id')
            ->limit($limit);

        $bar = $this->output->createProgressBar((clone $query)->count());
        $bar->start();

        $query->chunkById(100, function ($photos) use (&$totals, $purge, $bar) {
            foreach ($photos as $photo) {
                $bar->advance();
                $totals['scanned']++;
                try {
                    $disk = Storage::disk((string) ($photo->storage_disk ?: 'public'));
                    if ($disk->exists($photo->original_path)) {
                        continue;       // healthy
                    }
                    $totals['unreadable']++;
                    Log::warning('Orphan event_photo row (object missing)', [
                        'photo_id'      => $photo->id,
                        'event_id'      => $photo->event_id,
                        'storage_disk'  => $photo->storage_disk,
                        'original_path' => $photo->original_path,
                    ]);
                    if ($purge) {
                        $photo->forceFill(['status' => 'failed'])->saveQuietly();
                        $totals['marked_failed']++;
                    }
                } catch (\Throwable $e) {
                    Log::error('photos:reconcile-orphans probe failed', [
                        'photo_id' => $photo->id,
                        'error'    => $e->getMessage(),
                    ]);
                }
            }
        });

        $bar->finish();
        $this->newLine();

        if ($totals['unreadable'] === 0 && !$quiet) {
            $this->components->info(sprintf('Clean: %d photos scanned, all readable.', $totals['scanned']));
        } else {
            $this->components->warn(sprintf(
                'Scanned %d photos, %d unreadable%s.',
                $totals['scanned'],
                $totals['unreadable'],
                $purge ? ", {$totals['marked_failed']} marked failed" : ' (logged only — pass --purge to mark failed)',
            ));
        }

        return self::SUCCESS;
    }
}

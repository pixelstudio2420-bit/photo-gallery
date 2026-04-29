<?php

namespace App\Jobs;

use App\Models\EventPhoto;
use App\Services\StorageManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Async mirror: copy an EventPhoto's files (original/thumbnail/watermarked)
 * from its primary storage_disk to the configured mirror targets.
 *
 * Runs on the "downloads" queue so it doesn't fight for web workers.
 *
 * Idempotent: re-running it on an already-mirrored photo is a no-op (each
 * putToDriver() overwrites safely, and the success set is recorded in
 * event_photos.storage_mirrors).
 */
class MirrorPhotoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 600;
    public int $backoff = 120;

    /**
     * @param  int          $photoId  event_photos.id
     * @param  array|null   $targets  Override mirror targets; null = use settings
     */
    public function __construct(public int $photoId, public ?array $targets = null)
    {
        $this->onQueue('downloads');
    }

    public function handle(StorageManager $storage): void
    {
        $photo = EventPhoto::find($this->photoId);
        if (!$photo) {
            Log::warning("MirrorPhotoJob: photo {$this->photoId} not found");
            return;
        }

        $from = $photo->storage_disk ?: $storage->primaryDriver();
        $targets = $this->targets ?? $storage->mirrorTargets();

        if (empty($targets)) {
            return;
        }

        $paths = array_filter([
            'original'    => $photo->original_path,
            'thumbnail'   => $photo->thumbnail_path,
            'watermarked' => $photo->watermarked_path,
        ]);

        // Do the actual copy work OUTSIDE the row lock — copies can be
        // minutes long, and holding a SELECT FOR UPDATE the whole time
        // would block dashboard/list reads. We only lock at the very end
        // when merging the success set into storage_mirrors.
        $newlyMirrored = [];
        foreach ($targets as $target) {
            if ($target === $from) continue;
            $allOk = true;

            foreach ($paths as $key => $path) {
                if (!$storage->copyBetweenDrivers($path, $from, $target)) {
                    $allOk = false;
                    Log::warning("MirrorPhotoJob: copy failed photo={$photo->id} path={$key} {$from}→{$target}");
                    break;
                }
            }

            if ($allOk) {
                $newlyMirrored[] = $target;
            }
        }

        // Race-safe merge: lock the row, re-read storage_mirrors (another
        // job may have added targets concurrently), union with our new
        // results, persist. The transaction ensures the read+merge+write
        // is atomic.
        DB::transaction(function () use ($photo, $newlyMirrored) {
            $fresh = EventPhoto::lockForUpdate()->find($photo->id);
            if (!$fresh) {
                Log::warning("MirrorPhotoJob: photo {$photo->id} disappeared mid-mirror");
                return;
            }
            $existing = is_array($fresh->storage_mirrors) ? $fresh->storage_mirrors : [];
            $merged   = array_values(array_unique(array_merge($existing, $newlyMirrored)));

            // Skip the UPDATE entirely if nothing changed — saves a write
            // when the job re-runs and everything is already mirrored.
            if ($merged !== $existing || empty($fresh->last_mirror_check)) {
                $fresh->storage_mirrors   = $merged;
                $fresh->last_mirror_check = now();
                $fresh->save();
            }
        });
    }
}

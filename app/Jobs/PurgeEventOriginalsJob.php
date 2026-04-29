<?php

namespace App\Jobs;

use App\Models\Event;
use App\Models\EventPhoto;
use App\Services\ActivityLogger;
use App\Services\StorageManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Purge an expired event's ORIGINALS while keeping portfolio assets intact.
 *
 * This is the "soft retention" variant of {@see PurgeEventJob}:
 *
 *   ✅  KEEP   cover_image   — hero shot on the photographer's portfolio card
 *   ✅  KEEP   thumbnail_path — small preview shown in the portfolio grid
 *   ✅  KEEP   watermarked_path — optional larger preview (still watermarked)
 *   ❌  WIPE   original_path  — the money bytes; re-selling them is impossible
 *
 * After the run:
 *   • Event.status                = 'archived'
 *   • Event.originals_purged_at   = now()
 *   • EventPhoto.original_path    = null on every row (file already gone)
 *   • EventPhoto.rekognition_face_id cleared (face search stops working —
 *     the event is a showcase now, not a live gallery)
 *   • Google Drive folder removed when `$purgeDrive = true`
 *
 * Orders, reviews, packages — all preserved. Revenue history stays intact.
 *
 * Runs on the `downloads` queue (slow storage work like ZIP builds).
 */
class PurgeEventOriginalsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 1;       // one shot — half-purged state is worse than a skip
    public int $timeout = 900;     // 15 min max per event

    public function __construct(
        public int  $eventId,
        public bool $purgeDrive      = false,
        public bool $dryRun          = false,
        public ?int $triggeredByUser = null
    ) {
        $this->onQueue('downloads');
    }

    public function handle(): void
    {
        $event = Event::find($this->eventId);
        if (!$event) {
            Log::info("PurgeEventOriginalsJob: event {$this->eventId} gone — skipping");
            return;
        }

        if ($event->isPortfolioOnly()) {
            Log::info("PurgeEventOriginalsJob: event {$event->id} already in portfolio mode — skipping");
            return;
        }

        $summary = [
            'event_id'        => $event->id,
            'event_name'      => $event->name,
            'originals_files' => 0,
            'photos_touched'  => 0,
            'drive_folder'    => null,
            'dry_run'         => $this->dryRun,
            'mode'            => 'portfolio',
        ];

        try {
            $photos = $event->photos()->get();
            $summary['photos_touched'] = $photos->count();

            if ($this->dryRun) {
                $summary['originals_files'] = $photos->filter(fn($p) => !empty($p->original_path))->count();
                $summary['drive_folder']    = $event->drive_folder_id;
                Log::info('PurgeEventOriginalsJob DRY RUN', $summary);
                return;
            }

            $storage = app(StorageManager::class);

            // 1) Per-photo file delete (originals only) + optional Rekognition cleanup
            foreach ($photos as $photo) {
                if (!empty($photo->original_path)) {
                    $this->deleteOriginal($storage, $photo);
                    $summary['originals_files']++;
                }

                // Nuke face vectors — event is no longer searchable
                if (!empty($photo->rekognition_face_id) && !empty($photo->event_id)) {
                    try {
                        app(\App\Services\FaceSearchService::class)
                            ->deleteFace('event-' . $photo->event_id, $photo->rekognition_face_id);
                    } catch (\Throwable $e) {
                        Log::debug("PurgeEventOriginalsJob: Rekognition cleanup skipped #{$photo->id}: " . $e->getMessage());
                    }
                }

                // Update the row — keep preview pointers, drop the original.
                // We do this via DB::table() to avoid re-firing the EventPhoto
                // deleting hook (which would try to nuke previews too).
                DB::table('event_photos')
                    ->where('id', $photo->id)
                    ->update([
                        'original_path'       => null,
                        'rekognition_face_id' => null,
                        'status'              => 'portfolio',
                        'updated_at'          => now(),
                    ]);
            }

            // 2) Drive folder (opt-in — destroys all uploaded files on Drive)
            if ($this->purgeDrive && $event->drive_folder_id) {
                try {
                    $drive = app(\App\Services\GoogleDriveService::class);
                    if (method_exists($drive, 'deleteFolder')) {
                        $drive->deleteFolder($event->drive_folder_id);
                        $summary['drive_folder'] = $event->drive_folder_id;
                    }
                } catch (\Throwable $e) {
                    Log::warning("PurgeEventOriginalsJob drive delete fail for event {$event->id}: " . $e->getMessage());
                }
            }

            // 3) Also nuke the `events/{id}/photos/original` directory tree in
            //    case the per-file pass missed anything (e.g. orphaned files
            //    with no matching row). Previews live in sibling folders
            //    (`thumbnails/`, `watermarked/`), so this surgical purge is
            //    safe.
            try {
                $storage->purgeDirectory("events/{$event->id}/photos/original");
            } catch (\Throwable $e) {
                Log::warning("PurgeEventOriginalsJob directory sweep fail for event {$event->id}: " . $e->getMessage());
            }

            // 4) Flip the event into portfolio mode
            $event->forceFill([
                'status'              => 'archived',
                'originals_purged_at' => now(),
                'auto_delete_at'      => null, // clear any explicit delete schedule
            ])->save();

            Log::info('PurgeEventOriginalsJob success', $summary);

            if (class_exists(ActivityLogger::class)) {
                try {
                    ActivityLogger::log(
                        action:      'event.portfolio_archive',
                        description: "Archived event #{$event->id} \"{$event->name}\" to portfolio " .
                                     "(originals removed, previews kept)",
                        module:      'retention',
                        userId:      $this->triggeredByUser
                    );
                } catch (\Throwable $e) {
                    Log::debug('PurgeEventOriginalsJob: activity log skipped: ' . $e->getMessage());
                }
            }
        } catch (\Throwable $e) {
            Log::error("PurgeEventOriginalsJob FAILED for event {$this->eventId}: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error("PurgeEventOriginalsJob permanently failed for event {$this->eventId}: " . $e->getMessage());
    }

    /**
     * Wipe a single photo's ORIGINAL file across primary disk + every mirror.
     * Preview variants (thumbnail / watermarked) are untouched.
     */
    private function deleteOriginal(StorageManager $storage, EventPhoto $photo): void
    {
        $path = $photo->original_path;
        if (!$path) return;

        try {
            if (!empty($photo->storage_disk)) {
                $storage->deleteMany([$path], $photo->storage_disk);
            }
            $mirrors = is_array($photo->storage_mirrors) ? $photo->storage_mirrors : [];
            foreach ($mirrors as $mirrorDisk) {
                if ($mirrorDisk === $photo->storage_disk) continue;
                $storage->deleteMany([$path], $mirrorDisk);
            }
            // Belt & braces — the public disk is where legacy uploads landed
            // before multi-driver storage; delete there too if it's not the
            // primary.
            if ($photo->storage_disk !== 'public') {
                try { Storage::disk('public')->delete($path); } catch (\Throwable) {}
            }
        } catch (\Throwable $e) {
            Log::warning("PurgeEventOriginalsJob: file cleanup failed for photo #{$photo->id}: " . $e->getMessage());
        }
    }
}

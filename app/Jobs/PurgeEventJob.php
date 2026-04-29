<?php

namespace App\Jobs;

use App\Models\AppSetting;
use App\Models\Event;
use App\Services\ActivityLogger;
use App\Services\GoogleDriveService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Delete one expired event and all its associated records & storage.
 *
 * What gets deleted
 * -----------------
 *  • event_photos          — cascades via FK
 *  • sync_queue rows       — cascades via FK
 *  • event_photos_cache    — manual (no FK)
 *  • pricing_packages      — manual (no FK)
 *  • reviews               — manual, hard delete
 *  • wishlists             — manual, hard delete
 *  • chat_conversations.event_id → NULL (preserve chat history)
 *  • Cover image on public disk
 *  • Google Drive folder (only if event_auto_delete_purge_drive = 1)
 *  • The event row itself
 *
 * What is preserved
 * -----------------
 *  • Orders — kept as-is with event_id reference; they'll just point at a
 *    deleted event_id, which is fine for revenue/reporting purposes. The
 *    PurgeExpiredEventsCommand already refuses to dispatch this job for
 *    events with revenue-bearing orders unless --include-with-orders.
 *
 * Why a job, not inline?
 * ----------------------
 * Drive API calls + image deletes can take minutes per event; running inline
 * would block the scheduler tick. Goes on the `downloads` queue alongside ZIP
 * builds because both are "slow storage work."
 */
class PurgeEventJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 1;       // one shot — partial deletion is worse than a skip
    public int $timeout = 900;     // 15 min max per event

    public function __construct(
        public int  $eventId,
        public bool $purgeDrive     = false,
        public bool $dryRun         = false,
        public ?int $triggeredByUser = null
    ) {
        $this->onQueue('downloads');
    }

    public function handle(): void
    {
        $event = Event::find($this->eventId);
        if (!$event) {
            Log::info("PurgeEventJob: event {$this->eventId} already gone — skipping");
            return;
        }

        $summary = [
            'event_id'     => $event->id,
            'event_name'   => $event->name,
            'photos'       => 0,
            'cache_rows'   => 0,
            'packages'     => 0,
            'reviews'      => 0,
            'wishlists'    => 0,
            'chat_unlinks' => 0,
            'drive_folder' => null,
            'cover_image'  => null,
            'dry_run'      => $this->dryRun,
        ];

        try {
            if ($this->dryRun) {
                $summary['photos']       = $event->photos()->count();
                $summary['cache_rows']   = DB::table('event_photos_cache')->where('event_id', $event->id)->count();
                $summary['packages']     = DB::table('pricing_packages')->where('event_id', $event->id)->count();
                $summary['reviews']      = $event->reviews()->count();
                $summary['wishlists']    = DB::table('wishlists')->where('event_id', $event->id)->count();
                $summary['chat_unlinks'] = DB::table('chat_conversations')->where('event_id', $event->id)->count();
                $summary['drive_folder'] = $event->drive_folder_id;
                $summary['cover_image']  = $event->cover_image;
                Log::info('PurgeEventJob DRY RUN', $summary);
                return;
            }

            // 1. Manually delete related rows (no FK cascade)
            $summary['cache_rows'] = DB::table('event_photos_cache')->where('event_id', $event->id)->delete();
            $summary['packages']   = DB::table('pricing_packages')->where('event_id', $event->id)->delete();
            $summary['reviews']    = DB::table('reviews')->where('event_id', $event->id)->delete();
            $summary['wishlists']  = DB::table('wishlists')->where('event_id', $event->id)->delete();
            $summary['chat_unlinks'] = DB::table('chat_conversations')
                ->where('event_id', $event->id)
                ->update(['event_id' => null]);

            // 2. Count photos before they cascade out
            $summary['photos'] = $event->photos()->count();

            // 3. Cover image — sweep whichever driver it landed on (R2 /
            //    S3 / public) via StorageManager::deleteAsset. The legacy
            //    public-disk-only path left cover objects on R2 after a
            //    retention purge, burning storage quota indefinitely.
            if ($event->cover_image && !str_starts_with($event->cover_image, 'http')) {
                try {
                    $deleted = app(\App\Services\StorageManager::class)
                        ->deleteAsset($event->cover_image);
                    if ($deleted > 0) {
                        $summary['cover_image'] = $event->cover_image;
                    }
                } catch (\Throwable $e) {
                    Log::warning("PurgeEventJob cover-delete fail for event {$event->id}: " . $e->getMessage());
                }
            }

            // 4. Google Drive folder (opt-in — this is DESTRUCTIVE on Drive!)
            if ($this->purgeDrive && $event->drive_folder_id) {
                try {
                    $drive = app(GoogleDriveService::class);
                    if (method_exists($drive, 'deleteFolder')) {
                        $drive->deleteFolder($event->drive_folder_id);
                        $summary['drive_folder'] = $event->drive_folder_id;
                    } else {
                        Log::info("PurgeEventJob: GoogleDriveService has no deleteFolder() — skipped drive purge");
                    }
                } catch (\Throwable $e) {
                    Log::warning("PurgeEventJob drive-delete fail for event {$event->id}: " . $e->getMessage());
                }
            }

            // 5. The event itself — cascades event_photos + sync_queue
            $event->delete();

            Log::info('PurgeEventJob deleted', $summary);

            if (class_exists(ActivityLogger::class)) {
                try {
                    ActivityLogger::log(
                        action:      'event.auto_delete',
                        description: "Auto-deleted event #{$summary['event_id']} \"{$summary['event_name']}\" " .
                                     "(photos={$summary['photos']}, reviews={$summary['reviews']})",
                        module:      'retention',
                        userId:      $this->triggeredByUser
                    );
                } catch (\Throwable $e) {
                    // Never let activity-log failure poison a successful purge
                    Log::debug('PurgeEventJob: activity log skipped: ' . $e->getMessage());
                }
            }
        } catch (\Throwable $e) {
            Log::error("PurgeEventJob FAILED for event {$this->eventId}: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error("PurgeEventJob permanently failed for event {$this->eventId}: " . $e->getMessage());
    }
}

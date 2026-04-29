<?php

namespace App\Jobs;

use App\Models\Event;
use App\Services\GoogleDriveService;
use App\Services\QueueService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Coordinator Job — Import photos from a Google Drive folder.
 *
 * Flow:
 *   1) List all image files in the Drive folder (via API)
 *   2) Skip files already imported (by drive_file_id)
 *   3) Dispatch ImportSingleDrivePhotoJob for each new file
 *   4) Track progress in sync_queue
 *
 * Used for Case A (Google Drive Import) in the Hybrid Architecture.
 */
class ImportDrivePhotosJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 600; // 10 minutes for listing + dispatching

    /**
     * Seconds to wait between retries. This job's failure mode is almost
     * always a Drive API quota miss — a 2-minute cooldown gives the
     * per-second and per-minute quotas time to refill before we re-list
     * the folder (which can be thousands of files).
     */
    public int $backoff = 120;

    public function __construct(
        public int    $eventId,
        public string $driveFolderId,
        public int    $syncQueueId = 0  // ID in sync_queue for progress tracking
    ) {}

    public function handle(): void
    {
        Log::info("ImportDrivePhotosJob: Starting import for event #{$this->eventId}, folder: {$this->driveFolderId}");

        $drive = app(GoogleDriveService::class);

        try {
            // 1) Mark sync_queue as processing
            $this->updateSyncStatus('processing');

            // 2) List files from Google Drive
            $files = $drive->listFolderFilesDetailed($this->driveFolderId, 1000);

            if (empty($files)) {
                Log::warning("ImportDrivePhotosJob: No files found in folder {$this->driveFolderId}");
                $this->updateSyncStatus('completed', 0, 0);
                return;
            }

            // 3) Get already-imported drive_file_ids for this event
            $existingIds = DB::table('event_photos')
                ->where('event_id', $this->eventId)
                ->where('source', 'drive')
                ->whereIn('status', ['active', 'processing'])
                ->pluck('drive_file_id')
                ->filter()
                ->toArray();

            // 4) Filter out already-imported files
            $newFiles = array_filter($files, function ($file) use ($existingIds) {
                return !in_array($file['id'] ?? '', $existingIds, true);
            });

            $totalNew = count($newFiles);

            if ($totalNew === 0) {
                Log::info("ImportDrivePhotosJob: All {$this->eventId} photos already imported.");
                $this->updateSyncStatus('completed', count($files), count($files));
                return;
            }

            // 5) Update total count
            $this->updateSyncStatus('processing', $totalNew, 0);

            // 6) Dispatch individual import jobs with delay staggering
            $delay = 0;
            foreach (array_values($newFiles) as $index => $file) {
                ImportSingleDrivePhotoJob::dispatch(
                    $this->eventId,
                    $file,
                    $this->syncQueueId
                )->delay(now()->addSeconds($delay))
                 ->onQueue('photos');

                // Stagger jobs: 2 seconds apart to avoid API rate limits
                $delay += 2;
            }

            Log::info("ImportDrivePhotosJob: Dispatched {$totalNew} import jobs for event #{$this->eventId}");

        } catch (\Throwable $e) {
            Log::error("ImportDrivePhotosJob failed for event #{$this->eventId}", [
                'error' => $e->getMessage(),
            ]);

            $this->updateSyncStatus('failed', 0, 0, $e->getMessage());
            throw $e;
        }
    }

    /**
     * Update sync_queue record status.
     */
    private function updateSyncStatus(string $status, ?int $total = null, ?int $processed = null, ?string $error = null): void
    {
        if (!$this->syncQueueId || !Schema::hasTable('sync_queue')) {
            return;
        }

        try {
            $update = ['status' => $status];

            if ($total !== null) {
                $update['total_files'] = $total;
            }
            if ($processed !== null) {
                $update['processed_files'] = $processed;
            }
            if ($error !== null) {
                $update['error_message'] = mb_substr($error, 0, 500);
            }
            if ($status === 'processing' && !isset($update['started_at'])) {
                $update['started_at'] = now();
            }
            if (in_array($status, ['completed', 'failed'])) {
                $update['completed_at'] = now();
            }

            DB::table('sync_queue')->where('id', $this->syncQueueId)->update($update);
        } catch (\Throwable $e) {
            Log::warning("Failed to update sync_queue #{$this->syncQueueId}: " . $e->getMessage());
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("ImportDrivePhotosJob permanently failed for event #{$this->eventId}: " . $exception->getMessage());
        $this->updateSyncStatus('failed', null, null, $exception->getMessage());
    }
}

<?php

namespace App\Jobs;

use App\Models\EventPhoto;
use App\Services\GoogleDriveService;
use App\Services\ImageProcessorService;
use App\Services\StorageManager;
use App\Services\WatermarkService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Import a single photo from Google Drive:
 *   1) Download file content from Drive API (authenticated)
 *   2) Upload original to S3/R2/local
 *   3) Generate thumbnail (400px)
 *   4) Apply watermark + resize preview (1200px)
 *   5) Create EventPhoto record with status='active'
 *   6) Update sync_queue progress counter
 *
 * Used for Case A (Google Drive Import) in the Hybrid Architecture.
 */
class ImportSingleDrivePhotoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 300; // 5 minutes per photo
    public int $backoff = 30;

    public function __construct(
        public int   $eventId,
        public array $driveFile,      // {id, name, mimeType, size, imageMediaMetadata, ...}
        public int   $syncQueueId = 0
    ) {}

    public function handle(): void
    {
        $fileId   = $this->driveFile['id'] ?? '';
        $fileName = $this->driveFile['name'] ?? 'unknown.jpg';

        Log::info("ImportSingleDrivePhotoJob: Importing {$fileName} (Drive:{$fileId}) for event #{$this->eventId}");

        // Skip if already imported
        $exists = EventPhoto::where('event_id', $this->eventId)
            ->where('drive_file_id', $fileId)
            ->whereIn('status', ['active', 'processing'])
            ->exists();

        if ($exists) {
            Log::info("ImportSingleDrivePhotoJob: {$fileId} already imported, skipping.");
            $this->incrementProgress();
            return;
        }

        $drive    = app(GoogleDriveService::class);
        $storage  = app(StorageManager::class);
        $imgProc  = new ImageProcessorService();
        $tmpFiles = [];

        try {
            // 1) Download file from Google Drive
            $fileContents = $drive->downloadFileContent($fileId);

            if (empty($fileContents)) {
                throw new \RuntimeException("Failed to download file {$fileId} from Google Drive");
            }

            // 2) Save to temp for processing
            $ext = pathinfo($fileName, PATHINFO_EXTENSION) ?: 'jpg';
            $uniqueName = Str::uuid() . '.' . strtolower($ext);

            $tmpOriginal = tempnam(sys_get_temp_dir(), 'drive_photo_');
            file_put_contents($tmpOriginal, $fileContents);
            $tmpFiles[] = $tmpOriginal;

            // 3) Get paths
            $paths = $storage->photoPaths($this->eventId, $uniqueName, $ext);

            // 4) Upload original to cloud/local storage
            $disk = $storage->preferredDisk();
            $storage->put($paths['original_path'], $fileContents);

            // 5) Generate thumbnail
            $thumbData = $imgProc->thumbnail($tmpOriginal, 400);
            if ($thumbData) {
                $storage->put($paths['thumbnail_path'], $thumbData);
            }

            // 6) Generate watermarked preview
            $watermark = new WatermarkService();
            if ($watermark->isEnabled()) {
                $wmData = $watermark->apply($tmpOriginal);
                $tmpWm = tempnam(sys_get_temp_dir(), 'drive_wm_');
                file_put_contents($tmpWm, $wmData);
                $tmpFiles[] = $tmpWm;

                $wmResized = $imgProc->resize($tmpWm, 1200, 1200);
                if ($wmResized) {
                    $storage->put($paths['watermarked_path'], $wmResized);
                }
            } else {
                $previewData = $imgProc->resize($tmpOriginal, 1200, 1200);
                if ($previewData) {
                    $storage->put($paths['watermarked_path'], $previewData);
                }
            }

            // 7) Get dimensions
            $dims = $imgProc->getDimensions($tmpOriginal);
            $meta = $this->driveFile['imageMediaMetadata'] ?? [];

            // 8) Get next sort order
            $maxSort = EventPhoto::where('event_id', $this->eventId)->max('sort_order') ?? 0;

            // 9) Create EventPhoto record
            EventPhoto::create([
                'event_id'          => $this->eventId,
                'uploaded_by'       => null, // System import
                'source'            => 'drive',
                'filename'          => $uniqueName,
                'original_filename' => $fileName,
                'mime_type'         => $this->driveFile['mimeType'] ?? 'image/jpeg',
                'file_size'         => (int) ($this->driveFile['size'] ?? filesize($tmpOriginal)),
                'width'             => (int) ($dims['width']  ?? $meta['width']  ?? 0),
                'height'            => (int) ($dims['height'] ?? $meta['height'] ?? 0),
                'storage_disk'      => $disk,
                'original_path'     => $paths['original_path'],
                'thumbnail_path'    => $paths['thumbnail_path'],
                'watermarked_path'  => $paths['watermarked_path'],
                'drive_file_id'     => $fileId,
                'sort_order'        => $maxSort + 1,
                'status'            => 'active',
            ]);

            // 10) Update progress
            $this->incrementProgress();

            Log::info("ImportSingleDrivePhotoJob: {$fileName} imported successfully for event #{$this->eventId}");

        } catch (\Throwable $e) {
            Log::error("ImportSingleDrivePhotoJob: Failed to import {$fileName}", [
                'event_id' => $this->eventId,
                'file_id'  => $fileId,
                'error'    => $e->getMessage(),
            ]);

            throw $e;
        } finally {
            foreach ($tmpFiles as $tmp) {
                @unlink($tmp);
            }
        }
    }

    /**
     * Increment the processed_files counter in sync_queue.
     */
    private function incrementProgress(): void
    {
        if (!$this->syncQueueId || !Schema::hasTable('sync_queue')) {
            return;
        }

        try {
            DB::table('sync_queue')
                ->where('id', $this->syncQueueId)
                ->increment('processed_files');

            // Check if all files are processed → mark completed
            $record = DB::table('sync_queue')->where('id', $this->syncQueueId)->first();
            if ($record && $record->processed_files >= $record->total_files && $record->total_files > 0) {
                DB::table('sync_queue')->where('id', $this->syncQueueId)->update([
                    'status'       => 'completed',
                    'completed_at' => now(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning("Failed to increment sync progress: " . $e->getMessage());
        }
    }

    public function failed(\Throwable $exception): void
    {
        $fileId = $this->driveFile['id'] ?? 'unknown';
        Log::error("ImportSingleDrivePhotoJob permanently failed: {$fileId} for event #{$this->eventId}", [
            'error' => $exception->getMessage(),
        ]);
    }
}

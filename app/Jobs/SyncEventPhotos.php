<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class SyncEventPhotos implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Maximum number of Laravel queue attempts */
    public int $tries = 3;

    /** Timeout in seconds */
    public int $timeout = 120;

    /**
     * Seconds to wait between retries. The Drive listFolderFiles call is
     * the usual culprit when this job fails — wait for the rate-limit
     * window to clear before hitting the API again.
     */
    public int $backoff = 60;

    public function __construct(
        public int    $eventId,
        public string $driveFolderId
    ) {}

    public function handle(): void
    {
        try {
            // Attempt to sync via GoogleDriveService if available
            $photos = [];

            if (app()->bound(\App\Services\GoogleDriveService::class)) {
                /** @var \App\Services\GoogleDriveService $drive */
                $drive  = app(\App\Services\GoogleDriveService::class);
                $photos = $drive->listFolderFiles($this->driveFolderId) ?? [];
            }

            // Store / update in event_photos_cache
            if (Schema::hasTable('event_photos_cache')) {
                $existing = DB::table('event_photos_cache')
                    ->where('event_id', $this->eventId)
                    ->first();

                $photoData = json_encode($photos);
                $expiresAt = now()->addHours(6);

                if ($existing) {
                    DB::table('event_photos_cache')
                        ->where('event_id', $this->eventId)
                        ->update([
                            'photo_data' => $photoData,
                            'cached_at'  => now(),
                            'expires_at' => $expiresAt,
                        ]);
                } else {
                    DB::table('event_photos_cache')->insert([
                        'event_id'   => $this->eventId,
                        'photo_data' => $photoData,
                        'cached_at'  => now(),
                        'expires_at' => $expiresAt,
                    ]);
                }
            }

            // Update sync_queue record to completed
            if (Schema::hasTable('sync_queue')) {
                DB::table('sync_queue')
                    ->where('event_id', $this->eventId)
                    ->where('job_type', 'sync_photos')
                    ->where('status', 'processing')
                    ->update([
                        'status'       => 'completed',
                        'processed_at' => now(),
                    ]);
            }

            Log::info("SyncEventPhotos: event {$this->eventId} synced " . count($photos) . ' photos.');
        } catch (\Throwable $e) {
            Log::error("SyncEventPhotos failed for event {$this->eventId}: " . $e->getMessage());
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("SyncEventPhotos job permanently failed for event {$this->eventId}: " . $exception->getMessage());

        try {
            if (Schema::hasTable('sync_queue')) {
                DB::table('sync_queue')
                    ->where('event_id', $this->eventId)
                    ->where('job_type', 'sync_photos')
                    ->where('status', 'processing')
                    ->update([
                        'status'        => 'failed',
                        'error_message' => mb_substr($exception->getMessage(), 0, 500),
                        'processed_at'  => now(),
                    ]);
            }
        } catch (\Throwable) {
            // Silently ignore
        }
    }
}

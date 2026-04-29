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

class ProcessPhotoCache implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Maximum number of Laravel queue attempts */
    public int $tries = 3;

    /** Timeout in seconds */
    public int $timeout = 120;

    /**
     * Seconds to wait between retries. Intentionally longer than the
     * Drive API's typical rate-limit cooldown (~30s) so retries arrive
     * after quotas reset instead of piling on and burning the next budget.
     */
    public int $backoff = 60;

    public function __construct(public int $eventId) {}

    public function handle(): void
    {
        try {
            if (!Schema::hasTable('event_photos_cache')) {
                return;
            }

            // Pull cached photos
            $cacheRow = DB::table('event_photos_cache')
                ->where('event_id', $this->eventId)
                ->first();

            $photos = $cacheRow ? json_decode($cacheRow->photo_data ?? '[]', true) : [];

            // Process each photo: build metadata (thumbnail URL, name, size, etc.)
            $processed = [];
            foreach ((array) $photos as $photo) {
                $processed[] = [
                    'id'        => $photo['id']        ?? null,
                    'name'      => $photo['name']      ?? '',
                    'thumbnail' => $photo['thumbnail']  ?? ($photo['webContentLink'] ?? ''),
                    'mime_type' => $photo['mimeType']  ?? 'image/jpeg',
                    'size'      => $photo['size']       ?? 0,
                    'cached_at' => now()->toIso8601String(),
                ];
            }

            // Update / insert the processed cache
            $photoData = json_encode($processed);
            $expiresAt = now()->addHours(12);

            if ($cacheRow) {
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

            // Mark sync_queue record as completed
            if (Schema::hasTable('sync_queue')) {
                DB::table('sync_queue')
                    ->where('event_id', $this->eventId)
                    ->where('job_type', 'cache_photos')
                    ->where('status', 'processing')
                    ->update([
                        'status'       => 'completed',
                        'processed_at' => now(),
                    ]);
            }

            Log::info("ProcessPhotoCache: cached " . count($processed) . " photos for event {$this->eventId}.");
        } catch (\Throwable $e) {
            Log::error("ProcessPhotoCache failed for event {$this->eventId}: " . $e->getMessage());
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("ProcessPhotoCache job permanently failed for event {$this->eventId}: " . $exception->getMessage());

        try {
            if (Schema::hasTable('sync_queue')) {
                DB::table('sync_queue')
                    ->where('event_id', $this->eventId)
                    ->where('job_type', 'cache_photos')
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

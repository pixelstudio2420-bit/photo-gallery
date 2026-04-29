<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class QueueService
{
    /**
     * Add a job to the sync_queue table.
     *
     * @return int  The new record ID (or 0 on failure)
     */
    public function dispatch(string $jobType, int $eventId, array $payload = []): int
    {
        try {
            if (!Schema::hasTable('sync_queue')) {
                return 0;
            }

            return (int) DB::table('sync_queue')->insertGetId([
                'event_id'     => $eventId,
                'job_type'     => $jobType,
                'payload'      => json_encode($payload),
                'status'       => 'pending',
                'attempts'     => 0,
                'max_attempts' => 3,
                'error_message'=> null,
                'created_at'   => now(),
                'processed_at' => null,
            ]);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Get queue status counts.
     *
     * @return array{pending:int, processing:int, completed:int, failed:int}
     */
    public function getStatus(): array
    {
        $default = ['pending' => 0, 'processing' => 0, 'completed' => 0, 'failed' => 0];

        try {
            if (!Schema::hasTable('sync_queue')) {
                return $default;
            }

            $rows = DB::table('sync_queue')
                ->selectRaw('status, COUNT(*) as cnt')
                ->groupBy('status')
                ->get()
                ->pluck('cnt', 'status')
                ->toArray();

            return [
                'pending'    => (int) ($rows['pending']    ?? 0),
                'processing' => (int) ($rows['processing'] ?? 0),
                'completed'  => (int) ($rows['completed']  ?? 0),
                'failed'     => (int) ($rows['failed']     ?? 0),
            ];
        } catch (\Throwable $e) {
            return $default;
        }
    }

    /**
     * Get recent jobs (joined with events for name).
     */
    public function getRecentJobs(int $limit = 20): Collection
    {
        try {
            if (!Schema::hasTable('sync_queue')) {
                return collect();
            }

            return DB::table('sync_queue as sq')
                ->leftJoin('event_events as ee', 'sq.event_id', '=', 'ee.id')
                ->select('sq.*', 'ee.title as event_name')
                ->orderByDesc('sq.created_at')
                ->limit($limit)
                ->get();
        } catch (\Throwable $e) {
            return collect();
        }
    }

    /**
     * Retry a failed job by resetting it to pending.
     */
    public function retry(int $jobId): bool
    {
        try {
            if (!Schema::hasTable('sync_queue')) {
                return false;
            }

            $updated = DB::table('sync_queue')
                ->where('id', $jobId)
                ->where('status', 'failed')
                ->update([
                    'status'        => 'pending',
                    'attempts'      => 0,
                    'error_message' => null,
                ]);

            return $updated > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Clear completed jobs older than X days.
     *
     * @return int  Number of deleted rows
     */
    public function cleanup(int $days = 7): int
    {
        try {
            if (!Schema::hasTable('sync_queue')) {
                return 0;
            }

            return DB::table('sync_queue')
                ->where('status', 'completed')
                ->where('created_at', '<', now()->subDays($days))
                ->delete();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Process the next pending job in the queue.
     *
     * @return bool  True if a job was processed, false otherwise
     */
    public function processNext(): bool
    {
        try {
            if (!Schema::hasTable('sync_queue')) {
                return false;
            }

            // Claim the oldest pending job that hasn't exceeded max_attempts
            $job = DB::table('sync_queue')
                ->where('status', 'pending')
                ->whereColumn('attempts', '<', 'max_attempts')
                ->orderBy('created_at')
                ->first();

            if (!$job) {
                return false;
            }

            // Mark as processing
            DB::table('sync_queue')
                ->where('id', $job->id)
                ->where('status', 'pending')   // guard against race conditions
                ->update([
                    'status'   => 'processing',
                    'attempts' => $job->attempts + 1,
                ]);

            try {
                $this->executeJob($job);

                DB::table('sync_queue')->where('id', $job->id)->update([
                    'status'       => 'completed',
                    'processed_at' => now(),
                    'error_message'=> null,
                ]);
            } catch (\Throwable $e) {
                $newAttempts = $job->attempts + 1;
                $newStatus   = ($newAttempts >= $job->max_attempts) ? 'failed' : 'pending';

                DB::table('sync_queue')->where('id', $job->id)->update([
                    'status'        => $newStatus,
                    'error_message' => mb_substr($e->getMessage(), 0, 500),
                    'processed_at'  => now(),
                ]);
            }

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Get progress info for a specific sync_queue record.
     */
    public function getProgress(int $queueId): ?object
    {
        try {
            if (!Schema::hasTable('sync_queue')) {
                return null;
            }

            return DB::table('sync_queue as sq')
                ->leftJoin('event_events as ee', 'sq.event_id', '=', 'ee.id')
                ->select('sq.*', 'ee.title as event_name')
                ->where('sq.id', $queueId)
                ->first();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Get active import progress for an event.
     */
    public function getEventImportProgress(int $eventId): ?object
    {
        try {
            if (!Schema::hasTable('sync_queue')) {
                return null;
            }

            return DB::table('sync_queue')
                ->where('event_id', $eventId)
                ->whereIn('job_type', ['import_drive_photos', 'sync_photos'])
                ->whereIn('status', ['pending', 'processing'])
                ->orderByDesc('created_at')
                ->first();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Execute a specific job based on its type.
     */
    private function executeJob(object $job): void
    {
        $payload = json_decode($job->payload ?? '{}', true) ?? [];

        switch ($job->job_type) {
            case 'sync_photos':
                $driveFolderId = $payload['drive_folder_id'] ?? null;
                if ($driveFolderId) {
                    \App\Jobs\SyncEventPhotos::dispatchSync($job->event_id, $driveFolderId);
                }
                break;

            case 'cache_photos':
                \App\Jobs\ProcessPhotoCache::dispatchSync($job->event_id);
                break;

            case 'import_drive_photos':
                $driveFolderId = $payload['drive_folder_id'] ?? null;
                if ($driveFolderId) {
                    \App\Jobs\ImportDrivePhotosJob::dispatchSync(
                        $job->event_id,
                        $driveFolderId,
                        $job->id
                    );
                }
                break;

            case 'process_uploaded_photo':
                $photoId = $payload['photo_id'] ?? null;
                if ($photoId) {
                    \App\Jobs\ProcessUploadedPhotoJob::dispatchSync($photoId);
                }
                break;

            default:
                // Unknown job type — mark as complete so it doesn't block the queue
                break;
        }
    }
}

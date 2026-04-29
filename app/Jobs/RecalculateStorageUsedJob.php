<?php

namespace App\Jobs;

use App\Models\PhotographerProfile;
use App\Services\StorageQuotaService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Nightly reconciliation for per-photographer storage_used_bytes.
 *
 * Why a batch job, not live accounting only:
 *   • Mass deletes (PurgeEventJob cascade, DB constraint cascade, admin
 *     batch purge) don't fire the EventPhoto model's `deleted` event, so
 *     the counter drifts.
 *   • Failed uploads that got partially written to R2 but didn't create
 *     the EventPhoto row leave byte usage we've never accounted for.
 *     The recalc sums the DB truth, which is what the admin trusts.
 *
 * Execution model: if no specific photographerUserId is passed, the job
 * walks ALL photographer_profiles and dispatches itself per-profile. This
 * keeps each individual run short (< 1s) while allowing the scheduler
 * to fire one command nightly that fans out to the queue.
 */
class RecalculateStorageUsedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 2;
    public int $timeout = 120;

    public function __construct(public ?int $photographerUserId = null)
    {
        // Low-priority maintenance work — use the default queue.
        $this->onQueue('default');
    }

    public function handle(StorageQuotaService $quota): void
    {
        if ($this->photographerUserId) {
            $this->recalcOne($quota, $this->photographerUserId);
            return;
        }

        // Fan-out: one job per photographer so a single slow sum() doesn't
        // starve the queue worker.
        PhotographerProfile::select('user_id')->chunk(200, function ($rows) {
            foreach ($rows as $row) {
                self::dispatch((int) $row->user_id);
            }
        });
    }

    private function recalcOne(StorageQuotaService $quota, int $userId): void
    {
        $profile = PhotographerProfile::where('user_id', $userId)->first();
        if (!$profile) return;

        try {
            $before = (int) $profile->storage_used_bytes;
            $after  = $quota->recalculate($profile);
            $drift  = $after - $before;

            if (abs($drift) > 1024 * 1024) {
                Log::info('StorageQuota recalc drift detected', [
                    'user_id' => $userId,
                    'before'  => $before,
                    'after'   => $after,
                    'drift'   => $drift,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('RecalculateStorageUsedJob failed', [
                'user_id' => $userId,
                'error'   => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}

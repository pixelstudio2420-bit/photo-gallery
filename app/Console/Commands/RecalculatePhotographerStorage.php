<?php

namespace App\Console\Commands;

use App\Jobs\RecalculateStorageUsedJob;
use App\Models\PhotographerProfile;
use App\Services\StorageQuotaService;
use Illuminate\Console\Command;

/**
 * Force-reconcile storage_used_bytes for photographers.
 *
 *   photographers:recalc-storage              → dispatch a job per photographer
 *   photographers:recalc-storage --sync       → run inline, one by one
 *   photographers:recalc-storage --user=42    → just this user_id
 *   photographers:recalc-storage --tier=seller → scoped to a single tier
 *
 * Expected to run nightly via the scheduler — runs inline in < 1s per
 * photographer on typical datasets.
 */
class RecalculatePhotographerStorage extends Command
{
    protected $signature = 'photographers:recalc-storage
        {--sync     : Run inline instead of dispatching to the queue}
        {--user=    : Only recalc this specific photographer user_id}
        {--tier=    : Only recalc photographers in this tier (creator|seller|pro)}';

    protected $description = 'คำนวณ storage_used_bytes ของช่างภาพใหม่จากข้อมูลใน event_photos (กัน drift)';

    public function handle(StorageQuotaService $quota): int
    {
        $userId = $this->option('user') ? (int) $this->option('user') : null;
        $tier   = $this->option('tier');
        $sync   = (bool) $this->option('sync');

        $q = PhotographerProfile::query()->select(['id', 'user_id', 'display_name', 'tier', 'storage_used_bytes']);
        if ($userId) $q->where('user_id', $userId);
        if ($tier)   $q->where('tier', $tier);

        $total = (clone $q)->count();
        if ($total === 0) {
            $this->warn('ไม่มี photographer ที่ตรงเงื่อนไข');
            return self::SUCCESS;
        }

        $this->line("Recalculating storage for {$total} photographer(s)…");

        $processed = 0;
        $q->chunk(200, function ($rows) use ($quota, $sync, &$processed) {
            foreach ($rows as $p) {
                if ($sync) {
                    $before = (int) $p->storage_used_bytes;
                    $after  = $quota->recalculate($p);
                    $delta  = $after - $before;
                    $sign   = $delta >= 0 ? '+' : '';
                    $this->line(sprintf(
                        '  #%d %-30s %s → %s (%s%s)',
                        $p->user_id,
                        $this->truncate((string) $p->display_name ?? ('user#' . $p->user_id), 30),
                        $quota->humanBytes($before),
                        $quota->humanBytes($after),
                        $sign,
                        $quota->humanBytes(abs($delta))
                    ));
                } else {
                    RecalculateStorageUsedJob::dispatch((int) $p->user_id);
                }
                $processed++;
            }
        });

        // Flush admin snapshot cache so the change is visible immediately.
        $quota->flushAdminCache();

        $this->newLine();
        $this->info(
            $sync
                ? "✓ Recalculated {$processed} photographer(s) inline"
                : "✓ Dispatched {$processed} job(s) to queue — monitor with `queue:work`"
        );
        return self::SUCCESS;
    }

    private function truncate(string $s, int $n): string
    {
        return mb_strlen($s) > $n ? mb_substr($s, 0, $n - 1) . '…' : $s;
    }
}

<?php

namespace App\Console\Commands;

use App\Jobs\MirrorPhotoJob;
use App\Models\EventPhoto;
use App\Services\StorageManager;
use Illuminate\Console\Command;

/**
 * Bulk-migrate / mirror photos between storage drivers.
 *
 * Usage:
 *
 *   # Mirror all photos currently on Drive to R2, 200 per run
 *   php artisan photos:migrate-storage --from=drive --to=r2 --limit=200
 *
 *   # Preview what would move for a specific event
 *   php artisan photos:migrate-storage --from=public --to=r2 --event=42 --dry-run
 *
 *   # Mirror uploads on R2 into S3 as additional backup
 *   php artisan photos:migrate-storage --from=r2 --to=s3
 *
 * Actual copy + DB update happens inside MirrorPhotoJob so failures retry
 * cleanly and the web tier never blocks.
 */
class MigratePhotoStorageCommand extends Command
{
    protected $signature = 'photos:migrate-storage
                            {--from=     : Source driver (r2|s3|drive|public)}
                            {--to=       : Target driver (r2|s3|drive|public)}
                            {--event=    : Limit to a single event ID}
                            {--limit=500 : Max photos per run}
                            {--dry-run   : Preview without dispatching jobs}
                            {--sync      : Run in-process instead of dispatching to queue}';

    protected $description = 'ย้าย / มิเรอร์ภาพระหว่าง storage drivers (r2/s3/drive/public)';

    public function handle(StorageManager $storage): int
    {
        $from  = $this->option('from');
        $to    = $this->option('to');
        $event = $this->option('event');
        $limit = (int) $this->option('limit');
        $dry   = (bool) $this->option('dry-run');
        $sync  = (bool) $this->option('sync');

        if (!$from || !$to) {
            $this->error('ต้องระบุทั้ง --from และ --to');
            return self::INVALID;
        }
        if ($from === $to) {
            $this->error('--from และ --to ต้องต่างกัน');
            return self::INVALID;
        }

        $valid = ['r2', 's3', 'drive', 'public'];
        if (!in_array($from, $valid, true) || !in_array($to, $valid, true)) {
            $this->error('--from / --to ต้องเป็น: ' . implode('|', $valid));
            return self::INVALID;
        }

        if (!$storage->driverIsEnabled($to)) {
            $this->error("ปลายทาง [{$to}] ปิดอยู่หรือยังไม่ตั้งค่า credential");
            return self::INVALID;
        }

        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info(" Photo Storage Migration  " . ($dry ? '[DRY RUN]' : ''));
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->line(" From         : {$from}");
        $this->line(" To           : {$to}");
        $this->line(" Event filter : " . ($event ?: 'all'));
        $this->line(" Limit        : {$limit}");
        $this->line(" Mode         : " . ($dry ? 'dry-run' : ($sync ? 'sync' : 'queue')));
        $this->newLine();

        $q = EventPhoto::query()
            ->where('status', 'active')
            ->where(function ($w) use ($from) {
                $w->where('storage_disk', $from);
                if ($from === 'drive') {
                    $w->orWhereNotNull('drive_file_id');
                }
            });

        if ($event) $q->where('event_id', (int) $event);

        // Exclude ones already mirrored to target to keep runs idempotent
        $q->where(function ($w) use ($to) {
            $w->whereNull('storage_mirrors')
              ->orWhereRaw("JSON_SEARCH(storage_mirrors, 'one', ?) IS NULL", [$to]);
        });

        $total = (clone $q)->count();
        $picked = (clone $q)->orderBy('id')->limit($limit)->get(['id', 'event_id', 'original_path', 'storage_disk']);

        $this->line(" Candidates   : {$total}");
        $this->line(" Picking      : " . $picked->count());
        $this->newLine();

        if ($picked->isEmpty()) {
            $this->info('ไม่มีรูปที่ต้องย้าย');
            return self::SUCCESS;
        }

        $dispatched = 0;
        foreach ($picked as $p) {
            $this->line("  photo #{$p->id} (event {$p->event_id}) {$p->storage_disk} → {$to}");
            if ($dry) continue;

            if ($sync) {
                MirrorPhotoJob::dispatchSync($p->id, [$to]);
            } else {
                MirrorPhotoJob::dispatch($p->id, [$to]);
            }
            $dispatched++;
        }

        $this->newLine();
        $this->line('━━━ Summary ━━━');
        $this->line(" Dispatched : {$dispatched}");
        $this->line(" Mode       : " . ($dry ? 'dry-run' : ($sync ? 'sync' : 'queue')));

        return self::SUCCESS;
    }
}

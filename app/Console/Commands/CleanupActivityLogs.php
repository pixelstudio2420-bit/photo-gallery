<?php

namespace App\Console\Commands;

use App\Services\ActivityLogService;
use Illuminate\Console\Command;

class CleanupActivityLogs extends Command
{
    protected $signature = 'activity-log:cleanup {--days=180 : Delete logs older than N days}';

    protected $description = 'ลบ activity logs ที่เก่ากว่า N วัน';

    public function handle(ActivityLogService $service): int
    {
        $days = (int) $this->option('days');

        if ($days < 1) {
            $this->error('--days ต้องมากกว่า 0');
            return 1;
        }

        $this->info("กำลังลบ activity logs ที่เก่ากว่า {$days} วัน...");

        try {
            $deleted = $service->cleanup($days);
        } catch (\Throwable $e) {
            $this->error('เกิดข้อผิดพลาด: ' . $e->getMessage());
            return 1;
        }

        if ($deleted > 0) {
            $this->info("ลบเรียบร้อย {$deleted} รายการ");
        } else {
            $this->line('ไม่มีรายการที่ต้องลบ');
        }

        return 0;
    }
}

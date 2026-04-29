<?php

namespace App\Console\Commands;

use App\Models\AdminNotification;
use Illuminate\Console\Command;

class CleanupAdminNotifications extends Command
{
    protected $signature = 'notifications:cleanup {--days=90 : Delete READ notifications older than N days}';

    protected $description = 'ลบ admin_notifications ที่อ่านแล้วและเก่ากว่า N วัน เพื่อกัน table โต';

    public function handle(): int
    {
        $days = (int) $this->option('days');

        if ($days < 1) {
            $this->error('--days ต้องมากกว่า 0');
            return self::FAILURE;
        }

        $this->info("กำลังลบ admin notifications ที่อ่านแล้วและเก่ากว่า {$days} วัน...");

        try {
            $deleted = AdminNotification::cleanup($days);
        } catch (\Throwable $e) {
            $this->error('เกิดข้อผิดพลาด: ' . $e->getMessage());
            return self::FAILURE;
        }

        if ($deleted > 0) {
            $this->info("ลบเรียบร้อย {$deleted} รายการ");
        } else {
            $this->line('ไม่มีรายการที่ต้องลบ');
        }

        return self::SUCCESS;
    }
}

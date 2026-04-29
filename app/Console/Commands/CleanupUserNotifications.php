<?php

namespace App\Console\Commands;

use App\Models\UserNotification;
use Illuminate\Console\Command;

/**
 * Mirror of CleanupAdminNotifications but targeting the user_notifications
 * table (which serves both photographer and customer bell icons).
 *
 * Two-tier cleanup policy (defined in UserNotification::cleanup):
 *   1. READ rows older than --days days are deleted (default 90).
 *   2. UNREAD rows older than 1 year are deleted unconditionally —
 *      a forgotten notification from years ago should not keep the
 *      bell counter ticking forever.
 *
 * Scheduled daily at 04:30 in routes/console.php.
 */
class CleanupUserNotifications extends Command
{
    protected $signature = 'notifications:cleanup-users {--days=90 : Delete READ notifications older than N days}';

    protected $description = 'ลบ user_notifications ที่อ่านแล้วเก่ากว่า N วัน + ที่ยังไม่อ่านเก่ากว่า 1 ปี';

    public function handle(): int
    {
        $days = (int) $this->option('days');

        if ($days < 1) {
            $this->error('--days ต้องมากกว่า 0');
            return self::FAILURE;
        }

        $this->info("กำลังลบ user notifications ที่อ่านแล้วและเก่ากว่า {$days} วัน + unread > 1 ปี...");

        try {
            $deleted = UserNotification::cleanup($days);
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

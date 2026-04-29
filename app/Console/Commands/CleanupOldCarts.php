<?php

namespace App\Console\Commands;

use App\Models\AbandonedCart;
use Illuminate\Console\Command;

/**
 * Cleanup old expired/recovered abandoned carts from the database.
 *
 * Suggested schedule: daily at 3 AM.
 *   $schedule->command('carts:cleanup')->dailyAt('03:00');
 */
class CleanupOldCarts extends Command
{
    protected $signature = 'carts:cleanup {--days=30 : Remove carts older than this many days}';
    protected $description = 'ลบ abandoned carts ที่หมดอายุนานแล้วออกจากระบบ';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $threshold = now()->subDays($days);

        $this->info("ลบ abandoned carts ที่เก่ากว่า {$days} วัน (ก่อน {$threshold->format('Y-m-d H:i')})");

        try {
            $deleted = AbandonedCart::whereIn('recovery_status', ['expired', 'recovered'])
                ->where('updated_at', '<=', $threshold)
                ->delete();

            $this->info("ลบแล้ว: {$deleted} รายการ");
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('ล้มเหลว: ' . $e->getMessage());
            \Log::warning('CleanupOldCarts failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}

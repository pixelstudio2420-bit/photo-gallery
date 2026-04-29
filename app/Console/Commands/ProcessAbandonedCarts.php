<?php

namespace App\Console\Commands;

use App\Services\AbandonedCartService;
use App\Services\MailService;
use Illuminate\Console\Command;

/**
 * Send abandoned-cart reminders and expire stale carts.
 *
 * Suggested schedule: every 15 minutes.
 *   $schedule->command('carts:process-abandoned')->everyFifteenMinutes();
 */
class ProcessAbandonedCarts extends Command
{
    protected $signature = 'carts:process-abandoned';
    protected $description = 'ประมวลผล abandoned carts — ส่งเตือนและ expire เก่า';

    public function handle(AbandonedCartService $service, MailService $mail): int
    {
        $this->info('เริ่มประมวลผล abandoned carts...');

        try {
            $result = $service->processReminders($mail);
        } catch (\Throwable $e) {
            $this->error('ล้มเหลว: ' . $e->getMessage());
            \Log::warning('ProcessAbandonedCarts failed: ' . $e->getMessage());
            return self::FAILURE;
        }

        $this->table(
            ['ส่งเตือนครั้งที่ 1', 'ส่งเตือนครั้งที่ 2', 'หมดอายุ'],
            [[
                $result['reminded_1'] ?? 0,
                $result['reminded_2'] ?? 0,
                $result['expired']    ?? 0,
            ]]
        );

        $this->info('เสร็จสิ้น');
        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\Review;
use App\Services\MailService;
use Illuminate\Console\Command;

class SendReviewReminders extends Command
{
    protected $signature = 'reviews:send-reminders
                            {--days=7 : Days after order paid to send reminder}
                            {--dry-run : Preview without sending}';

    protected $description = 'ส่งอีเมลเชิญชวนให้ลูกค้าเขียนรีวิวหลังซื้อเสร็จ';

    public function handle(MailService $mail): int
    {
        $days = (int) $this->option('days');
        $dryRun = (bool) $this->option('dry-run');
        $targetDate = now()->subDays($days);

        $this->info("กำลังหา orders ที่ชำระเงินเมื่อ {$days} วันก่อน...");

        // Find paid orders that are X days old, have no review, and no reminder sent
        $orders = Order::with(['user', 'event.photographerProfile'])
            ->where('status', 'paid')
            ->whereDate('updated_at', '<=', $targetDate->copy()->endOfDay())
            ->whereDate('updated_at', '>=', $targetDate->copy()->subDay()->startOfDay())
            ->whereDoesntHave('items', function ($q) {
                // Skip orders already reviewed
            })
            ->get()
            ->filter(function ($order) {
                // Must have user and not already reviewed this order
                if (!$order->user || !$order->user->email) return false;
                $hasReview = Review::where('order_id', $order->id)->exists();
                return !$hasReview;
            });

        $count = $orders->count();
        $this->info("พบ {$count} orders ที่ควรส่งการเตือน");

        if ($count === 0) {
            return 0;
        }

        if ($dryRun) {
            $this->warn('DRY-RUN MODE — ไม่ส่งอีเมลจริง');
            foreach ($orders as $o) {
                $this->line("  → #{$o->order_number} ({$o->user->email})");
            }
            return 0;
        }

        $sent = 0;
        $bar = $this->output->createProgressBar($count);
        $bar->start();

        foreach ($orders as $order) {
            try {
                $reviewUrl = url('/reviews/create/' . $order->id);

                $mail->reviewReminder(
                    $order->user->email,
                    $order->user->first_name ?? 'ลูกค้า',
                    [
                        'id'                => $order->id,
                        'order_number'      => $order->order_number,
                        'event_name'        => $order->event?->title,
                        'photographer_name' => $order->event?->photographerProfile?->display_name,
                    ],
                    $reviewUrl
                );

                $sent++;
            } catch (\Throwable $e) {
                \Log::warning("Review reminder failed for order {$order->id}: " . $e->getMessage());
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("ส่งเตือนรีวิวเรียบร้อย {$sent}/{$count} รายการ");

        return 0;
    }
}

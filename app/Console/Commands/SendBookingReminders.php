<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Services\BookingService;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Booking reminder dispatcher.
 *
 * Runs every 5 minutes via the Laravel scheduler. Each invocation looks for
 * bookings that fall into one of four reminder windows AND haven't yet
 * received that window's reminder (de-duplicated via the
 * `reminder_*_sent_at` timestamp columns).
 *
 *   T-3 days  → ลูกค้า + ช่างภาพ
 *   T-1 day   → ลูกค้า + ช่างภาพ
 *   T-1 hour  → ช่างภาพ (final prep call)
 *   T-0       → ลูกค้า (day-of greeting)
 *   T+1 day   → ลูกค้า (post-shoot review prompt) — for completed bookings
 *
 * Idempotent: re-running won't double-send. Safe to overlap with other
 * scheduler runs.
 */
class SendBookingReminders extends Command
{
    protected $signature = 'bookings:send-reminders
                            {--dry-run : Preview which reminders would fire without sending}';

    protected $description = 'ส่ง LINE reminder สำหรับ bookings ที่ใกล้ถึงเวลา (3 วัน · 1 วัน · 1 ชม. · วันงาน · หลังงาน)';

    public function handle(BookingService $bookingService): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $now    = now();

        $totals = ['3d' => 0, '1d' => 0, '1h' => 0, 'day' => 0, 'review' => 0];

        $this->info('═══════════════════════════════════════════════');
        $this->info('  Booking Reminder Sweep — ' . $now->format('Y-m-d H:i:s'));
        $this->info('═══════════════════════════════════════════════');

        // ── T-3 days ────────────────────────────────────────────────────
        // Window: bookings 2.5–3.5 days from now (catches the cron tick
        // closest to exactly T-3d, gives ±12h slack so a 5-min cron always
        // hits one tick in the window).
        Booking::where('status', Booking::STATUS_CONFIRMED)
            ->whereNull('reminder_3d_sent_at')
            ->whereBetween('scheduled_at', [
                $now->copy()->addDays(2)->addHours(12),
                $now->copy()->addDays(3)->addHours(12),
            ])
            ->chunk(100, function ($chunk) use ($bookingService, $dryRun, &$totals) {
                foreach ($chunk as $b) {
                    $this->line("  3d  → #{$b->id} ({$b->title}) on {$b->scheduled_at}");
                    if (!$dryRun) $bookingService->sendReminder3Days($b);
                    $totals['3d']++;
                }
            });

        // ── T-1 day ────────────────────────────────────────────────────
        Booking::where('status', Booking::STATUS_CONFIRMED)
            ->whereNull('reminder_1d_sent_at')
            ->whereBetween('scheduled_at', [
                $now->copy()->addHours(20),
                $now->copy()->addHours(28),
            ])
            ->chunk(100, function ($chunk) use ($bookingService, $dryRun, &$totals) {
                foreach ($chunk as $b) {
                    $this->line("  1d  → #{$b->id} ({$b->title})");
                    if (!$dryRun) $bookingService->sendReminder1Day($b);
                    $totals['1d']++;
                }
            });

        // ── T-1 hour ────────────────────────────────────────────────────
        Booking::where('status', Booking::STATUS_CONFIRMED)
            ->whereNull('reminder_1h_sent_at')
            ->whereBetween('scheduled_at', [
                $now->copy()->addMinutes(50),
                $now->copy()->addMinutes(70),
            ])
            ->chunk(100, function ($chunk) use ($bookingService, $dryRun, &$totals) {
                foreach ($chunk as $b) {
                    $this->line("  1h  → #{$b->id} ({$b->title})");
                    if (!$dryRun) $bookingService->sendReminder1Hour($b);
                    $totals['1h']++;
                }
            });

        // ── T-0 (day-of greeting, fires at first cron tick of the shoot day) ──
        Booking::where('status', Booking::STATUS_CONFIRMED)
            ->whereNull('reminder_day_sent_at')
            ->whereDate('scheduled_at', $now->toDateString())
            ->chunk(100, function ($chunk) use ($bookingService, $dryRun, &$totals) {
                foreach ($chunk as $b) {
                    $this->line("  day → #{$b->id} ({$b->title})");
                    if (!$dryRun) $bookingService->sendReminderDayOf($b);
                    $totals['day']++;
                }
            });

        // ── T+1 day post-shoot review prompt ───────────────────────────
        Booking::where('status', Booking::STATUS_COMPLETED)
            ->whereNull('post_shoot_review_sent_at')
            ->where('completed_at', '<=', $now->copy()->subHours(20))
            ->where('completed_at', '>=', $now->copy()->subHours(28))
            ->chunk(100, function ($chunk) use ($bookingService, $dryRun, &$totals) {
                foreach ($chunk as $b) {
                    $this->line("  rev → #{$b->id} ({$b->title})");
                    if (!$dryRun) $bookingService->sendPostShootReviewPrompt($b);
                    $totals['review']++;
                }
            });

        $sum = array_sum($totals);
        $this->newLine();
        $this->info(sprintf(
            '  Sent: %d total  (3d:%d · 1d:%d · 1h:%d · day:%d · review:%d) %s',
            $sum,
            $totals['3d'], $totals['1d'], $totals['1h'], $totals['day'], $totals['review'],
            $dryRun ? '[DRY-RUN]' : ''
        ));

        return self::SUCCESS;
    }
}

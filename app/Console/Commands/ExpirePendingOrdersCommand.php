<?php

namespace App\Console\Commands;

use App\Services\OrderExpireService;
use Illuminate\Console\Command;

/**
 * orders:expire-pending — runs every minute via routes/console.php.
 *
 * Auto-cancels orders whose `payment_expires_at` has passed. Without
 * this, expired pending orders stayed visible in /profile/orders with
 * a stuck "pending" status until manually cleaned up — confusing for
 * customers ("did I pay?") and ops ("did this complete?").
 *
 * The actual cancel logic lives in OrderExpireService::sweepExpired()
 * — this command is a thin Artisan wrapper around it. Keeps the
 * scheduled-task surface area small and lets the same logic be called
 * from controllers / API endpoints / tests without spawning a process.
 *
 * Idempotency: the service guards each order with a row-level lock
 * + status re-check inside the transaction, so concurrent runs (e.g.
 * cron firing while a controller also touches the order) can never
 * double-cancel or undo a paid order.
 */
class ExpirePendingOrdersCommand extends Command
{
    protected $signature   = 'orders:expire-pending';
    protected $description = 'Auto-cancel pending orders whose payment window has passed';

    public function handle(OrderExpireService $service): int
    {
        $start = microtime(true);
        $count = $service->sweepExpired();
        $ms    = (int) ((microtime(true) - $start) * 1000);

        if ($count > 0) {
            $this->info("Cancelled {$count} expired pending order(s) in {$ms}ms");
        } else {
            $this->line("No expired orders found ({$ms}ms)");
        }
        return self::SUCCESS;
    }
}

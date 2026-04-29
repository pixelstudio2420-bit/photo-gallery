<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-shot backfill for the `orders.delivered_at` regression.
 *
 * Background: when LINE delivery moved from sync to queued, the
 * channel-specific deliverer started returning status='sent' instead
 * of status='delivered'. PhotoDeliveryService::deliver() only stamped
 * `orders.delivered_at` when status === 'delivered', so LINE-delivered
 * orders accumulated a NULL delivered_at field — breaking the admin
 * "delivered orders this week" pivot.
 *
 * The DeliverOrderViaLineJob fix takes care of NEW orders. This
 * command repairs the EXISTING data: orders that are already paid
 * + delivery_method=line + delivery_status in ('sent','delivered')
 * but have delivered_at = NULL get their delivered_at backfilled to
 * `updated_at` (the closest proxy for when delivery was attempted).
 *
 * Safety
 * ------
 * - Idempotent: skips rows that already have delivered_at set.
 * - --dry-run shows what would be touched without writing.
 * - --limit caps the affected row count so a runaway query doesn't
 *   lock orders for too long on big tables.
 * - The actual update is wrapped in a transaction.
 */
class BackfillOrderDeliveredAtCommand extends Command
{
    protected $signature   = 'orders:backfill-delivered-at
                                {--dry-run : Show counts without writing}
                                {--limit=10000 : Maximum rows to touch in this run}';
    protected $description = 'Backfill delivered_at on LINE-delivered orders that lost the timestamp';

    public function handle(): int
    {
        $dry   = (bool) $this->option('dry-run');
        $limit = max(1, (int) $this->option('limit'));

        $query = DB::table('orders')
            ->where('delivery_method', 'line')
            ->whereIn('delivery_status', ['sent', 'delivered'])
            ->whereNull('delivered_at')
            ->limit($limit);

        $count = (clone $query)->count();
        if ($count === 0) {
            $this->info('Nothing to backfill — no LINE orders have delivery_status set without delivered_at.');
            return self::SUCCESS;
        }

        $this->line(sprintf(
            'Found %d LINE-delivered order%s with NULL delivered_at%s',
            $count,
            $count === 1 ? '' : 's',
            $dry ? ' (dry-run)' : '',
        ));

        if ($dry) {
            // Show a sample so the operator can sanity-check before
            // committing to a write.
            $sample = (clone $query)->select(['id', 'order_number', 'delivery_status', 'updated_at'])
                ->orderBy('id')
                ->limit(5)->get();
            foreach ($sample as $row) {
                $this->line(sprintf(
                    '  #%d %s status=%s updated_at=%s',
                    $row->id, $row->order_number ?: '(no number)',
                    $row->delivery_status, $row->updated_at,
                ));
            }
            return self::SUCCESS;
        }

        // Update in one statement — Postgres + sqlite + mysql all support
        // setting a column to another column's value.
        $touched = DB::transaction(fn () => $query->update([
            'delivered_at' => DB::raw('updated_at'),
        ]));

        $this->info(sprintf('Backfilled %d row%s.', $touched, $touched === 1 ? '' : 's'));
        return self::SUCCESS;
    }
}

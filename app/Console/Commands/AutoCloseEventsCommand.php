<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Services\EventLifecycleNotifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Hourly tick that flips active|published → closed when sales_ends_at
 * has passed, and pings photographers 24h before the scheduled close
 * so they can extend or cancel.
 *
 *   php artisan events:auto-close
 *
 * Schedule: hourly. Idempotent — once an event is at status='closed'
 * the query filter excludes it so re-runs cost nothing.
 *
 * Two batches per tick:
 *   1. Imminent  (24h-ish from now) → photographer-side reminder only
 *   2. Past-due (sales_ends_at < now()) → flip status + notify buyers
 *
 * The 24h reminder is rate-limited via a notifications table refid
 * uniqueness check inside UserNotification::notify so a photographer
 * can't get spammed every hour for 24 ticks.
 */
class AutoCloseEventsCommand extends Command
{
    protected $signature = 'events:auto-close {--dry : Preview without writes}';
    protected $description = 'Auto-close events whose sales_ends_at has passed, plus ping owners 24h ahead.';

    public function handle(EventLifecycleNotifier $notifier): int
    {
        $now = now();
        $dry = (bool) $this->option('dry');

        // ── 1) Imminent close warning (12-26h ahead window) ──────────
        // Window is wider than 24h on both sides so the hourly cron
        // catches every event at least once even if a tick is delayed.
        // The notification's ref_id de-dupes within the window.
        $imminent = Event::query()
            ->whereIn('status', ['active', 'published'])
            ->whereNotNull('sales_ends_at')
            ->whereBetween('sales_ends_at', [
                $now->copy()->addHours(12),
                $now->copy()->addHours(26),
            ])
            ->get();

        $this->info("Imminent (24h ahead): {$imminent->count()} event(s)");

        foreach ($imminent as $event) {
            if (!$dry) $notifier->notifyAutoCloseImminent($event);
            $this->line("  · imminent #{$event->id} \"{$event->name}\" → {$event->sales_ends_at->format('d/m H:i')}");
        }

        // ── 2) Past-due close (the actual flip) ──────────────────────
        $due = Event::query()
            ->whereIn('status', ['active', 'published'])
            ->whereNotNull('sales_ends_at')
            ->where('sales_ends_at', '<=', $now)
            ->get();

        $this->info("Past-due (auto-closing): {$due->count()} event(s)");

        $closed = 0;
        $failed = 0;
        foreach ($due as $event) {
            try {
                if (!$dry) {
                    $event->update([
                        'status'    => 'closed',
                        'closed_at' => $now,
                    ]);
                    $notifier->notifySaleClosed($event->fresh());
                }
                $this->line("  · closed #{$event->id} \"{$event->name}\"");
                $closed++;
            } catch (\Throwable $e) {
                $failed++;
                Log::error('events:auto-close failed', [
                    'event_id' => $event->id,
                    'error'    => $e->getMessage(),
                ]);
                $this->error("  ✗ failed #{$event->id}: {$e->getMessage()}");
            }
        }

        $this->info("Done. closed={$closed}, failed={$failed}, imminent_pinged={$imminent->count()}, dry=" . ($dry ? 'yes' : 'no'));
        return self::SUCCESS;
    }
}

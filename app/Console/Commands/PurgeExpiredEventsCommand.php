<?php

namespace App\Console\Commands;

use App\Jobs\PurgeEventJob;
use App\Jobs\PurgeEventOriginalsJob;
use App\Models\AppSetting;
use App\Models\Event;
use Illuminate\Console\Command;

/**
 * Auto-manage events whose retention window has elapsed.
 *
 * Two modes (controlled by AppSetting `event_retention_mode`):
 *   • portfolio (default) → dispatch {@see PurgeEventOriginalsJob} which
 *       wipes ONLY the original photo files but keeps the cover + thumbnails
 *       + watermarked previews. The event becomes a portfolio entry on the
 *       photographer's profile.
 *   • full                → dispatch {@see PurgeEventJob} which wipes
 *       everything (original behaviour before April 2026).
 *
 * Scheduled daily at 02:30 — see routes/console.php.
 *
 * Safety rails
 * ------------
 *   • Refuses to run unless event_auto_delete_enabled = 1 (override: --force).
 *   • Skips events with paid/processing/completed orders (override:
 *     --include-with-orders). Note that with portfolio mode, the safer
 *     behaviour is to ALLOW events with paid orders to be archived — the
 *     orders remain valid and revenue stays attached to the event.
 *   • Honours per-event auto_delete_exempt flag always.
 *   • Events already in portfolio mode are skipped (idempotent).
 *   • Respects event_auto_delete_batch_limit (default 50).
 *   • --dry-run logs what WOULD happen without deleting anything.
 *
 * Typical usage
 * -------------
 *   php artisan events:purge-expired --dry-run
 *   php artisan events:purge-expired --mode=full --days=180
 *   php artisan events:purge-expired --mode=portfolio --event=42
 */
class PurgeExpiredEventsCommand extends Command
{
    protected $signature = 'events:purge-expired
        {--dry-run              : Preview only; do not delete anything}
        {--force                : Run even if event_auto_delete_enabled is OFF}
        {--mode=                : Override retention mode (portfolio|full)}
        {--days=                : Override default retention_days for this run}
        {--include-with-orders  : Also purge events with paid orders (DANGEROUS in full mode)}
        {--limit=               : Cap how many events are dispatched this run}
        {--event=               : Purge only this specific event ID}';

    protected $description = 'ลบ/เก็บผลงานอีเวนต์ที่หมดอายุตาม retention policy (โหมด portfolio = เก็บภาพตัวอย่าง+หน้าปก, full = ลบทั้งหมด)';

    public function handle(): int
    {
        $dryRun          = (bool) $this->option('dry-run');
        $force           = (bool) $this->option('force');
        $includeOrders   = (bool) $this->option('include-with-orders');
        $daysOverride    = $this->option('days') !== null ? (int) $this->option('days') : null;
        $limitOverride   = $this->option('limit') !== null ? (int) $this->option('limit') : null;
        $specificEventId = $this->option('event') !== null ? (int) $this->option('event') : null;
        $modeOverride    = $this->option('mode');

        $enabled   = (bool) AppSetting::get('event_auto_delete_enabled', 0);
        $skipOrders = (bool) AppSetting::get('event_auto_delete_skip_if_orders', 1);
        $purgeDrive = (bool) AppSetting::get('event_auto_delete_purge_drive', 0);
        $limit      = $limitOverride ?? (int) AppSetting::get('event_auto_delete_batch_limit', 50);

        $mode = $modeOverride ?: (string) AppSetting::get('event_retention_mode', 'portfolio');
        $mode = strtolower(trim($mode));
        if (!in_array($mode, ['portfolio', 'full'], true)) {
            $this->error("Invalid --mode='{$mode}'. Use 'portfolio' or 'full'.");
            return self::FAILURE;
        }

        if (!$enabled && !$force && !$specificEventId) {
            $this->warn('ปิดอยู่ (event_auto_delete_enabled=0). ใช้ --force เพื่อลงมือ หรือเปิดที่ Admin → Settings → Retention');
            return self::SUCCESS;
        }

        $this->line('');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->line(' Event Retention Purge  ' . ($dryRun ? '<fg=yellow>[DRY RUN]</>' : ''));
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->line(" Mode               : <fg=cyan>{$mode}</>" . ($mode === 'portfolio' ? ' (keeps preview + cover)' : ' (FULL DELETE)'));
        $this->line(" Limit this run     : {$limit}");
        // Portfolio mode is safe with paid orders — previews stay, so the
        // customer still has a receipt link that displays the watermarked copy.
        $effectiveSkipOrders = $mode === 'full' ? ($includeOrders ? false : $skipOrders) : false;
        $this->line(" Skip w/ orders     : " . ($effectiveSkipOrders ? 'yes' : 'no' . ($mode === 'portfolio' ? ' (portfolio mode — orders preserved)' : '')));
        $this->line(" Also purge Drive   : " . ($purgeDrive ? 'yes' : 'no'));
        if ($daysOverride !== null) {
            $this->line(" Days override      : {$daysOverride}");
        }
        $this->line('');

        // Build candidate set
        $query = Event::query();
        if ($specificEventId) {
            $query->where('id', $specificEventId);
        } else {
            $query->where('auto_delete_exempt', false)
                  ->whereNull('originals_purged_at');  // skip events already archived to portfolio
        }

        $candidates = $query->get();
        $dispatched = 0;
        $skipped    = 0;

        foreach ($candidates as $event) {
            if ($dispatched >= $limit) {
                $this->line("— reached batch limit ({$limit}); remaining candidates deferred to next run");
                break;
            }

            // Compute effective delete date (with optional --days override)
            $eta = $this->effectiveDeleteAt($event, $daysOverride);
            if (!$specificEventId && (!$eta || $eta->isFuture())) {
                continue;
            }

            // Photographers can pin an event to portfolio forever
            if ($event->is_portfolio && $mode === 'full') {
                $this->line("  <fg=yellow>skip</> #{$event->id} \"{$event->name}\" — pinned as portfolio (is_portfolio=1)");
                $skipped++;
                continue;
            }

            // Full-mode only: refuse to wipe events with revenue-bearing orders.
            // Portfolio mode keeps orders valid so we always archive.
            if ($mode === 'full' && !$includeOrders && $skipOrders && $event->hasBlockingOrders()) {
                $this->line("  <fg=yellow>skip</> #{$event->id} \"{$event->name}\" — has paid orders (use --mode=portfolio instead)");
                $skipped++;
                continue;
            }

            $overdue = $eta ? (int) now()->diffInDays($eta, false) : 0;
            $actionLabel = $mode === 'portfolio' ? 'archive' : 'purge';
            $this->line(sprintf(
                "  <fg=%s>%s</> #%d \"%s\" — due %s (%d days ago)",
                $mode === 'portfolio' ? 'blue' : 'red',
                $actionLabel,
                $event->id,
                $this->truncate($event->name, 50),
                $eta?->format('Y-m-d') ?? '-',
                abs($overdue)
            ));

            if ($mode === 'portfolio') {
                if ($dryRun) {
                    PurgeEventOriginalsJob::dispatchSync($event->id, $purgeDrive, true);
                } else {
                    PurgeEventOriginalsJob::dispatch($event->id, $purgeDrive, false);
                }
            } else { // full
                if ($dryRun) {
                    PurgeEventJob::dispatchSync($event->id, $purgeDrive, true);
                } else {
                    PurgeEventJob::dispatch($event->id, $purgeDrive, false);
                }
            }
            $dispatched++;
        }

        // Second pass: portfolio-mode events that have aged out of the
        // "portfolio keep window" can now be hard-deleted if the admin set
        // event_portfolio_keep_days > 0.
        if ($mode === 'full' && !$specificEventId) {
            $this->hardDeleteAgedPortfolio($dryRun, $purgeDrive, $dispatched, $limit);
        }

        $this->line('');
        $this->line("━━━ Summary ━━━");
        $this->line(" Dispatched : <fg=" . ($dispatched ? 'green' : 'gray') . ">{$dispatched}</>");
        $this->line(" Skipped    : {$skipped}");
        $this->line(" Mode       : {$mode}" . ($dryRun ? ' (dry-run)' : ''));
        $this->line('');

        return self::SUCCESS;
    }

    /**
     * Hard-delete portfolio rows older than `event_portfolio_keep_days` days.
     * Only invoked when the admin runs in --mode=full.
     */
    private function hardDeleteAgedPortfolio(bool $dryRun, bool $purgeDrive, int &$dispatched, int $limit): void
    {
        $keepDays = (int) AppSetting::get('event_portfolio_keep_days', 0);
        if ($keepDays <= 0) return;

        $cutoff = now()->subDays($keepDays);
        $rows = Event::whereNotNull('originals_purged_at')
            ->where('originals_purged_at', '<=', $cutoff)
            ->where('is_portfolio', false) // pinned events stay forever
            ->get();

        foreach ($rows as $event) {
            if ($dispatched >= $limit) break;
            $this->line(sprintf(
                "  <fg=red>hard-delete</> #%d \"%s\" — portfolio aged out (%d days old)",
                $event->id,
                $this->truncate($event->name, 50),
                (int) $event->originals_purged_at->diffInDays(now())
            ));
            if ($dryRun) {
                PurgeEventJob::dispatchSync($event->id, $purgeDrive, true);
            } else {
                PurgeEventJob::dispatch($event->id, $purgeDrive, false);
            }
            $dispatched++;
        }
    }

    /**
     * Same rules as Event::effectiveDeleteAt() but honours the --days CLI override.
     *
     * Priority (with CLI flag layered in):
     *   1. --days N                 → wins over everything for the current run
     *   2. auto_delete_exempt       → null (never)
     *   3. auto_delete_at           → explicit date
     *   4. retention_days_override  → per-event setting
     *   5. Event::tierRetentionDays() → per-tier default (retention_days_*)
     *   6. event_default_retention_days → global fallback
     */
    private function effectiveDeleteAt(Event $event, ?int $daysOverride): ?\Illuminate\Support\Carbon
    {
        if ($event->auto_delete_exempt) {
            return null;
        }
        if ($event->auto_delete_at && $daysOverride === null) {
            return \Illuminate\Support\Carbon::parse($event->auto_delete_at);
        }

        $fromField = (string) AppSetting::get('event_auto_delete_from_field', 'shoot_date');
        $base = null;
        if ($fromField === 'shoot_date' && $event->shoot_date) {
            $base = \Illuminate\Support\Carbon::parse($event->shoot_date);
        }
        $base = $base ?: $event->created_at;
        if (!$base) {
            return null;
        }

        // CLI flag wins. Otherwise per-event override. Otherwise tier default.
        if ($daysOverride !== null) {
            $days = (int) $daysOverride;
        } elseif ($event->retention_days_override !== null) {
            $days = (int) $event->retention_days_override;
        } else {
            $days = $event->tierRetentionDays();
        }

        if ($days <= 0) {
            return null;
        }

        return $base->copy()->addDays((int) $days);
    }

    private function truncate(string $s, int $n): string
    {
        return mb_strlen($s) > $n ? mb_substr($s, 0, $n - 1) . '…' : $s;
    }
}

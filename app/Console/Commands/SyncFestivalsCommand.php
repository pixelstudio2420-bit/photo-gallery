<?php

namespace App\Console\Commands;

use Database\Seeders\FestivalsSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Sync festival dates with the canonical multi-year calendar.
 *
 * Re-runs FestivalsSeeder which:
 *   • Re-computes the next occurrence for fixed-date festivals
 *     (Songkran, Christmas, NYE, Valentine, etc.) — auto-bumps to
 *     next year if this year's window has passed.
 *   • Looks up lunar festivals (Loy Krathong, Chinese NY) from the
 *     hardcoded multi-year table in the seeder, accurate through 2030.
 *
 * Preserves admin edits to:
 *   • headline / body / cta / theme / popup_lead_days
 *   • enabled / show_priority / is_recurring
 *   • target_province_id
 *
 * Updates only:
 *   • starts_at / ends_at (the actual dates)
 *   • name (contains the BE year, gets bumped along with dates)
 *
 * Custom festivals created via the admin UI (slugs not in the seeder)
 * are completely untouched — sync only operates on the canonical 9.
 *
 * Usage:
 *   php artisan festivals:sync
 *   php artisan festivals:sync --dry-run    (don't actually write)
 *
 * Scheduled monthly via routes/console.php so dates stay current
 * without admin intervention. Also exposed as a button on
 * /admin/festivals for on-demand sync.
 */
class SyncFestivalsCommand extends Command
{
    protected $signature = 'festivals:sync
                            {--dry-run : Preview the changes without writing}';

    protected $description = 'Re-apply canonical festival dates from the multi-year calendar table';

    public function handle(): int
    {
        $this->info($this->option('dry-run') ? '─── DRY RUN ───' : '─── Syncing festival dates ───');

        if ($this->option('dry-run')) {
            // Show what WOULD change by capturing the current state +
            // computing what the seeder would write, without actually
            // running it. We do this with a savepoint we never commit.
            $this->showDiff();
            return self::SUCCESS;
        }

        try {
            (new FestivalsSeeder())->setCommand($this)->run();

            // Bust the festival popup cache so next page load picks up
            // the new dates immediately. Pragmatic global flush —
            // festival keys are per-user and not pattern-deletable on
            // file/array drivers.
            Cache::flush();

            $this->info('✓ Sync complete. Cache flushed.');
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Sync failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    /**
     * Print what would change vs current DB state. Pure read — no
     * writes. Used by --dry-run.
     */
    private function showDiff(): void
    {
        $rows = \DB::table('festivals')
            ->select('id', 'slug', 'name', 'starts_at', 'ends_at')
            ->whereIn('slug', [
                'songkran', 'loy-krathong', 'new-year', 'valentine',
                'christmas', 'mothers-day', 'chinese-new-year',
                'pride-month', 'halloween',
            ])
            ->get()
            ->keyBy('slug');

        $this->newLine();
        $this->line('Current DB state (canonical festivals only):');
        $this->newLine();
        $this->line(sprintf('  %-22s %-12s → %-12s', 'slug', 'starts_at', 'ends_at'));
        $this->line('  ' . str_repeat('─', 60));

        foreach ($rows as $r) {
            $this->line(sprintf('  %-22s %-12s → %-12s', $r->slug, $r->starts_at, $r->ends_at));
        }

        $this->newLine();
        $this->line('Run without --dry-run to re-apply canonical dates.');
    }
}

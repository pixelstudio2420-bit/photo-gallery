<?php

namespace App\Console\Commands;

use App\Jobs\ModeratePhotoJob;
use App\Models\Event;
use App\Models\EventPhoto;
use App\Services\ImageModerationService;
use Illuminate\Console\Command;

/**
 * Re-run AWS Rekognition content moderation on existing EventPhoto rows.
 *
 * Useful when:
 *   - Moderation was disabled when photos were uploaded and has now been turned on
 *   - Thresholds or enabled categories were changed and you want the decisions recomputed
 *   - You want to rescue auto-rejected photos after relaxing a policy
 *   - You want to sweep a specific event after a quality complaint
 *
 * Usage:
 *   php artisan photos:remoderate 42
 *   php artisan photos:remoderate --all                     # every active event
 *   php artisan photos:remoderate 42 --status=pending       # only pending photos
 *   php artisan photos:remoderate 42 --status=skipped       # rescan previously-skipped
 *   php artisan photos:remoderate --all --dry-run           # report, no queue dispatch
 *   php artisan photos:remoderate 42 --recompute-only       # reuse stored labels, skip AWS
 *   php artisan photos:remoderate 42 --limit=100
 *   php artisan photos:remoderate 42 --force                # re-moderate even approved photos
 */
class RemoderateEvent extends Command
{
    protected $signature = 'photos:remoderate
                            {event_id? : Event ID to re-moderate (omit with --all)}
                            {--all : Re-moderate every active event}
                            {--status= : Only photos with this moderation_status (pending,approved,flagged,rejected,skipped)}
                            {--force : Re-moderate even approved photos (default: skip approved photos)}
                            {--recompute-only : Reuse stored moderation_labels and only recompute the decision with current thresholds}
                            {--dry-run : Show what would happen, do not dispatch jobs}
                            {--limit=0 : Cap the number of photos processed (0 = unlimited)}';

    protected $description = 'Re-run AWS Rekognition content moderation on existing EventPhoto rows';

    public function handle(ImageModerationService $moderator): int
    {
        $dryRun         = (bool) $this->option('dry-run');
        $force          = (bool) $this->option('force');
        $recomputeOnly  = (bool) $this->option('recompute-only');
        $limit          = (int)  $this->option('limit');
        $runAll         = (bool) $this->option('all');
        $statusFilter   = $this->option('status');
        $eventId        = $this->argument('event_id');

        // ─── Validation ───
        if (!$runAll && !$eventId) {
            $this->error('Provide an event_id or use --all.');
            return self::FAILURE;
        }

        $validStatuses = ['pending', 'approved', 'flagged', 'rejected', 'skipped'];
        if ($statusFilter && !in_array($statusFilter, $validStatuses, true)) {
            $this->error("Invalid --status. Allowed: " . implode(', ', $validStatuses));
            return self::FAILURE;
        }

        if (!$dryRun && !$recomputeOnly && !$moderator->isConfigured()) {
            $this->error('AWS Rekognition is not configured. Set aws_key / aws_secret in admin settings, or use --dry-run / --recompute-only.');
            return self::FAILURE;
        }

        // ─── Resolve event(s) ───
        $events = $runAll
            ? Event::where('status', 'active')->orderBy('id')->get(['id', 'name'])
            : collect([Event::find($eventId)])->filter();

        if ($events->isEmpty()) {
            $this->error($runAll ? 'No active events found.' : "Event #{$eventId} not found.");
            return self::FAILURE;
        }

        $this->line('');
        $this->info(sprintf(
            '🔍 Re-moderating %d event(s) [status=%s, force=%s, recompute-only=%s, dry-run=%s, limit=%s]',
            $events->count(),
            $statusFilter ?: 'any',
            $force ? 'yes' : 'no',
            $recomputeOnly ? 'yes' : 'no',
            $dryRun ? 'yes' : 'no',
            $limit ?: 'unlimited'
        ));
        $this->line('');

        // ─── Accumulators ───
        $totals = [
            'events'       => 0,
            'scanned'      => 0,
            'dispatched'   => 0,
            'recomputed'   => 0,
            'decisions'    => [
                'approved' => 0,
                'flagged'  => 0,
                'rejected' => 0,
                'skipped'  => 0,
            ],
            'skipped'      => 0,
            'failed'       => 0,
        ];

        foreach ($events as $event) {
            $totals['events']++;
            $this->line("<fg=cyan>── Event #{$event->id}: {$event->name} ──</>");

            $query = EventPhoto::where('event_id', $event->id)
                ->where('status', 'active');

            if ($statusFilter) {
                $query->where('moderation_status', $statusFilter);
            } elseif (!$force) {
                // Default: don't re-moderate approved photos (wastes Rekognition credits)
                $query->whereIn('moderation_status', ['pending', 'flagged', 'skipped']);
            }

            if ($limit > 0) {
                $remaining = $limit - $totals['scanned'];
                if ($remaining <= 0) {
                    $this->warn("Global limit of {$limit} reached, stopping.");
                    break;
                }
                $query->limit($remaining);
            }

            $photos = $query->orderBy('id')->get();

            if ($photos->isEmpty()) {
                $this->line("   (no photos match the filter)");
                continue;
            }

            $bar = $this->output->createProgressBar($photos->count());
            $bar->setFormat('   %current%/%max% [%bar%] %percent:3s%% — %message%');
            $bar->setMessage('starting…');
            $bar->start();

            foreach ($photos as $photo) {
                $totals['scanned']++;
                $bar->setMessage("photo #{$photo->id}");

                if ($dryRun) {
                    $totals['dispatched']++;
                    $bar->advance();
                    continue;
                }

                try {
                    if ($recomputeOnly) {
                        // Reuse stored labels, recompute decision with current thresholds
                        $labels = $photo->moderation_labels ?: [];
                        if (empty($labels)) {
                            $totals['skipped']++;
                            $bar->advance();
                            continue;
                        }

                        $result = $moderator->decide($labels);

                        $photo->forceFill([
                            'moderation_status'  => $result['decision'],
                            'moderation_score'   => $result['score'],
                            'moderation_labels'  => $labels,
                        ])->save();

                        $totals['recomputed']++;
                        $totals['decisions'][$result['decision']]++;
                    } else {
                        // Queue a full re-moderation (hits Rekognition again)
                        // Reset status to pending so the job knows to work on it
                        $photo->forceFill([
                            'moderation_status'        => 'pending',
                            'moderation_reviewed_by'   => null,
                            'moderation_reviewed_at'   => null,
                            'moderation_reject_reason' => null,
                        ])->save();

                        ModeratePhotoJob::dispatch($photo->id);
                        $totals['dispatched']++;
                    }
                } catch (\Throwable $e) {
                    $totals['failed']++;
                    $this->newLine();
                    $this->warn("   photo #{$photo->id}: " . $e->getMessage());
                }

                $bar->advance();
            }

            $bar->finish();
            $this->newLine(2);
        }

        // ─── Summary ───
        $this->line('');
        $this->line('<fg=green;options=bold>════════ SUMMARY ════════</>');
        $rows = [
            ['Events processed', $totals['events']],
            ['Photos scanned',   $totals['scanned']],
        ];

        if ($recomputeOnly) {
            $rows[] = ['Recomputed',         $totals['recomputed']];
            $rows[] = ['  → approved',       $totals['decisions']['approved']];
            $rows[] = ['  → flagged',        $totals['decisions']['flagged']];
            $rows[] = ['  → rejected',       $totals['decisions']['rejected']];
            $rows[] = ['  → skipped',        $totals['decisions']['skipped']];
        } else {
            $rows[] = ['Dispatched to queue', $totals['dispatched']];
        }

        $rows[] = ['Skipped (no labels)', $totals['skipped']];
        $rows[] = ['Failed',              $totals['failed']];

        $this->table(['Metric', 'Count'], $rows);

        if ($dryRun) {
            $this->warn('DRY RUN — no jobs were dispatched.');
        } elseif (!$recomputeOnly && $totals['dispatched'] > 0) {
            $this->info("✓ {$totals['dispatched']} job(s) queued. Run `php artisan queue:work` to process them.");
        }

        return self::SUCCESS;
    }
}

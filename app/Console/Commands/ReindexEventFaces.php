<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\EventPhoto;
use App\Services\FaceSearchService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Backfill AWS Rekognition face indexing for photos uploaded before auto-indexing
 * was wired into ProcessUploadedPhotoJob, or for events where indexing was disabled
 * and has now been re-enabled.
 *
 * Usage:
 *   php artisan rekognition:reindex-event 42
 *   php artisan rekognition:reindex-event 42 --force      # re-index even if face_id exists
 *   php artisan rekognition:reindex-event --all           # walk every event (careful!)
 *   php artisan rekognition:reindex-event 42 --dry-run    # report-only, no AWS calls
 */
class ReindexEventFaces extends Command
{
    protected $signature = 'rekognition:reindex-event
                            {event_id? : Event ID to reindex (omit with --all)}
                            {--all : Reindex every active event}
                            {--force : Reindex photos even if they already have a face_id}
                            {--dry-run : Show what would happen, do not call AWS}
                            {--limit=0 : Cap the number of photos processed (0 = unlimited)}';

    protected $description = 'Backfill AWS Rekognition face indexing for existing EventPhoto rows';

    public function handle(FaceSearchService $faceSearch): int
    {
        $dryRun  = (bool) $this->option('dry-run');
        $force   = (bool) $this->option('force');
        $limit   = (int)  $this->option('limit');
        $runAll  = (bool) $this->option('all');
        $eventId = $this->argument('event_id');

        // ─── Validation ───
        if (!$runAll && !$eventId) {
            $this->error('Provide an event_id or use --all.');
            return self::FAILURE;
        }

        if (!$dryRun && !$faceSearch->isConfigured()) {
            $this->error('AWS Rekognition is not configured. Set aws_key / aws_secret in admin settings, or use --dry-run.');
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
            '🎯 Reindexing %d event(s) [force=%s, dry-run=%s, limit=%s]',
            $events->count(),
            $force ? 'yes' : 'no',
            $dryRun ? 'yes' : 'no',
            $limit ?: 'unlimited'
        ));
        $this->line('');

        // ─── Accumulators ───
        $totals = [
            'events'  => 0,
            'scanned' => 0,
            'indexed' => 0,
            'skipped' => 0,
            'failed'  => 0,
            'no_face' => 0,
        ];

        foreach ($events as $event) {
            $totals['events']++;
            $this->line("<fg=cyan>── Event #{$event->id}: {$event->name} ──</>");

            $query = EventPhoto::where('event_id', $event->id)
                ->where('status', 'active');

            if (!$force) {
                $query->whereNull('rekognition_face_id');
            }

            if ($limit > 0) {
                $query->limit($limit - $totals['scanned']);
                if (($limit - $totals['scanned']) <= 0) {
                    $this->warn("Global limit of {$limit} reached, stopping.");
                    break;
                }
            }

            $photos = $query->get();

            if ($photos->isEmpty()) {
                $this->line("   (no photos to reindex)");
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
                    $totals['indexed']++;
                    $bar->advance();
                    continue;
                }

                try {
                    // When --force is set, null out existing face_id so indexPhoto proceeds.
                    // We do NOT delete the old face from the collection here — the new index
                    // call replaces it by ExternalImageId.
                    if ($force && $photo->rekognition_face_id) {
                        $photo->forceFill(['rekognition_face_id' => null])->save();
                    }

                    $faceId = $faceSearch->indexPhoto($photo, null);

                    if ($faceId) {
                        $totals['indexed']++;
                    } else {
                        // Could be "no face" or "not configured" or "unreadable"
                        $totals['no_face']++;
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
        $this->table(
            ['Metric', 'Count'],
            [
                ['Events processed', $totals['events']],
                ['Photos scanned',   $totals['scanned']],
                ['Indexed',          $totals['indexed']],
                ['No face detected', $totals['no_face']],
                ['Skipped',          $totals['skipped']],
                ['Failed',           $totals['failed']],
            ]
        );

        if ($dryRun) {
            $this->warn('DRY RUN — no AWS calls were made.');
        }

        return self::SUCCESS;
    }
}

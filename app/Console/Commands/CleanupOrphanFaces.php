<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\EventPhoto;
use App\Services\FaceSearchService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Cleanup orphan faces from AWS Rekognition collections.
 *
 * Runs nightly via the scheduler. For each `event-{id}` collection it:
 *   1. Lists all faces
 *   2. Checks if the ExternalImageId (which is the EventPhoto ID) still
 *      points to an active (not soft-deleted) photo
 *   3. Deletes the orphan face record
 *
 * Also deletes the entire collection if the corresponding event is gone
 * (archived or hard-deleted) — AWS charges for face storage, so don't
 * leave zombie data lying around.
 *
 * Pre-hook: the EventPhoto::deleting hook deletes individual faces as
 * photos are removed one-by-one. This command is a safety net for:
 *   • Photos deleted BEFORE the hook was added (legacy data)
 *   • Hard DB deletes (bypass Eloquent events)
 *   • AWS calls that failed silently at delete-time
 */
class CleanupOrphanFaces extends Command
{
    protected $signature = 'rekognition:cleanup-orphans
                            {--dry-run : Show what would be deleted, do not call AWS}
                            {--event_id= : Limit to one event}';

    protected $description = 'Delete faces in Rekognition collections that no longer correspond to live EventPhoto rows';

    public function handle(FaceSearchService $faceSearch): int
    {
        $dryRun  = (bool) $this->option('dry-run');
        $eventId = $this->option('event_id');

        if (!$faceSearch->isConfigured()) {
            $this->error('AWS Rekognition is not configured. Nothing to clean up.');
            return self::SUCCESS; // not a failure — just nothing to do
        }

        $this->line('');
        $this->info(sprintf('🧹 Cleanup orphan faces  [dry-run=%s, event=%s]',
            $dryRun ? 'yes' : 'no',
            $eventId ?: 'all'
        ));
        $this->line('');

        // ─── Stage 1: find all event-{id} collections in AWS ───
        $collections = $faceSearch->listCollections();
        $eventCollections = array_values(array_filter($collections, fn($c) => str_starts_with($c, 'event-')));

        if ($eventId) {
            $eventCollections = array_filter($eventCollections, fn($c) => $c === "event-{$eventId}");
        }

        if (empty($eventCollections)) {
            $this->warn('No event-* collections found in AWS. Nothing to clean up.');
            return self::SUCCESS;
        }

        $this->info(sprintf('Found %d event-* collection(s) in AWS.', count($eventCollections)));

        // ─── Stage 2: iterate each collection ───
        $totals = [
            'collections_scanned' => 0,
            'faces_scanned'       => 0,
            'faces_deleted'       => 0,
            'collections_deleted' => 0,
            'failed'              => 0,
        ];

        foreach ($eventCollections as $collectionId) {
            $totals['collections_scanned']++;
            $eventIdFromCollection = (int) str_replace('event-', '', $collectionId);

            $this->line("<fg=cyan>── {$collectionId} ──</>");

            // Drop collections for events that no longer exist at all
            $event = Event::find($eventIdFromCollection);
            if (!$event) {
                $this->warn("   Event #{$eventIdFromCollection} no longer exists → DROP collection");
                if (!$dryRun) {
                    if ($faceSearch->deleteCollection($collectionId)) {
                        $totals['collections_deleted']++;
                    } else {
                        $totals['failed']++;
                    }
                } else {
                    $totals['collections_deleted']++;
                }
                continue;
            }

            $faces = $faceSearch->listFaces($collectionId);
            if (empty($faces)) {
                $this->line("   (empty collection — skipping)");
                continue;
            }

            // Pull alive photo IDs for this event in one query (avoids N calls)
            $alivePhotoIds = EventPhoto::where('event_id', $eventIdFromCollection)
                ->where('status', 'active')
                ->pluck('id')
                ->map(fn($id) => (string) $id)
                ->flip(); // flip for O(1) lookup

            $orphans = [];
            foreach ($faces as $face) {
                $totals['faces_scanned']++;
                $extId = (string) ($face['external_id'] ?? '');
                if ($extId === '' || !$alivePhotoIds->has($extId)) {
                    $orphans[] = $face;
                }
            }

            $this->line(sprintf(
                '   faces: %d, alive photos: %d, <fg=yellow>orphans: %d</>',
                count($faces),
                $alivePhotoIds->count(),
                count($orphans)
            ));

            // Delete orphans (up to 4096 per call; in practice our collections are small)
            foreach ($orphans as $orphan) {
                if ($dryRun) {
                    $totals['faces_deleted']++;
                    continue;
                }
                if ($faceSearch->deleteFace($collectionId, $orphan['face_id'])) {
                    $totals['faces_deleted']++;
                } else {
                    $totals['failed']++;
                }
            }
        }

        // ─── Summary ───
        $this->line('');
        $this->line('<fg=green;options=bold>════════ SUMMARY ════════</>');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Collections scanned', $totals['collections_scanned']],
                ['Faces scanned',       $totals['faces_scanned']],
                ['Orphan faces deleted', $totals['faces_deleted']],
                ['Collections deleted', $totals['collections_deleted']],
                ['Failures',            $totals['failed']],
            ]
        );

        if ($dryRun) {
            $this->warn('DRY RUN — no AWS delete calls were made.');
        }

        return self::SUCCESS;
    }
}

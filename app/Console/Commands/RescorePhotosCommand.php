<?php

namespace App\Console\Commands;

use App\Services\PhotoQualityScoringService;
use Illuminate\Console\Command;

class RescorePhotosCommand extends Command
{
    protected $signature   = 'photos:rescore {--event= : Score only a single event id}';
    protected $description = 'Recompute quality scores & ranks for event photos.';

    public function handle(PhotoQualityScoringService $svc): int
    {
        $eventId = $this->option('event');

        if ($eventId) {
            $n = $svc->scoreEvent((int) $eventId);
            $this->info("Event {$eventId}: {$n} photos scored.");
            return self::SUCCESS;
        }

        $result = $svc->scoreAllEvents(function ($i, $total, $eid, $n) {
            $this->line("  [{$i}/{$total}] event {$eid} → {$n} photos");
        });

        $this->info("Done. Events: {$result['events_scored']}, Photos: {$result['photos_scored']}");
        return self::SUCCESS;
    }
}

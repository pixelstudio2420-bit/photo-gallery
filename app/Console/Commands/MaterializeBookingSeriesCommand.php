<?php

namespace App\Console\Commands;

use App\Services\BookingSeriesService;
use Illuminate\Console\Command;

/**
 * Daily run of the recurring-bookings materializer.
 *
 * Walks every active booking_series and creates concrete `bookings`
 * rows for occurrences within the next `materialize_horizon_days`.
 *
 * Idempotent: re-running on the same day creates no new rows because
 * the service skips occurrences that already have a booking row with
 * the same series_id + scheduled_at.
 *
 * Cron registration is in routes/console.php.
 */
class MaterializeBookingSeriesCommand extends Command
{
    protected $signature = 'bookings:materialize-series {--horizon-days=}';
    protected $description = 'Generate concrete bookings for upcoming occurrences of every active series';

    public function handle(BookingSeriesService $svc): int
    {
        $horizon = $this->option('horizon-days') !== null
            ? (int) $this->option('horizon-days')
            : null;
        $result = $svc->materializeAll($horizon);
        $this->line(sprintf(
            'Materialized: created=%d series_processed=%d',
            $result['created'],
            $result['series_processed'],
        ));
        return self::SUCCESS;
    }
}

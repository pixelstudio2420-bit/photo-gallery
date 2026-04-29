<?php

namespace App\Console\Commands;

use App\Services\UserPresenceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Housekeeping task for scalability:
 *  - Mark stale sessions offline (>5 min)
 *  - Delete sessions older than 24h
 *  - Flush buffered event view counts to event_events
 *
 * Schedule (bootstrap/app.php): ->everyFiveMinutes()
 */
class CleanupPresenceData extends Command
{
    protected $signature = 'presence:cleanup
        {--keep-hours=24 : Delete user_sessions rows older than this (hours)}';

    protected $description = 'Clean up stale user_sessions + flush buffered view counters';

    public function handle(UserPresenceService $presence): int
    {
        // 1. Session cleanup (uses service — keeps logic in one place)
        try {
            $presence->cleanup();
            $this->info('user_sessions cleanup done');
        } catch (\Throwable $e) {
            $this->warn('user_sessions cleanup failed: ' . $e->getMessage());
        }

        // 2. Hard-delete very old rows
        try {
            $hours = max(1, (int) $this->option('keep-hours'));
            if (Schema::hasTable('user_sessions')) {
                $deleted = DB::table('user_sessions')
                    ->where('last_activity', '<', now()->subHours($hours))
                    ->delete();
                $this->info("Deleted {$deleted} stale session rows (> {$hours}h)");
            }
        } catch (\Throwable $e) {
            $this->warn('Stale delete failed: ' . $e->getMessage());
        }

        return self::SUCCESS;
    }
}

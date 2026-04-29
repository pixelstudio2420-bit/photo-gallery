<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\UserStorageService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Weekly maintenance: recompute `auth_users.storage_used_bytes` for every
 * user by summing their user_files rows. The hot path (upload / delete /
 * purge) mutates the cached counter incrementally; this command exists
 * to heal drift caused by:
 *   - Failed transactions that partially wrote files
 *   - Admin purges run outside FileManagerService (emergency tooling)
 *   - Direct DB edits during migrations / data fixes
 *
 * Safe to run daily — it's a SUM per user, which is cheap on the indexed
 * (user_id, deleted_at) columns.
 *
 * By default only users with a non-zero cached counter OR at least one
 * file row are touched, to avoid pointlessly locking every row in
 * auth_users on a fresh install.
 */
class UserStorageRecalcQuotasCommand extends Command
{
    protected $signature   = 'user-storage:recalc-quotas
                               {--user= : Only recompute for a single user_id}
                               {--all   : Scan every user row (default: only users with files/cache)}
                               {--dry-run : Show deltas without mutating}';
    protected $description = 'Recompute cached storage_used_bytes on auth_users to heal drift.';

    public function handle(UserStorageService $svc): int
    {
        if (!$svc->systemEnabled()) {
            $this->line('Consumer storage system disabled — skipping.');
            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $only   = $this->option('user');
        $scanAll = (bool) $this->option('all');

        $q = User::query();
        if ($only) {
            $q->where('id', (int) $only);
        } elseif (!$scanAll) {
            // Default: only users who have either files OR a non-zero cached counter
            $q->where(function ($w) {
                $w->where('storage_used_bytes', '>', 0)
                  ->orWhereIn('id', DB::table('user_files')
                      ->whereNull('deleted_at')
                      ->select('user_id'));
            });
        }

        $total    = $q->count();
        $touched  = 0;
        $drifted  = 0;
        $failed   = 0;
        $bytesAdj = 0; // net adjustment across all users (can be negative)

        $this->info("Recomputing storage_used_bytes for {$total} user(s)...");

        $q->chunkById(100, function ($users) use (&$touched, &$drifted, &$failed, &$bytesAdj, $svc, $dryRun) {
            foreach ($users as $u) {
                try {
                    $before = (int) $u->storage_used_bytes;
                    // Always recompute from source rather than trust cache
                    $actual = (int) DB::table('user_files')
                        ->where('user_id', $u->id)
                        ->whereNull('deleted_at')
                        ->sum('size_bytes');

                    if ($before !== $actual) {
                        $drifted++;
                        $delta = $actual - $before;
                        $bytesAdj += $delta;
                        $this->line(sprintf(
                            '  %s user#%d: %s → %s (%s%s B)',
                            $dryRun ? '[dry]' : '  ✓  ',
                            $u->id,
                            number_format($before),
                            number_format($actual),
                            $delta >= 0 ? '+' : '',
                            number_format($delta),
                        ));

                        if (!$dryRun) {
                            $u->forceFill(['storage_used_bytes' => $actual])->save();
                        }
                    }
                    $touched++;
                } catch (\Throwable $e) {
                    $failed++;
                    Log::error('user-storage:recalc-quotas failed for user#'.$u->id, [
                        'error' => $e->getMessage(),
                    ]);
                    $this->error("  ✗ user#{$u->id} — ".$e->getMessage());
                }
            }
        });

        $this->info("Scanned: {$touched} | Drifted: {$drifted} | Failed: {$failed} | Net adjustment: ".number_format($bytesAdj)." B");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}

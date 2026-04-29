<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Daily cleanup of audit tables that grow without bound.
 *
 * Tables we touch
 * ---------------
 * Each row below has a per-table retention because the right TTL
 * depends on the operational use of the table:
 *
 *   line_deliveries          90d   "did user X get the order
 *                                   notification?" rarely needs to
 *                                   look back beyond one quarter.
 *   line_inbound_events      30d   webhook dedup + investigation;
 *                                   month is enough since unique
 *                                   constraint protects retries.
 *   line_inbound_media       180d  bytes are stored on R2; the row
 *                                   is just metadata. Keep longer
 *                                   in case admins want a forensic
 *                                   trail.
 *   booking_calendar_sync    60d   diagnostic for "why didn't this
 *                                   booking sync to Google?".
 *   booking_sheets_exports   60d   same use case.
 *   booking_reminder_claims  30d   reminders fire once; old claims
 *                                   are no longer used.
 *   gcal_watch_channels      stop|expired rows older than 30d
 *                                   (active rows never pruned).
 *   payment_audit_log        180d  longer because finance people
 *                                   might investigate older fraud.
 *   upload_chunks            30d   completed/aborted/expired only;
 *                                   active rows are never pruned.
 *   upload_sessions          30d   same gating as upload_chunks.
 *
 * All retentions are env-overridable so an operator who needs longer
 * windows can extend without code changes.
 *
 * Safety
 * ------
 * - --dry-run shows what would be deleted.
 * - Per-table failure does NOT abort the whole run.
 * - Each delete is bounded by --batch-size so a giant table doesn't
 *   cause a long-running transaction.
 */
class PruneAuditTablesCommand extends Command
{
    protected $signature   = 'audit:prune {--dry-run} {--batch-size=5000}';
    protected $description = 'Delete old rows from audit tables per per-table retention policy';

    /**
     * @var array<int, array{table:string, days_env:string, default_days:int, where?:string}>
     */
    private const TABLES = [
        ['table' => 'line_deliveries',          'days_env' => 'AUDIT_RETENTION_LINE_DELIVERIES',          'default_days' => 90],
        ['table' => 'line_inbound_events',      'days_env' => 'AUDIT_RETENTION_LINE_INBOUND_EVENTS',      'default_days' => 30],
        ['table' => 'line_inbound_media',       'days_env' => 'AUDIT_RETENTION_LINE_INBOUND_MEDIA',       'default_days' => 180],
        ['table' => 'booking_calendar_sync',    'days_env' => 'AUDIT_RETENTION_BOOKING_CAL_SYNC',         'default_days' => 60],
        ['table' => 'booking_sheets_exports',   'days_env' => 'AUDIT_RETENTION_BOOKING_SHEETS_EXPORTS',   'default_days' => 60],
        ['table' => 'booking_reminder_claims',  'days_env' => 'AUDIT_RETENTION_BOOKING_REMINDER_CLAIMS',  'default_days' => 30],
        // gcal_watch_channels: never prune active rows — they're
        // load-bearing live state. Only stop|expired old enough.
        ['table' => 'gcal_watch_channels',      'days_env' => 'AUDIT_RETENTION_GCAL_WATCH',               'default_days' => 30,
         'where' => "status IN ('stopped', 'expired')"],
        ['table' => 'payment_audit_log',        'days_env' => 'AUDIT_RETENTION_PAYMENT_AUDIT',            'default_days' => 180],
        ['table' => 'upload_chunks',            'days_env' => 'AUDIT_RETENTION_UPLOAD_CHUNKS',            'default_days' => 30,
         'where' => "status IN ('completed', 'aborted', 'expired')"],
        ['table' => 'upload_sessions',          'days_env' => 'AUDIT_RETENTION_UPLOAD_SESSIONS',          'default_days' => 30,
         'where' => "status IN ('completed', 'aborted', 'expired')"],
    ];

    public function handle(): int
    {
        $dry  = (bool) $this->option('dry-run');
        $size = max(100, (int) $this->option('batch-size'));

        $totals = ['scanned' => 0, 'deleted' => 0];

        foreach (self::TABLES as $cfg) {
            $table = $cfg['table'];
            if (!Schema::hasTable($table)) {
                $this->warn("skip {$table} (not present)");
                continue;
            }

            $days = (int) (env($cfg['days_env']) ?? $cfg['default_days']);
            if ($days <= 0) {
                $this->warn("skip {$table} (retention=0 means keep forever)");
                continue;
            }

            $cutoff = now()->subDays($days);
            $where  = $cfg['where'] ?? null;

            try {
                $query = DB::table($table)->where('created_at', '<', $cutoff);
                if ($where) {
                    $query->whereRaw($where);
                }
                $count = (clone $query)->count();
                $totals['scanned'] += $count;

                if ($count === 0) {
                    $this->line("ok    {$table}: 0 rows older than {$days}d");
                    continue;
                }

                if ($dry) {
                    $this->info("dry   {$table}: would delete {$count} rows older than {$days}d");
                    continue;
                }

                // Batch the delete so we don't hold a 1M-row transaction.
                $deletedTotal = 0;
                do {
                    $deleted = $query->limit($size)->delete();
                    $deletedTotal += $deleted;
                    if ($deleted > 0) {
                        $this->info(sprintf('  → %s: deleted %d (running total %d)',
                            $table, $deleted, $deletedTotal));
                    }
                } while ($deleted >= $size);

                $totals['deleted'] += $deletedTotal;
                $this->line("done  {$table}: deleted {$deletedTotal} rows older than {$days}d");
            } catch (\Throwable $e) {
                // Per-table failure isolated — keep going.
                $this->error("failed {$table}: " . $e->getMessage());
            }
        }

        $this->line('');
        $this->info(sprintf(
            'Summary: scanned=%d, deleted=%d%s',
            $totals['scanned'], $totals['deleted'], $dry ? ' (dry-run)' : '',
        ));
        return self::SUCCESS;
    }
}

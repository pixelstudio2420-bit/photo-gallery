<?php

namespace App\Console\Commands;

use App\Finance\LedgerService;
use App\Finance\Models\FinancialAccount;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * `php artisan finance:reconcile`
 *
 * Detect drift between:
 *   1. Per-journal:  SUM(DR) == SUM(CR) — must hold for every entry.
 *   2. Per-account:  cached financial_balances == recomputed from journal_lines.
 *   3. Cross-system: existing tables (orders, photographer_payouts) sum
 *      should match the corresponding journal posts (within rounding).
 *
 * Output: human-readable summary + non-zero exit code if drift found,
 * so the cron can alert. Use --strict to fail on cross-system drift
 * even when intra-ledger is clean (useful during the dual-write
 * migration window).
 */
class FinanceReconcileCommand extends Command
{
    protected $signature = 'finance:reconcile
        {--strict : Exit non-zero on cross-system drift, not just intra-ledger drift}
        {--quiet-success : Only print when drift is found}';

    protected $description = 'Verify the financial ledger is internally consistent and matches existing payment tables.';

    public function __construct(private readonly LedgerService $ledger)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $strict   = (bool) $this->option('strict');
        $quiet    = (bool) $this->option('quiet-success');
        $hasDrift = false;
        $hasCritical = false;

        // ────────────────────────────────────────────────────────────
        // 1. Per-journal: every entry must have SUM(DR) == SUM(CR)
        // ────────────────────────────────────────────────────────────
        $unbalanced = DB::table('financial_journal_lines')
            ->groupBy('journal_entry_id')
            ->havingRaw("SUM(CASE WHEN direction='DR' THEN amount_minor ELSE 0 END) <> SUM(CASE WHEN direction='CR' THEN amount_minor ELSE 0 END)")
            ->select('journal_entry_id', DB::raw("SUM(CASE WHEN direction='DR' THEN amount_minor ELSE 0 END) AS dr"), DB::raw("SUM(CASE WHEN direction='CR' THEN amount_minor ELSE 0 END) AS cr"))
            ->get();

        if ($unbalanced->isNotEmpty()) {
            $hasDrift = true;
            $hasCritical = true;
            $this->components->error(sprintf('Found %d unbalanced journal(s) — CRITICAL', $unbalanced->count()));
            foreach ($unbalanced as $row) {
                $this->line(sprintf(
                    '  journal_id=%d  DR=%d satang  CR=%d satang  diff=%d',
                    $row->journal_entry_id,
                    (int) $row->dr,
                    (int) $row->cr,
                    (int) $row->dr - (int) $row->cr,
                ));
            }
        } elseif (!$quiet) {
            $this->components->info('All journals balanced (DR == CR).');
        }

        // ────────────────────────────────────────────────────────────
        // 2. Per-account: cached balance == recomputed from lines
        // ────────────────────────────────────────────────────────────
        $accounts = FinancialAccount::where('is_active', true)->get();
        $balanceDrift = 0;
        foreach ($accounts as $account) {
            $cached     = (int) (DB::table('financial_balances')
                ->where('account_id', $account->id)
                ->value('balance_minor') ?? 0);
            $recomputed = $this->ledger->recomputeBalance($account)->minor;

            if ($cached !== $recomputed) {
                $hasDrift = true;
                $hasCritical = true;
                $balanceDrift++;
                $this->components->error(sprintf(
                    'Balance drift on %s: cached=%d, ledger=%d, diff=%d',
                    $account->account_code, $cached, $recomputed, $cached - $recomputed,
                ));
            }
        }
        if ($balanceDrift === 0 && !$quiet) {
            $this->components->info(sprintf('All %d account balances match the ledger.', $accounts->count()));
        }

        // ────────────────────────────────────────────────────────────
        // 3. Cross-system: existing payment tables vs ledger
        //    Compares SUM(orders.total satang) against SUM(gateway DR
        //    journal lines) for the same period.
        // ────────────────────────────────────────────────────────────
        $crossDrift = $this->checkCrossSystemDrift($strict, $quiet);
        if ($crossDrift > 0) {
            $hasDrift = true;
        }

        if ($hasCritical) {
            return self::FAILURE;
        }
        if ($hasDrift && $strict) {
            return self::FAILURE;
        }
        return self::SUCCESS;
    }

    /**
     * Compare the legacy orders.total sum (this month) against the sum
     * of gateway-receivable DR lines tagged as 'order.paid' for the
     * same period. They should be within 0 satang in steady state.
     *
     * Returns the number of drift signals found (informational unless
     * --strict).
     */
    private function checkCrossSystemDrift(bool $strict, bool $quiet): int
    {
        $drift = 0;

        // Sum of paid orders this month (legacy table).
        // Convert decimal(10,2) → satang via *100; use raw SQL for the
        // multiplication so we don't pull a million rows into PHP.
        $legacyPaidSatang = (int) (DB::table('orders')
            ->where('status', 'paid')
            ->whereRaw("EXTRACT(YEAR FROM created_at) = EXTRACT(YEAR FROM NOW())")
            ->whereRaw("EXTRACT(MONTH FROM created_at) = EXTRACT(MONTH FROM NOW())")
            ->sum(DB::raw('CAST(ROUND(total * 100) AS BIGINT)')) ?? 0);

        // Sum of journal-side gross (gateway DR for type=order.paid).
        $journalPaidSatang = (int) (DB::table('financial_journal_lines as l')
            ->join('financial_journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
            ->join('financial_accounts as a',         'a.id', '=', 'l.account_id')
            ->where('l.direction', 'DR')
            ->where('e.type',      'order.paid')
            ->whereRaw("EXTRACT(YEAR FROM e.posted_at) = EXTRACT(YEAR FROM NOW())")
            ->whereRaw("EXTRACT(MONTH FROM e.posted_at) = EXTRACT(MONTH FROM NOW())")
            ->where('a.account_type', FinancialAccount::TYPE_ASSET)
            ->sum('l.amount_minor') ?? 0);

        if ($legacyPaidSatang !== $journalPaidSatang) {
            $drift++;
            $verb = $strict ? 'error' : 'warn';
            $this->components->{$verb}(sprintf(
                'Cross-system drift this month: legacy paid orders=%d satang, ledger=%d satang, diff=%d',
                $legacyPaidSatang, $journalPaidSatang, $legacyPaidSatang - $journalPaidSatang,
            ));
            if (!$strict) {
                $this->line('  (informational — re-run with --strict to fail the cron)');
            }
        } elseif (!$quiet) {
            $this->components->info(sprintf(
                'Cross-system OK: legacy=%d satang == ledger=%d satang for this month.',
                $legacyPaidSatang, $journalPaidSatang,
            ));
        }

        return $drift;
    }
}

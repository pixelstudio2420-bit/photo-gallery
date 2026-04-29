<?php

namespace App\Console\Commands;

use App\Models\AppSetting;
use App\Models\PhotographerProfile;
use App\Services\CreditService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Monthly-on-the-1st grant of free upload credits to every photographer
 * on the credits billing plan.
 *
 * Amount per photographer is tier-driven and controlled entirely from
 * AppSettings so Admin → Settings can dial it up/down without a code push:
 *   • creator  → `monthly_free_credits_creator`  (default seeded: 10)
 *   • seller   → `monthly_free_credits_seller`   (default seeded: 25)
 *   • pro      → `monthly_free_credits_pro`      (default seeded: 50)
 *
 * Bundles granted here expire in 45 days, so unused freebies don't pile
 * up forever and the "value of the month" framing stays intact for UX.
 *
 * Idempotency: this command is meant to run exactly once per month (1st,
 * 03:30). If it gets double-invoked, the ledger will show duplicate
 * `grant` rows with source=monthly_free — call out via --dry-run first if
 * recovering from a partial run.
 */
class GrantMonthlyFreeCreditsCommand extends Command
{
    protected $signature   = 'credits:grant-monthly-free
                              {--dry-run : Report what would be granted without touching the ledger}
                              {--tier=   : Restrict to a single tier (creator|seller|pro) for testing}';
    protected $description = 'Grant monthly free upload credits to photographers on the credits plan (tier-driven).';

    /** Default per-tier allowance if the AppSetting key is unset. */
    private const FALLBACK_AMOUNTS = [
        'creator' => 10,
        'seller'  => 25,
        'pro'     => 50,
    ];

    private const EXPIRY_DAYS = 45;

    public function handle(CreditService $credits): int
    {
        if (!$credits->systemEnabled()) {
            $this->warn('Credits system is globally disabled — nothing to grant.');
            return self::SUCCESS;
        }

        $dry      = (bool) $this->option('dry-run');
        $onlyTier = $this->option('tier');

        if ($onlyTier && !isset(self::FALLBACK_AMOUNTS[$onlyTier])) {
            $this->error("Unknown --tier={$onlyTier}. Use one of: creator, seller, pro.");
            return self::FAILURE;
        }

        $amounts = [];
        foreach (self::FALLBACK_AMOUNTS as $tier => $fallback) {
            $amounts[$tier] = (int) AppSetting::get("monthly_free_credits_{$tier}", $fallback);
        }

        $query = PhotographerProfile::query()
            ->where('billing_mode', PhotographerProfile::BILLING_CREDITS);

        if ($onlyTier) {
            $query->where('tier', $onlyTier);
        }

        $granted     = 0;
        $skipped     = 0;
        $totalCredit = 0;

        $query->chunkById(200, function ($profiles) use ($amounts, $credits, $dry, &$granted, &$skipped, &$totalCredit) {
            foreach ($profiles as $profile) {
                $tier   = (string) ($profile->tier ?? 'creator');
                $amount = $amounts[$tier] ?? 0;

                if ($amount <= 0) {
                    $skipped++;
                    continue;
                }

                if ($dry) {
                    $this->line("[dry-run] would grant {$amount} credits → photographer user_id={$profile->user_id} tier={$tier}");
                } else {
                    try {
                        $credits->grant(
                            photographerUserId: (int) $profile->user_id,
                            credits:            $amount,
                            source:             'monthly_free',
                            expiresDays:        self::EXPIRY_DAYS,
                            actorUserId:        null,
                            note:               'Monthly free credits ('.date('Y-m').')',
                        );
                    } catch (\Throwable $e) {
                        Log::error('Monthly free credit grant failed', [
                            'user_id' => $profile->user_id,
                            'tier'    => $tier,
                            'amount'  => $amount,
                            'error'   => $e->getMessage(),
                        ]);
                        $skipped++;
                        continue;
                    }
                }

                $granted++;
                $totalCredit += $amount;
            }
        });

        $this->info(sprintf(
            '%sMonthly free credits: %d photographers granted (%d total credits), %d skipped.',
            $dry ? '[dry-run] ' : '',
            $granted,
            $totalCredit,
            $skipped,
        ));

        return self::SUCCESS;
    }
}

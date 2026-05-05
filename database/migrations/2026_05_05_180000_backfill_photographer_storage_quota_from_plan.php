<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Backfill photographer_profiles.storage_quota_bytes for every row whose
 * cached value drifted from the plan's storage_bytes.
 *
 * Symptom this fixes: a photographer's dashboard showed
 * "0 / 100 GB (Pro plan)" but EnforceStorageQuota middleware was
 * gating their uploads at 5 GB because storage_quota_bytes was still
 * the seeded value from when they were on Free. The plan_code column
 * was up to date; the bytes column was not.
 *
 * Why was the bytes column stale?
 *   • syncProfileCache() correctly writes storage_quota_bytes whenever
 *     a real subscription transition fires (subscribe / renew / change),
 *     but admin manual edits to subscription_plan_code, legacy data
 *     migrations, and bulk seeders bypass it.
 *   • dashboardSummary now self-heals on read (see SubscriptionService),
 *     but the next upload on a fresh request would still hit middleware
 *     before the dashboard was ever loaded — so we backfill once here
 *     to make the live state correct without waiting for a visit.
 *
 * Idempotent: re-running adjusts only rows that drifted again.
 */
return new class extends Migration {
    public function up(): void
    {
        // Pull every (profile, plan) pair so we can compute the desired
        // storage_quota_bytes purely in PHP. A pure SQL UPDATE-with-JOIN
        // would be faster, but we want detailed logging of every fix.
        $rows = DB::table('photographer_profiles as p')
            ->leftJoin('subscription_plans as sp', 'sp.code', '=', 'p.subscription_plan_code')
            ->select(
                'p.id',
                'p.user_id',
                'p.storage_quota_bytes',
                'p.subscription_plan_code',
                'sp.storage_bytes as plan_bytes'
            )
            ->get();

        // Default-free fallback for profiles with NULL/missing plan_code
        $freeBytes = (int) (DB::table('subscription_plans')
            ->where('is_default_free', true)
            ->where('is_active', true)
            ->value('storage_bytes') ?? 0);

        $fixed = 0;
        foreach ($rows as $row) {
            $target = (int) ($row->plan_bytes ?? $freeBytes);
            if ($target <= 0) continue;
            if ((int) $row->storage_quota_bytes === $target) continue;

            DB::table('photographer_profiles')
                ->where('id', $row->id)
                ->update([
                    'storage_quota_bytes' => $target,
                    'updated_at'          => now(),
                ]);
            $fixed++;
        }

        if (function_exists('logger')) {
            logger()->info("Backfilled storage_quota_bytes for {$fixed} photographer profile(s)");
        }
    }

    public function down(): void
    {
        // No rollback — this fixes data drift, the previous values were
        // wrong by definition. There's nothing meaningful to revert to.
    }
};

<?php

namespace App\Services\Usage;

use App\Support\FlatConfig;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * UsageMeter — the single point where every metered operation gets recorded.
 *
 * Invariants (do not break)
 * -------------------------
 * 1. Every gated operation calls record() exactly once on success.
 * 2. record() is idempotent within a transaction: callers wrap in a
 *    DB::transaction so the API call + the meter happen atomically.
 *    A rolled-back call must not leave a counter row half-incremented.
 * 3. Counter rows are upserted with raw SQL — never via Eloquent — so
 *    parallel writers cannot lose updates.
 * 4. Microcent math is integer-only. Floats are forbidden because
 *    accumulating 0.001 floats over 100k calls drifts measurably.
 *
 * The shape of `record()`
 * -----------------------
 *     UsageMeter::record(
 *         userId:    $u->id,
 *         planCode:  'pro',
 *         resource:  'ai.face_search',
 *         units:     1,
 *         metadata:  ['event_id' => 99, 'cache_hit' => false],
 *     );
 *
 * Cost is looked up from config('usage.pricing'). Callers can pass an
 * explicit `costMicrocents` for cases where the cost varies per call
 * (storage uses bytes, AI uses calls).
 */
class UsageMeter
{
    /**
     * Record a metered operation: append to ledger + bump counters.
     *
     * Returns the cost in microcents that was charged. Callers can use
     * this for receipts or to surface "this call cost X" in the UI.
     */
    public static function record(
        int $userId,
        string $planCode,
        string $resource,
        int $units = 1,
        ?int $costMicrocents = null,
        array $metadata = [],
        ?Carbon $occurredAt = null,
    ): int {
        if ($units <= 0) {
            return 0; // ignore zero/negative records
        }

        $occurredAt ??= now();
        $cost = $costMicrocents ?? self::priceFor($resource, $units);

        try {
            DB::transaction(function () use (
                $userId, $planCode, $resource, $units,
                $cost, $metadata, $occurredAt,
            ) {
                // 1. Append to immutable ledger
                DB::table('usage_events')->insert([
                    'user_id'         => $userId,
                    'plan_code'       => $planCode,
                    'resource'        => $resource,
                    'units'           => $units,
                    'cost_microcents' => $cost,
                    'metadata'        => json_encode($metadata),
                    'occurred_at'     => $occurredAt,
                ]);

                // 2. Bump every period bucket
                foreach (self::periodKeys($occurredAt) as $period => $key) {
                    self::upsertCounter($userId, $resource, $period, $key, $units, $cost);
                }
            });

            return $cost;
        } catch (Throwable $e) {
            // Recording usage MUST NOT block the user-facing operation.
            // We log and move on so a transient ledger DB failure doesn't
            // surface as a 500. The counter MAY drift here — the nightly
            // reconciliation job rebuilds counters from the ledger.
            Log::error('UsageMeter::record failed', [
                'user_id'  => $userId,
                'resource' => $resource,
                'error'    => $e->getMessage(),
            ]);
            return 0;
        }
    }

    /**
     * Read the current counter for (user, resource, period). Returns
     * 0 when no row exists. This is the hot-path read the middleware
     * calls before every gated operation.
     */
    public static function counter(int $userId, string $resource, string $period, ?Carbon $at = null): int
    {
        $key = self::periodKey($period, $at ?? now());
        $row = DB::table('usage_counters')
            ->where('user_id',     $userId)
            ->where('resource',    $resource)
            ->where('period',      $period)
            ->where('period_key',  $key)
            ->select('units')
            ->first();
        return (int) ($row->units ?? 0);
    }

    /**
     * Read the current cost for (user, resource, period) in microcents.
     */
    public static function costMicrocents(int $userId, string $resource, string $period, ?Carbon $at = null): int
    {
        $key = self::periodKey($period, $at ?? now());
        $row = DB::table('usage_counters')
            ->where('user_id',     $userId)
            ->where('resource',    $resource)
            ->where('period',      $period)
            ->where('period_key',  $key)
            ->select('cost_microcents')
            ->first();
        return (int) ($row->cost_microcents ?? 0);
    }

    /**
     * "Lifetime" counter — sum across all months for this user/resource.
     * Used for storage.bytes which has no rolling window.
     */
    public static function lifetime(int $userId, string $resource): int
    {
        return (int) DB::table('usage_counters')
            ->where('user_id',  $userId)
            ->where('resource', $resource)
            ->where('period',   'month')
            ->sum('units');
    }

    /**
     * Reverse a recorded usage (e.g. file deleted, photo removed).
     * Decrements counters but leaves the original ledger row in place
     * — we want a complete audit trail. A negative ledger row is
     * appended so the running sum stays consistent.
     */
    public static function reverse(
        int $userId,
        string $planCode,
        string $resource,
        int $units,
        array $metadata = [],
    ): void {
        if ($units <= 0) return;

        $occurredAt = now();
        $cost = self::priceFor($resource, $units);

        try {
            DB::transaction(function () use (
                $userId, $planCode, $resource, $units, $cost, $metadata, $occurredAt,
            ) {
                DB::table('usage_events')->insert([
                    'user_id'         => $userId,
                    'plan_code'       => $planCode,
                    'resource'        => $resource,
                    'units'           => -$units,           // negative ⇒ reversal
                    'cost_microcents' => -$cost,
                    'metadata'        => json_encode($metadata + ['reversal' => true]),
                    'occurred_at'     => $occurredAt,
                ]);

                foreach (self::periodKeys($occurredAt) as $period => $key) {
                    self::upsertCounter($userId, $resource, $period, $key, -$units, -$cost);
                }
            });
        } catch (Throwable $e) {
            Log::warning('UsageMeter::reverse failed', [
                'user_id'  => $userId,
                'resource' => $resource,
                'units'    => $units,
                'error'    => $e->getMessage(),
            ]);
        }
    }

    /* ─────────────────── Internal helpers ─────────────────── */

    /**
     * Upsert with atomic increment. Postgres uses ON CONFLICT, sqlite
     * fakes it with INSERT-then-UPDATE because its ON CONFLICT support
     * for composite PKs is fiddly across versions.
     */
    private static function upsertCounter(
        int $userId, string $resource, string $period, string $periodKey,
        int $units, int $costMicrocents,
    ): void {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement(
                'INSERT INTO usage_counters (user_id, resource, period, period_key, units, cost_microcents, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, NOW())
                 ON CONFLICT (user_id, resource, period, period_key) DO UPDATE SET
                   units            = usage_counters.units + EXCLUDED.units,
                   cost_microcents  = usage_counters.cost_microcents + EXCLUDED.cost_microcents,
                   updated_at       = NOW()',
                [$userId, $resource, $period, $periodKey, $units, $costMicrocents],
            );
            return;
        }

        // sqlite / mysql path — race-safe under transaction isolation
        $existing = DB::table('usage_counters')
            ->where('user_id',     $userId)
            ->where('resource',    $resource)
            ->where('period',      $period)
            ->where('period_key',  $periodKey)
            ->lockForUpdate()
            ->first();

        if ($existing) {
            DB::table('usage_counters')
                ->where('user_id',     $userId)
                ->where('resource',    $resource)
                ->where('period',      $period)
                ->where('period_key',  $periodKey)
                ->update([
                    'units'           => DB::raw('units + ' . (int) $units),
                    'cost_microcents' => DB::raw('cost_microcents + ' . (int) $costMicrocents),
                    'updated_at'      => now(),
                ]);
        } else {
            DB::table('usage_counters')->insert([
                'user_id'         => $userId,
                'resource'        => $resource,
                'period'          => $period,
                'period_key'      => $periodKey,
                'units'           => $units,
                'cost_microcents' => $costMicrocents,
                'updated_at'      => now(),
            ]);
        }
    }

    /** @return array<string, string>  e.g. ['minute' => '2026-04-27T13:45', 'hour' => …] */
    private static function periodKeys(Carbon $at): array
    {
        return [
            'minute' => $at->format('Y-m-d\TH:i'),
            'hour'   => $at->format('Y-m-d\TH'),
            'day'    => $at->format('Y-m-d'),
            'month'  => $at->format('Y-m'),
        ];
    }

    private static function periodKey(string $period, Carbon $at): string
    {
        return match ($period) {
            'minute' => $at->format('Y-m-d\TH:i'),
            'hour'   => $at->format('Y-m-d\TH'),
            'day'    => $at->format('Y-m-d'),
            'month'  => $at->format('Y-m'),
            default  => throw new \InvalidArgumentException("Unknown period: {$period}"),
        };
    }

    /**
     * Look up the per-unit cost for a resource and multiply.
     *
     * Storage is special — it's billed by byte-month, but we record the
     * raw byte count and let PlanCostCalculator amortise the storage
     * cost across the month at report time.
     */
    private static function priceFor(string $resource, int $units): int
    {
        if ($resource === 'storage.bytes' || $resource === 'storage.bytes_month') {
            // Storage cost is amortised in PlanCostCalculator — record 0
            // here so per-call ledger rows aren't doubly counted.
            return 0;
        }
        return FlatConfig::int('usage.pricing', $resource) * $units;
    }
}

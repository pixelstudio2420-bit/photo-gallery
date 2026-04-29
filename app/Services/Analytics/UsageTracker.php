<?php

namespace App\Services\Analytics;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Append-only usage tracker — high-write, no-blocking.
 *
 * Design
 * ------
 * Every HTTP request hits this once. We CANNOT afford a SELECT or
 * UPDATE per request (would double the DB load just to measure it).
 * Instead the middleware accumulates into PER-MINUTE COUNTERS in the
 * cache layer, and a separate cron command flushes them to the
 * `request_minute_buckets` table.
 *
 * Cache key shape
 * ---------------
 *   metrics.bucket.{minute_iso}.{route_group}.{counter}
 *
 * Where counter is one of:
 *   • count       — total requests
 *   • errors      — 5xx
 *   • duration_ms_sum — sum of request durations
 *   • duration_ms_max — running max
 *   • users:{userId}  — set membership; counted at flush time
 *
 * The flush command (analytics:rollup-minute) reads + clears each
 * bucket's keys when persisting to the DB row. Idempotent — if a
 * minute is partially flushed, the next run picks up the rest.
 *
 * Why cache and not Redis directly?
 *   • Cache facade is driver-agnostic (file in dev, Redis in prod).
 *     Don't lock the analytics layer to one infra choice.
 *   • If the cache backend dies, we LOSE the bucket but NEVER block
 *     a user request. Analytics is best-effort by design.
 */
class UsageTracker
{
    public const TTL = 600;   // 10 min — enough for the cron to catch up

    /**
     * Record one request. Called from middleware on the way out.
     */
    public function record(string $routeGroup, int $statusCode, int $durationMs, ?int $userId): void
    {
        try {
            $bucket = $this->bucketKey();
            $base   = "metrics.bucket.{$bucket}.{$routeGroup}";

            // increment() is atomic at the cache backend (Redis or file
            // with proper locking). For drivers that don't support
            // atomic increment (some legacy ones), it's a near-atomic
            // get-set; under high concurrency we may lose a few counts.
            // Acceptable — analytics is approximate.
            Cache::increment("{$base}.count");
            Cache::add("{$base}.first_seen", $bucket, self::TTL);

            if ($statusCode >= 500) {
                Cache::increment("{$base}.errors");
            }

            // Duration accumulator — sum + max. At flush time we compute
            // average = sum / count for the bucket.
            Cache::increment("{$base}.duration_ms_sum", max(0, $durationMs));
            $currentMax = (int) Cache::get("{$base}.duration_ms_max", 0);
            if ($durationMs > $currentMax) {
                Cache::put("{$base}.duration_ms_max", $durationMs, self::TTL);
            }

            // Distinct users — store as a set in cache. We use a single
            // string with delimiter (cheap, lossy on overflow); a real
            // HyperLogLog (Redis PFADD) would be exact but we don't
            // require that level of precision for daily DAU.
            if ($userId !== null) {
                $usersKey = "{$base}.users";
                $blob = (string) Cache::get($usersKey, '');
                // Cap at 64 KB so a runaway page can't blow cache.
                if (strlen($blob) < 64_000) {
                    if (!str_contains($blob, "|{$userId}|")) {
                        Cache::put($usersKey, $blob . "|{$userId}|", self::TTL);
                    }
                }
            }

            // Track ALL the keys we touched so the flush command can
            // find this bucket without a Cache::keys() scan (which is
            // expensive on Redis and unsupported on file driver).
            $registry = "metrics.registry.{$bucket}";
            $existing = (string) Cache::get($registry, '');
            $marker   = "|{$routeGroup}|";
            if (!str_contains($existing, $marker)) {
                Cache::put($registry, $existing . $marker, self::TTL);
            }
        } catch (\Throwable) {
            // Best-effort. Do NOT break a user request because metrics
            // tracking has a hiccup.
        }
    }

    /**
     * Get-and-clear the buckets for a specific minute. Used by the
     * rollup command. Returns array of:
     *   [routeGroup => ['count', 'errors', 'duration_ms_sum',
     *                   'duration_ms_max', 'distinct_users']]
     */
    public function drain(string $bucketIso): array
    {
        $registry = "metrics.registry.{$bucketIso}";
        $groupsBlob = (string) Cache::get($registry, '');
        if ($groupsBlob === '') return [];

        $groups = array_filter(explode('|', $groupsBlob));
        $out = [];
        foreach (array_unique($groups) as $group) {
            $base = "metrics.bucket.{$bucketIso}.{$group}";
            $count = (int) Cache::get("{$base}.count", 0);
            if ($count === 0) continue;

            $errors  = (int) Cache::get("{$base}.errors", 0);
            $durSum  = (int) Cache::get("{$base}.duration_ms_sum", 0);
            $durMax  = (int) Cache::get("{$base}.duration_ms_max", 0);
            $usersBlob = (string) Cache::get("{$base}.users", '');
            $distinctUsers = $usersBlob === ''
                ? 0
                : count(array_filter(explode('|', $usersBlob)));

            $out[$group] = [
                'count'           => $count,
                'errors'          => $errors,
                'duration_ms_sum' => $durSum,
                'duration_ms_max' => $durMax,
                'distinct_users'  => $distinctUsers,
            ];

            // Clear so we don't double-count if the cron lags + reruns.
            Cache::forget("{$base}.count");
            Cache::forget("{$base}.errors");
            Cache::forget("{$base}.duration_ms_sum");
            Cache::forget("{$base}.duration_ms_max");
            Cache::forget("{$base}.users");
            Cache::forget("{$base}.first_seen");
        }
        Cache::forget($registry);
        return $out;
    }

    /**
     * Flush all buckets older than the cutoff (default = current minute,
     * so we don't flush a bucket that's still being written to). Returns
     * the count of bucket rows persisted.
     */
    public function flushTo(\DateTimeInterface $cutoff): int
    {
        // We don't know which minutes have data — we'd need cache key
        // enumeration. Instead, we walk back from cutoff and stop at
        // the first empty bucket OR a hard limit. 10 minutes is enough
        // since the cron runs every minute.
        $minutes = 10;
        $persisted = 0;
        $cutoffEpoch = (int) ($cutoff->getTimestamp() / 60) * 60;

        for ($i = 1; $i <= $minutes; $i++) {
            $epoch = $cutoffEpoch - ($i * 60);
            $iso   = gmdate('Y-m-d\TH:i', $epoch);

            $rows = $this->drain($iso);
            if (empty($rows)) continue;

            foreach ($rows as $group => $row) {
                $bucketAt = date('Y-m-d H:i:00', $epoch);

                // SELECT-then-INSERT-or-UPDATE — driver-portable.
                // Avoids:
                //   • `column + N` raw expressions on the INSERT branch
                //     (sqlite errors because the column doesn't exist yet)
                //   • GREATEST() which is missing on sqlite.
                $existing = DB::table('request_minute_buckets')
                    ->where('bucket_at', $bucketAt)
                    ->where('route_group', $group)
                    ->first();

                if ($existing) {
                    DB::table('request_minute_buckets')
                        ->where('id', $existing->id)
                        ->update([
                            'request_count'   => (int) $existing->request_count   + (int) $row['count'],
                            'error_count'     => (int) $existing->error_count     + (int) $row['errors'],
                            'duration_ms_sum' => (int) $existing->duration_ms_sum + (int) $row['duration_ms_sum'],
                            'duration_ms_max' => max((int) $existing->duration_ms_max, (int) $row['duration_ms_max']),
                            'distinct_users'  => (int) $existing->distinct_users  + (int) $row['distinct_users'],
                            'updated_at'      => now(),
                        ]);
                } else {
                    DB::table('request_minute_buckets')->insert([
                        'bucket_at'       => $bucketAt,
                        'route_group'     => $group,
                        'request_count'   => (int) $row['count'],
                        'error_count'     => (int) $row['errors'],
                        'duration_ms_sum' => (int) $row['duration_ms_sum'],
                        'duration_ms_max' => (int) $row['duration_ms_max'],
                        'distinct_users'  => (int) $row['distinct_users'],
                        'created_at'      => now(),
                        'updated_at'      => now(),
                    ]);
                }
                $persisted++;
            }
        }
        return $persisted;
    }

    /**
     * Bucket key = "YYYY-MM-DDTHH:mm" in UTC. Truncate seconds.
     */
    private function bucketKey(): string
    {
        return gmdate('Y-m-d\TH:i', time());
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Usage analytics + capacity tracking — schema.
 *
 * Three tables, three different write rates / read patterns
 * ---------------------------------------------------------
 *
 *   request_minute_buckets   — high-write, low-read.
 *     One row per (minute, route_group). The middleware accumulates
 *     into the cache layer; a cron command flushes to this table
 *     every minute. Querying this directly is fine for ad-hoc
 *     "what happened in the last 6 hours?", but for dashboard
 *     reads we use the daily rollup below.
 *
 *   usage_daily              — low-write, high-read.
 *     One row per (date, metric, feature). The rollup command
 *     aggregates yesterday's buckets into one row per metric.
 *     This is the table the admin dashboard hits.
 *
 *   capacity_baselines       — config-as-data.
 *     Measured ceilings from load tests live here so the capacity
 *     calculator can output "you're at X% of provisioned capacity"
 *     without hard-coding numbers in code. Each baseline row is
 *     metric + value + measurement_date + notes (provenance).
 *
 * Why minute buckets instead of raw per-request rows
 * --------------------------------------------------
 * 30 RPS × 86400 s = 2.6 M rows/day even on a dev box. Per-request
 * persistence would add 30+ INSERTs/sec just for tracking — bigger
 * than the actual app traffic. Per-minute aggregate = 1440 rows/day
 * = trivial DB cost.
 *
 * Privacy
 * -------
 * No PII in any table. user_id is foreign-key only — used for DAU
 * counts but never joined with the user's name/email at this layer.
 * For "who did X" questions, use the existing activity_logs table.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('request_minute_buckets')) {
            Schema::create('request_minute_buckets', function (Blueprint $t) {
                $t->id();
                // Truncated to the start of the minute (UTC) so per-minute
                // counters land on a deterministic bucket.
                $t->timestamp('bucket_at');
                // Coarse route grouping. Examples:
                //   'public.read'      — GET /events, /photographers, etc.
                //   'public.write'     — POST cart/order
                //   'auth.write'       — POST /auth/login etc.
                //   'photographer.write' — photographer dashboard actions
                //   'api.upload'       — /api/uploads/*
                //   'api.webhook'      — /api/webhooks/*
                //   'health'           — /up
                //   'other'            — fallback
                $t->string('route_group', 32)->default('other');
                $t->unsignedInteger('request_count')->default(0);
                $t->unsignedInteger('error_count')->default(0);
                // We track sum + count separately so the rollup can
                // compute a true average across buckets without losing
                // precision (don't store an average per bucket).
                $t->unsignedBigInteger('duration_ms_sum')->default(0);
                // Approximate p95 via the higher-quantile bucket sketch.
                // For exactness use the raw access log; this column is
                // a "good enough" indicator for dashboards.
                $t->unsignedInteger('duration_ms_max')->default(0);
                // Distinct user_id count (lossy across buckets — we
                // only have per-bucket cardinality). The daily rollup
                // sums this with 80% double-counting correction. For
                // strict DAU, use activity_logs.user_id distinct.
                $t->unsignedInteger('distinct_users')->default(0);
                $t->timestamps();

                $t->unique(['bucket_at', 'route_group']);
                $t->index('bucket_at');
            });
        }

        if (!Schema::hasTable('usage_daily')) {
            Schema::create('usage_daily', function (Blueprint $t) {
                $t->id();
                $t->date('date');
                // Metric examples:
                //   'requests.total'        request_count summed
                //   'requests.errors'       error_count summed
                //   'requests.peak_rps'     max bucket request_count / 60
                //   'requests.avg_ms'       weighted duration average
                //   'users.dau'             distinct users for the day
                //   'uploads.photos'        from EventPhoto::created
                //   'bookings.created'      from Booking::created
                //   'line.pushes'           from line_deliveries
                //   'line.errors'           failed deliveries
                $t->string('metric', 64);
                // Optional sub-key — e.g. metric=requests.total feature=public.read
                $t->string('feature', 64)->nullable();
                $t->bigInteger('value')->default(0);
                // Free-form context (e.g. peak hour for peak_rps metric).
                $t->json('meta')->nullable();
                $t->timestamps();

                $t->unique(['date', 'metric', 'feature'], 'usage_daily_uniq');
                $t->index('date');
                $t->index('metric');
            });
        }

        if (!Schema::hasTable('capacity_baselines')) {
            Schema::create('capacity_baselines', function (Blueprint $t) {
                $t->id();
                // Examples:
                //   'rps.production_per_box'        — measured peak RPS
                //   'rps.dev_per_box'               — measured dev peak
                //   'concurrent_users.production'   — extrapolated ceiling
                //   'storage.bytes_per_user_avg'    — avg R2 bytes per active user
                //   'queue.workers'                 — current worker count
                //   'queue.jobs_per_sec_per_worker' — measured throughput
                $t->string('metric', 64)->unique();
                $t->bigInteger('value');
                $t->string('unit', 16)->default('count');   // count|bytes|seconds|percent
                // When this number was measured, so the dashboard can warn
                // "this baseline is 6 months old, re-measure".
                $t->date('measured_on');
                // Source of the number — e.g. 'tests/Load/harness.cjs run on
                // 2026-04-28' or 'extrapolated from dev-box × 5'.
                $t->string('source', 200)->nullable();
                $t->text('notes')->nullable();
                $t->timestamps();
            });
        }

        // ── Seed the baselines from our measured load test ──────────────
        // These rows are CONFIG-AS-DATA, not migrations of customer data —
        // they capture what we measured in tests/Load/harness.cjs after
        // applying opcache + ThreadsPerChild + cache-table fixes.
        if (DB::table('capacity_baselines')->count() === 0) {
            $now = now();
            DB::table('capacity_baselines')->insert([
                [
                    'metric'      => 'rps.dev_per_box',
                    'value'       => 30,
                    'unit'        => 'count',
                    'measured_on' => '2026-04-28',
                    'source'      => 'tests/Load/harness.cjs c=25, post-opcache fix',
                    'notes'       => 'Windows + Apache mpm_winnt + PHP 8.2; this is the floor. Linux + nginx + PHP-FPM extrapolates to 5-20× this number.',
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ],
                [
                    'metric'      => 'rps.production_per_box',
                    'value'       => 200,
                    'unit'        => 'count',
                    'measured_on' => '2026-04-28',
                    'source'      => 'extrapolated: dev_per_box × ~7 (Linux/PHP-FPM speedup midpoint)',
                    'notes'       => 'Conservative midpoint of 5-20× extrapolation. Re-measure after first production deploy to replace this estimate with measured number.',
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ],
                [
                    'metric'      => 'concurrent_users.dev',
                    'value'       => 25,
                    'unit'        => 'count',
                    'measured_on' => '2026-04-28',
                    'source'      => 'tests/Load/harness.cjs at p50<500ms threshold',
                    'notes'       => null,
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ],
                [
                    'metric'      => 'concurrent_users.production',
                    'value'       => 7500,
                    'unit'        => 'count',
                    'measured_on' => '2026-04-28',
                    'source'      => 'extrapolated for mixed traffic mix (8 req/min/user avg) at 200 RPS',
                    'notes'       => null,
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ],
                [
                    'metric'      => 'queue.jobs_per_sec_per_worker',
                    'value'       => 10,
                    'unit'        => 'count',
                    'measured_on' => '2026-04-28',
                    'source'      => 'database queue driver, per-worker poll loop',
                    'notes'       => 'Redis driver typically 5-10× this. Update after switching driver.',
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ],
                [
                    'metric'      => 'r2.put_per_sec_per_bucket',
                    'value'       => 5000,
                    'unit'        => 'count',
                    'measured_on' => '2026-04-28',
                    'source'      => 'Cloudflare R2 documented bucket-level rate limit',
                    'notes'       => null,
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ],
                [
                    'metric'      => 'line.push_per_sec',
                    'value'       => 1000,
                    'unit'        => 'count',
                    'measured_on' => '2026-04-28',
                    'source'      => 'LINE Messaging API documented rate',
                    'notes'       => null,
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ],
                [
                    'metric'      => 'line.free_quota_per_month',
                    'value'       => 200,
                    'unit'        => 'count',
                    'measured_on' => '2026-04-28',
                    'source'      => 'LINE OA free tier; paid plans lift this',
                    'notes'       => null,
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ],
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('capacity_baselines');
        Schema::dropIfExists('usage_daily');
        Schema::dropIfExists('request_minute_buckets');
    }
};

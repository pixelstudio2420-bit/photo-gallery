<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * face_search_logs — every face-search attempt (allowed OR denied).
 *
 * Why this table exists
 *   The public face-search endpoint spends real AWS Rekognition $ per call
 *   (detectFaces ~$0.001 + searchFacesByImage ~$0.001 + up to N × compareFaces
 *   ~$0.001 in the fallback path). Without a ledger we cannot enforce daily
 *   budgets, we cannot detect abuse, and we cannot compute cost attribution
 *   after the fact. This table is that ledger.
 *
 * Intentionally append-only + narrow:
 *   • No selfie bytes or face bounding boxes — PDPA compliance (selfies are
 *     biometric data; we promised to purge at request end).
 *   • `selfie_hash` is a sha256 of the raw bytes — enables dedup/cache lookup
 *     without retaining the original image. Collisions are cryptographically
 *     impossible at this scale so it is safe as a cache key.
 *   • Covering indexes on (event_id, created_at), (user_id, created_at), and
 *     (ip_address, created_at) so the daily-cap queries in
 *     FaceSearchBudget::remainingQuota() stay on an index scan.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('face_search_logs')) {
            return;
        }

        Schema::create('face_search_logs', function (Blueprint $table) {
            $table->id();

            // Who & where — event_id is nullable only so global quota counts
            // stay consistent even if the event row is later hard-deleted.
            $table->unsignedBigInteger('event_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('ip_address', 45)->index(); // IPv6-wide

            // sha256 hex of selfie bytes — 64 chars. Used by FaceSearchBudget
            // to serve repeat identical searches from cache instead of hitting
            // AWS again. NOT personally identifying on its own.
            $table->char('selfie_hash', 64);

            // Which code path ran:
            //   'collection' — searchFacesByImage (1 API call, cheap)
            //   'fallback'   — compareFaces loop (N API calls, expensive)
            //   'cache'      — served from cache, 0 API calls
            $table->string('search_type', 20)->default('collection');

            // Cost telemetry — how many paid AWS calls this request actually
            // issued. Lets the admin usage dashboard multiply by $0.001 for a
            // rough cost estimate without pulling from AWS Cost Explorer.
            $table->unsignedSmallInteger('api_calls')->default(0);
            $table->unsignedSmallInteger('face_count')->default(0);
            $table->unsignedSmallInteger('match_count')->default(0);
            $table->unsignedInteger('duration_ms')->default(0);

            // Outcome — the dashboard filters on this to show "denied / served
            // from cache / error" counts alongside the happy path.
            //   success | cache_hit | no_face | denied_kill_switch |
            //   denied_daily_cap_event | denied_daily_cap_user |
            //   denied_daily_cap_ip | denied_monthly_global |
            //   fallback_too_large | error
            $table->string('status', 32)->default('success');
            $table->string('notes', 255)->nullable();

            // Timestamps — we do NOT need updated_at because rows are
            // append-only; saves 8 bytes per row at scale.
            $table->timestamp('created_at')->useCurrent();

            // Composite indexes for the cap-enforcement queries, which all
            // filter on (dimension, created_at >= startOfDay) — a single
            // composite index beats two independent ones here.
            $table->index(['event_id', 'created_at'], 'fsl_event_time_idx');
            $table->index(['user_id', 'created_at'],  'fsl_user_time_idx');
            $table->index(['ip_address', 'created_at'], 'fsl_ip_time_idx');
            $table->index(['selfie_hash', 'event_id'], 'fsl_hash_event_idx');
            $table->index(['status', 'created_at'], 'fsl_status_time_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('face_search_logs');
    }
};

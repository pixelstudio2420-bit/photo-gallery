<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Threat Intelligence + Geo IP cache backing tables.
 *
 * Why this migration exists separately from
 * 2026_04_15_300000_create_security_sync_tables:
 *   - That earlier migration was authored before the ThreatIntelligenceService
 *     had its current shape, so it created `threat_incidents` with column
 *     names that no longer match what the service inserts (threat_type vs
 *     type, ip_address vs ip, metadata vs data, no resolved_at/resolution).
 *   - The other 4 backing tables (threat_patterns, threat_scores,
 *     threat_blocked_fingerprints, geo_ip_cache) were never created at all,
 *     which is why the dashboard 500s with "Base table or view not found".
 *
 * Strategy:
 *   - Create the 4 missing tables outright.
 *   - Reconcile `threat_incidents` by ADDING the columns the service expects.
 *     Old columns stay (nullable) so the table is forward-compatible without
 *     destroying any rows that might have landed there.
 *   - Backfill the new columns from the old ones for any existing rows so
 *     reads via the new schema don't show NULLs unnecessarily.
 *
 * down() is conservative — it drops the 4 new tables and removes the
 * columns we added, but does NOT touch anything the earlier migration owns.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Threat Patterns ─────────────────────────────────────────────
        // Every analyzed request lands here; the analyzer reads recent rows
        // back to compute burst / UA / path-scan signals. High write volume,
        // so we lean on indexes for the ip+created_at lookup.
        if (!Schema::hasTable('threat_patterns')) {
            Schema::create('threat_patterns', function (Blueprint $table) {
                $table->id();
                $table->string('ip', 45)->index();
                $table->string('fingerprint', 64)->default('')->index();
                $table->string('action', 50)->default('analyze');
                $table->string('path', 500)->nullable();
                $table->string('method', 10)->default('GET');
                $table->string('user_agent', 500)->nullable();
                $table->json('meta')->nullable();
                $table->timestamp('created_at')->nullable()->index();

                $table->index(['ip', 'created_at']);
            });
        }

        // ── Threat Scores ───────────────────────────────────────────────
        // One row per IP. score is 0-100, factors is a json log of the last
        // ~50 deltas with reasons (kept short on purpose so this table
        // doesn't grow unbounded per IP).
        if (!Schema::hasTable('threat_scores')) {
            Schema::create('threat_scores', function (Blueprint $table) {
                $table->id();
                $table->string('ip', 45)->unique();
                $table->unsignedTinyInteger('score')->default(0)->index();
                $table->json('factors')->nullable();
                $table->string('fingerprint', 64)->default('');
                $table->timestamp('first_seen')->nullable();
                $table->timestamp('last_seen')->nullable()->index();
                $table->timestamp('updated_at')->nullable();
            });
        }

        // ── Blocked Fingerprints ────────────────────────────────────────
        // Time-boxed bans on browser/request fingerprints — survives IP
        // rotation. The query path checks `expires_at > now()` so a unique
        // index on fingerprint + an index on expires_at keeps it fast.
        if (!Schema::hasTable('threat_blocked_fingerprints')) {
            Schema::create('threat_blocked_fingerprints', function (Blueprint $table) {
                $table->id();
                $table->string('fingerprint', 64)->unique();
                $table->string('reason', 255)->nullable();
                $table->timestamp('blocked_at')->nullable()->index();
                $table->timestamp('expires_at')->nullable()->index();
            });
        }

        // ── Geo IP Cache ────────────────────────────────────────────────
        // 24-hour TTL cache for ip-api.com lookups. Refreshed on demand by
        // GeoAccessService::lookup(); pruned weekly by scheduler.
        if (!Schema::hasTable('geo_ip_cache')) {
            Schema::create('geo_ip_cache', function (Blueprint $table) {
                $table->id();
                $table->string('ip', 45)->unique();
                $table->string('country_code', 4)->nullable()->index();
                $table->string('country_name', 100)->nullable();
                $table->string('region', 100)->nullable();
                $table->string('city', 100)->nullable();
                $table->string('isp', 200)->nullable();
                $table->timestamp('cached_at')->nullable()->index();
            });
        }

        // ── Reconcile threat_incidents ─────────────────────────────────
        // The service inserts/queries with a different column shape than
        // the original migration. Add the missing ones; back-fill from the
        // old columns where possible so the dashboard doesn't show NULLs
        // for whatever historical rows are present.
        if (Schema::hasTable('threat_incidents')) {
            Schema::table('threat_incidents', function (Blueprint $table) {
                if (!Schema::hasColumn('threat_incidents', 'type')) {
                    $table->string('type', 100)->nullable()->after('id')->index();
                }
                if (!Schema::hasColumn('threat_incidents', 'ip')) {
                    $table->string('ip', 45)->nullable()->index();
                }
                if (!Schema::hasColumn('threat_incidents', 'data')) {
                    $table->json('data')->nullable();
                }
                if (!Schema::hasColumn('threat_incidents', 'resolved_at')) {
                    $table->timestamp('resolved_at')->nullable();
                }
                if (!Schema::hasColumn('threat_incidents', 'resolution')) {
                    $table->string('resolution', 500)->nullable();
                }
            });

            // Back-fill from old columns so existing rows show up sensibly.
            // Wrapped in a try so a partial schema doesn't break the migration.
            try {
                if (Schema::hasColumn('threat_incidents', 'threat_type')) {
                    \Illuminate\Support\Facades\DB::table('threat_incidents')
                        ->whereNull('type')
                        ->update(['type' => \Illuminate\Support\Facades\DB::raw('threat_type')]);
                }
                if (Schema::hasColumn('threat_incidents', 'ip_address')) {
                    \Illuminate\Support\Facades\DB::table('threat_incidents')
                        ->whereNull('ip')
                        ->update(['ip' => \Illuminate\Support\Facades\DB::raw('ip_address')]);
                }
                if (Schema::hasColumn('threat_incidents', 'metadata')) {
                    \Illuminate\Support\Facades\DB::table('threat_incidents')
                        ->whereNull('data')
                        ->update(['data' => \Illuminate\Support\Facades\DB::raw('metadata')]);
                }
            } catch (\Throwable) {
                // Back-fill is best-effort; never block migration.
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('threat_patterns');
        Schema::dropIfExists('threat_scores');
        Schema::dropIfExists('threat_blocked_fingerprints');
        Schema::dropIfExists('geo_ip_cache');

        if (Schema::hasTable('threat_incidents')) {
            Schema::table('threat_incidents', function (Blueprint $table) {
                foreach (['type', 'ip', 'data', 'resolved_at', 'resolution'] as $col) {
                    if (Schema::hasColumn('threat_incidents', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seed defaults for the 6-phase R2 cost reduction program.
 *
 * Phase 1 — Lifecycle-aware retention
 * ────────────────────────────────────
 * Event::effectiveRetentionTier() now downgrades to TIER_CREATOR when the
 * photographer has no active/grace subscription. That's a code change with
 * no settings needed.
 *
 * Phase 2 — Free tier short retention
 * ───────────────────────────────────
 * Reduce `retention_days_creator` from 60 → 5. Free photographers' originals
 * get archived to portfolio mode after 5 days. Combined with the existing
 * `retention_mode_creator=portfolio` (NOT 'full' — preserves photographer's
 * portfolio display + protects active download tokens).
 *
 * The previous migration may have set retention_days_creator=60. We update
 * to 5 ONLY if the current value is the old default (60) — admins who tuned
 * to something else keep their tuning.
 *
 * Phase 3 — Warning ahead-of-purge
 * ────────────────────────────────
 * Bump `retention_warning_days_ahead` from 1 → 2 so a 5-day window has 40%
 * lead time for the warning email (was 17% at 1 day of 60-day retention).
 *
 * Phase 4 — Photographer dashboard widget
 * ───────────────────────────────────────
 * No settings needed — pure view code.
 *
 * Phase 5 — Compress aged originals
 * ─────────────────────────────────
 *   compress_originals_enabled       → '1'   master switch
 *   compress_originals_after_days    → '90'  originals older than N days get recompressed
 *   compress_originals_quality       → '80'  JPEG quality (was ~95 on upload)
 *   compress_originals_batch_limit   → '100' photos per nightly run
 *
 * Phase 6 — Inactive Free account sweep
 * ─────────────────────────────────────
 *   inactive_sweep_enabled              → '0'   OFF by default (admin opt-in;
 *                                                deletes user accounts!)
 *   inactive_sweep_login_days           → '90'  no login for N days = candidate
 *   inactive_sweep_no_sales_days        → '180' no sales for N days = candidate
 *   inactive_sweep_warning_days_ahead   → '30'  email warning N days before delete
 *
 * Marker-based rollback (consistent with previous retention migration).
 */
return new class extends Migration {
    private const INSERTED_MARKER_KEY = '__6phase_cost_reduction_inserted__';
    private const PHASE2_UPDATED_KEY  = '__6phase_phase2_updated_keys__';

    public function up(): void
    {
        $now = now();
        $insertedKeys = [];
        $updatedKeys  = [];

        // ─── Phase 2 — tighten Free retention if still at the old default ───
        $this->updateIfDefault('retention_days_creator', '60', '5', $now, $updatedKeys);

        // ─── Phase 3 — bump warning lead time ───
        $this->updateIfDefault('retention_warning_days_ahead', '1', '2', $now, $updatedKeys);

        // ─── Phase 5 — compress aged originals ───
        $this->insertIfMissing([
            'compress_originals_enabled'     => '1',
            'compress_originals_after_days'  => '90',
            'compress_originals_quality'     => '80',
            'compress_originals_batch_limit' => '100',
        ], $now, $insertedKeys);

        // ─── Phase 6 — inactive Free account sweep (opt-in) ───
        $this->insertIfMissing([
            'inactive_sweep_enabled'            => '0',
            'inactive_sweep_login_days'         => '90',
            'inactive_sweep_no_sales_days'      => '180',
            'inactive_sweep_warning_days_ahead' => '30',
        ], $now, $insertedKeys);

        // Persist marker so down() can target precisely.
        if (!empty($insertedKeys)) {
            DB::table('app_settings')->updateOrInsert(
                ['key' => self::INSERTED_MARKER_KEY],
                ['value' => json_encode($insertedKeys, JSON_UNESCAPED_UNICODE), 'updated_at' => $now]
            );
        }
        if (!empty($updatedKeys)) {
            // Stores [key => oldValue] so down() can restore.
            DB::table('app_settings')->updateOrInsert(
                ['key' => self::PHASE2_UPDATED_KEY],
                ['value' => json_encode($updatedKeys, JSON_UNESCAPED_UNICODE), 'updated_at' => $now]
            );
        }
    }

    public function down(): void
    {
        // Delete keys this migration inserted.
        $marker = DB::table('app_settings')->where('key', self::INSERTED_MARKER_KEY)->value('value');
        $insertedKeys = $marker ? (array) json_decode((string) $marker, true) : [];
        if (!empty($insertedKeys)) {
            DB::table('app_settings')->whereIn('key', $insertedKeys)->delete();
        }
        DB::table('app_settings')->where('key', self::INSERTED_MARKER_KEY)->delete();

        // Restore values this migration overwrote.
        $marker2 = DB::table('app_settings')->where('key', self::PHASE2_UPDATED_KEY)->value('value');
        $updatedKeys = $marker2 ? (array) json_decode((string) $marker2, true) : [];
        foreach ($updatedKeys as $key => $oldValue) {
            DB::table('app_settings')->where('key', $key)->update([
                'value' => (string) $oldValue,
                'updated_at' => now(),
            ]);
        }
        DB::table('app_settings')->where('key', self::PHASE2_UPDATED_KEY)->delete();
    }

    private function insertIfMissing(array $defaults, $now, array &$insertedKeys): void
    {
        foreach ($defaults as $key => $value) {
            if (DB::table('app_settings')->where('key', $key)->exists()) {
                continue;
            }
            DB::table('app_settings')->insert([
                'key'        => $key,
                'value'      => (string) $value,
                'updated_at' => $now,
            ]);
            $insertedKeys[] = $key;
        }
    }

    /**
     * Update a setting only if its current value matches the expected old default.
     * Preserves admin's intentional tunings.
     */
    private function updateIfDefault(string $key, string $oldDefault, string $newValue, $now, array &$updatedKeys): void
    {
        $current = DB::table('app_settings')->where('key', $key)->value('value');
        if ($current === null) {
            // Key doesn't exist — insert with new default. Record so down() removes it.
            DB::table('app_settings')->insert([
                'key' => $key,
                'value' => $newValue,
                'updated_at' => $now,
            ]);
            $updatedKeys[$key] = ''; // empty string = "was missing"
            return;
        }
        if ((string) $current !== $oldDefault) {
            // Admin has tuned this — leave alone.
            return;
        }
        DB::table('app_settings')->where('key', $key)->update([
            'value' => $newValue,
            'updated_at' => $now,
        ]);
        $updatedKeys[$key] = $oldDefault;
    }
};

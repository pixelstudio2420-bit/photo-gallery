<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Grant the `presets` feature to all paid subscription plans
 * (Starter / Pro / Business / Studio).
 *
 * Lightroom Presets is a value-add advertised on every paid tier — but
 * the seed for ai_features didn't include it, which meant ANY paid
 * photographer hit a 402 Payment Required when trying to use the
 * editor or duplicate a system preset. This migration patches existing
 * rows by merging 'presets' into the ai_features JSON array
 * idempotently (so re-running is safe and won't create duplicates).
 *
 * Free plan stays gated — to encourage upgrades and limit GD load.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('subscription_plans')) {
            return;
        }

        $codes = ['starter', 'pro', 'business', 'studio'];

        foreach ($codes as $code) {
            $row = DB::table('subscription_plans')->where('code', $code)->first();
            if (!$row) continue;

            $features = json_decode($row->ai_features ?? '[]', true) ?: [];
            if (!in_array('presets', $features, true)) {
                $features[] = 'presets';
                DB::table('subscription_plans')
                    ->where('id', $row->id)
                    ->update([
                        'ai_features' => json_encode($features),
                        'updated_at'  => now(),
                    ]);
            }
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('subscription_plans')) {
            return;
        }

        $codes = ['starter', 'pro', 'business', 'studio'];

        foreach ($codes as $code) {
            $row = DB::table('subscription_plans')->where('code', $code)->first();
            if (!$row) continue;

            $features = json_decode($row->ai_features ?? '[]', true) ?: [];
            $features = array_values(array_filter($features, fn ($f) => $f !== 'presets'));
            DB::table('subscription_plans')
                ->where('id', $row->id)
                ->update([
                    'ai_features' => json_encode($features),
                    'updated_at'  => now(),
                ]);
        }
    }
};

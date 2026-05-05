<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Make plan.storage_bytes match what plan.description advertises.
 *
 * Audit found three plans whose description string and storage_bytes
 * column disagreed:
 *
 *   free     desc "2 GB"     storage_bytes 5 GB    (storage > desc)
 *   starter  desc "20 GB"    storage_bytes 25 GB   (storage > desc)
 *   studio   desc "2 TB"     storage_bytes 1 TB    (storage < desc) ← problem
 *
 * The Studio mismatch was visible to the buyer: they saw "พื้นที่ 2 TB"
 * on the plans page, paid ฿3,990/month, then the dashboard widget
 * (which reads storage_bytes) capped them at 1024 GB. Reported as
 * "Storage GB ไม่ตรงกับแผน".
 *
 * Fix strategy: reconcile to the LARGER of the two values, so:
 *   • Buyers never get less than they were promised in marketing copy
 *   • Existing photographers don't have anything taken away
 *
 * Specifically:
 *   • free:     bump description "2 GB" → "5 GB" (storage stays 5 GB)
 *   • starter:  bump description "20 GB" → "25 GB" (storage stays 25 GB)
 *   • studio:   bump storage_bytes 1 TB → 2 TB (description stays "2 TB")
 *
 * Also patches features_json["X GB storage"] / ["X TB storage"] entries
 * so the per-plan feature checklist stays consistent. Idempotent.
 */
return new class extends Migration {
    public function up(): void
    {
        $this->fixFree();
        $this->fixStarter();
        $this->fixStudio();
    }

    public function down(): void
    {
        // No rollback — the previous state was the inconsistency itself.
    }

    private function fixFree(): void
    {
        $row = DB::table('subscription_plans')->where('code', 'free')->first();
        if (!$row) return;

        $newDesc = preg_replace('/พื้นที่\s+\d+\s*GB/u', 'พื้นที่ 5 GB', (string) $row->description);
        $features = json_decode((string) $row->features_json, true);
        if (is_array($features)) {
            $features = array_map(fn ($f) =>
                preg_replace('/\b\d+\s*GB\s+storage/i', '5 GB storage', (string) $f), $features);
        }

        DB::table('subscription_plans')
            ->where('code', 'free')
            ->update([
                'description'   => $newDesc,
                'features_json' => is_array($features) ? json_encode($features, JSON_UNESCAPED_UNICODE) : $row->features_json,
                'updated_at'    => now(),
            ]);
    }

    private function fixStarter(): void
    {
        $row = DB::table('subscription_plans')->where('code', 'starter')->first();
        if (!$row) return;

        $newDesc = preg_replace('/พื้นที่\s+\d+\s*GB/u', 'พื้นที่ 25 GB', (string) $row->description);
        $features = json_decode((string) $row->features_json, true);
        if (is_array($features)) {
            $features = array_map(fn ($f) =>
                preg_replace('/\b\d+\s*GB\s+storage/i', '25 GB storage', (string) $f), $features);
        }

        DB::table('subscription_plans')
            ->where('code', 'starter')
            ->update([
                'description'   => $newDesc,
                'features_json' => is_array($features) ? json_encode($features, JSON_UNESCAPED_UNICODE) : $row->features_json,
                'updated_at'    => now(),
            ]);
    }

    private function fixStudio(): void
    {
        $row = DB::table('subscription_plans')->where('code', 'studio')->first();
        if (!$row) return;

        $twoTbBytes = 2 * 1024 * 1024 * 1024 * 1024; // 2 TB exactly

        // features_json had "1 TB storage" — bump to match the new cap
        $features = json_decode((string) $row->features_json, true);
        if (is_array($features)) {
            $features = array_map(function ($f) {
                $f = (string) $f;
                $f = preg_replace('/\b\d+\s*TB\s+storage/i', '2 TB storage', $f);
                return $f;
            }, $features);
        }

        DB::table('subscription_plans')
            ->where('code', 'studio')
            ->update([
                'storage_bytes' => $twoTbBytes,
                'features_json' => is_array($features) ? json_encode($features, JSON_UNESCAPED_UNICODE) : $row->features_json,
                'updated_at'    => now(),
            ]);

        // Backfill every photographer on Studio so their cached
        // storage_quota_bytes matches the new cap immediately. The
        // self-heal in dashboardSummary would catch this lazily, but
        // running it here means upload middleware enforces 2 TB on the
        // very next request without waiting for a dashboard view.
        DB::table('photographer_profiles')
            ->where('subscription_plan_code', 'studio')
            ->update([
                'storage_quota_bytes' => $twoTbBytes,
                'updated_at'          => now(),
            ]);
    }
};

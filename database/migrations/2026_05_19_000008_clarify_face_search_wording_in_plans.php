<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Clarify the "AI Face Search X ครั้ง/เดือน" line in each plan's
 * features_json. The number is correct — it maps 1:1 to
 * `monthly_ai_credits` in the same row — but the word "ครั้ง"
 * (occurrences) reads as "search queries" while the actual
 * deduction happens per *photo indexed* in
 * `AiTaskService::runFaceIndex()` ($credits = count of photos
 * in the event, debited once per indexer run).
 *
 * Changing copy from "ครั้ง" → "รูป" + adding a clarifying
 * second clause ("ลูกค้าค้นหาได้ไม่จำกัด") so photographers
 * understand:
 *   - Their plan caps how many of THEIR own photos they can
 *     index per month
 *   - Customer face-search hits are governed separately by
 *     FaceSearchBudget (IP / event / global daily caps), not
 *     by the photographer's monthly_ai_credits
 *
 * Idempotent: only updates plans whose features_json still
 * contains the old "ครั้ง" string. Safe to re-run.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('subscription_plans')) {
            return;
        }

        $rewrites = [
            'free' => [
                'AI Face Search 100 ครั้ง/เดือน'   => 'Face Indexing 100 รูป/เดือน · ลูกค้าค้นหาได้ไม่จำกัด',
            ],
            'pro' => [
                'AI Face Search 5,000 ครั้ง/เดือน' => 'Face Indexing 5,000 รูป/เดือน · ลูกค้าค้นหาได้ไม่จำกัด',
            ],
            'studio' => [
                'AI Face Search 50,000 ครั้ง/เดือน' => 'Face Indexing 50,000 รูป/เดือน · ลูกค้าค้นหาได้ไม่จำกัด',
            ],
            // Hidden tiers — keep wording in sync so admin who flips
            // is_public=true on these later doesn't see stale "ครั้ง".
            'starter' => [
                'AI Face Search 1,500 ครั้ง/เดือน' => 'Face Indexing 1,500 รูป/เดือน · ลูกค้าค้นหาได้ไม่จำกัด',
            ],
            'business' => [
                'AI Face Search 20,000 ครั้ง/เดือน' => 'Face Indexing 20,000 รูป/เดือน · ลูกค้าค้นหาได้ไม่จำกัด',
            ],
            'lite' => [
                'AI Face Search 20,000 ภาพ/เดือน'   => 'Face Indexing 20,000 รูป/เดือน · ลูกค้าค้นหาได้ไม่จำกัด',
            ],
        ];

        foreach ($rewrites as $code => $map) {
            $row = DB::table('subscription_plans')->where('code', $code)->first(['features_json']);
            if (!$row) continue;

            $features = json_decode($row->features_json ?? '[]', true) ?: [];
            $changed  = false;

            foreach ($features as $i => $line) {
                foreach ($map as $needle => $replacement) {
                    if ($line === $needle) {
                        $features[$i] = $replacement;
                        $changed = true;
                        break;
                    }
                }
            }

            if ($changed) {
                DB::table('subscription_plans')
                    ->where('code', $code)
                    ->update([
                        'features_json' => json_encode($features, JSON_UNESCAPED_UNICODE),
                        'updated_at'    => now(),
                    ]);
            }
        }

        try {
            \Illuminate\Support\Facades\Cache::forget('public.pricing.plans');
            \Illuminate\Support\Facades\Cache::forget('public.for-photographers.plans');
        } catch (\Throwable) {}
    }

    public function down(): void
    {
        if (!Schema::hasTable('subscription_plans')) {
            return;
        }

        // Revert: turn the new wording back into the old one
        $reverts = [
            'free' => [
                'Face Indexing 100 รูป/เดือน · ลูกค้าค้นหาได้ไม่จำกัด' => 'AI Face Search 100 ครั้ง/เดือน',
            ],
            'pro' => [
                'Face Indexing 5,000 รูป/เดือน · ลูกค้าค้นหาได้ไม่จำกัด' => 'AI Face Search 5,000 ครั้ง/เดือน',
            ],
            'studio' => [
                'Face Indexing 50,000 รูป/เดือน · ลูกค้าค้นหาได้ไม่จำกัด' => 'AI Face Search 50,000 ครั้ง/เดือน',
            ],
            'starter' => [
                'Face Indexing 1,500 รูป/เดือน · ลูกค้าค้นหาได้ไม่จำกัด' => 'AI Face Search 1,500 ครั้ง/เดือน',
            ],
            'business' => [
                'Face Indexing 20,000 รูป/เดือน · ลูกค้าค้นหาได้ไม่จำกัด' => 'AI Face Search 20,000 ครั้ง/เดือน',
            ],
            'lite' => [
                'Face Indexing 20,000 รูป/เดือน · ลูกค้าค้นหาได้ไม่จำกัด' => 'AI Face Search 20,000 ภาพ/เดือน',
            ],
        ];

        foreach ($reverts as $code => $map) {
            $row = DB::table('subscription_plans')->where('code', $code)->first(['features_json']);
            if (!$row) continue;
            $features = json_decode($row->features_json ?? '[]', true) ?: [];
            $changed  = false;
            foreach ($features as $i => $line) {
                foreach ($map as $needle => $replacement) {
                    if ($line === $needle) { $features[$i] = $replacement; $changed = true; break; }
                }
            }
            if ($changed) {
                DB::table('subscription_plans')->where('code', $code)->update([
                    'features_json' => json_encode($features, JSON_UNESCAPED_UNICODE),
                    'updated_at'    => now(),
                ]);
            }
        }

        try {
            \Illuminate\Support\Facades\Cache::forget('public.pricing.plans');
            \Illuminate\Support\Facades\Cache::forget('public.for-photographers.plans');
        } catch (\Throwable) {}
    }
};

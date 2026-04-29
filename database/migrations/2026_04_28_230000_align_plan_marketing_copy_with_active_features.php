<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Align subscription_plans marketing copy with the active feature set.
 *
 * Why a follow-up to 2026_04_28_220000
 * ------------------------------------
 * The previous migration stripped deprecated entries from
 * `subscription_plans.ai_features` (machine-readable JSON array).
 *
 * But `features_json` (the human-facing bullet list shown on
 * /sell-photos and the photographer subscription picker), `tagline`,
 * `description`, and `max_team_seats` were NOT touched — so the public
 * pricing page still advertised features that the plan no longer
 * grants. e.g. Studio still claimed "🎬 Video thumbnail extraction"
 * and "🔌 API access เต็มรูปแบบ" even though those features are
 * gated OFF and stripped from ai_features.
 *
 * Rather than re-edit each Blade view to filter bullets at render
 * time (fragile + scatters the rule), we update the source-of-truth
 * row once. Re-enabling a deprecated feature in the future means
 * flipping the flag AND restoring the bullet — single decision point.
 *
 * Reversibility
 * -------------
 * down() restores the original copy verbatim. If you re-enable a
 * deprecated feature in the future, prefer rolling forward (a new
 * migration) over rolling this one back, so the changelog stays clear.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('subscription_plans')) return;

        // ── Business — drop "Smart Captions" bullet + reset max_team_seats ──
        DB::table('subscription_plans')
            ->where('code', 'business')
            ->update([
                'tagline'         => 'สตูดิโอเล็ก · custom branding · LINE OA',
                'description'    => 'พื้นที่ 500 GB · LINE OA Rich Menu สั่งทำเอง · custom watermark · Priority support',
                'max_team_seats'  => 1,
                'features_json'   => json_encode([
                    '🏢 พื้นที่ทำงาน 500 GB',
                    '🟢 LINE OA: Rich Menu + Broadcast + Multicast',
                    '🤖 AI ไม่จำกัด (200,000 ภาพ/เดือน)',
                    '🎨 Custom watermark + branding',
                    '📊 Customer Behavior Analytics',
                    '📅 อีเวนต์ไม่จำกัด',
                    '⏱️ Priority support (ตอบใน 4 ชม.)',
                    '🧾 e-Tax + e-Receipt + ผูก peakaccount.com',
                    '💰 0% commission · auto-payout',
                ], JSON_UNESCAPED_UNICODE),
                'updated_at'      => now(),
            ]);

        // ── Studio — drop Video Thumbnail + API + Team bullet ─────────────
        DB::table('subscription_plans')
            ->where('code', 'studio')
            ->update([
                'tagline'         => 'Agency / Corporate · White-label',
                'description'    => 'พื้นที่ 2 TB · White-label · Account manager · SLA 99.9%',
                'max_team_seats'  => 1,
                'features_json'   => json_encode([
                    '🏢 พื้นที่ 2 TB (fair use)',
                    '🟢 LINE OA: ทุก feature + Webhook custom',
                    '🤖 AI ทุกฟีเจอร์ · 1,000,000 ภาพ/เดือน',
                    '⚪ White-label (ซ่อนแบรนด์เรา)',
                    '👤 Dedicated Account Manager',
                    '📜 SLA 99.9% guaranteed',
                    '🧾 e-Tax + ใบเสร็จออกในนามบริษัท',
                    '💰 0% commission · daily payout option',
                ], JSON_UNESCAPED_UNICODE),
                'updated_at'      => now(),
            ]);

        // ── Pro — keep auto_tagging bullet (auto_tagging is NOT deprecated) ──
        // Pro plan untouched; its features_json already aligns with the
        // ai_features list that survived the prior migration.

        // Starter, Lite, Free — already aligned; skip.
    }

    public function down(): void
    {
        if (!Schema::hasTable('subscription_plans')) return;

        // Restore Business — original seed
        DB::table('subscription_plans')
            ->where('code', 'business')
            ->update([
                'tagline'        => 'สตูดิโอเล็ก · ทีม 3 คน · custom branding',
                'description'    => 'พื้นที่ 500 GB · ทีม 3 คน · LINE OA Rich Menu สั่งทำเอง · custom watermark · Priority support',
                'max_team_seats' => 3,
                'features_json'  => json_encode([
                    '🏢 พื้นที่ทำงาน 500 GB',
                    '👥 ทีม 3 ผู้ใช้',
                    '🟢 LINE OA: Rich Menu + Broadcast + Multicast',
                    '🤖 AI ไม่จำกัด (200,000 ภาพ/เดือน)',
                    '🎨 Custom watermark + branding',
                    '📊 Customer Behavior Analytics',
                    '💬 Smart Captions หลายภาษา',
                    '📅 อีเวนต์ไม่จำกัด',
                    '⏱️ Priority support (ตอบใน 4 ชม.)',
                    '🧾 e-Tax + e-Receipt + ผูก peakaccount.com',
                    '💰 0% commission · auto-payout',
                ], JSON_UNESCAPED_UNICODE),
                'updated_at'     => now(),
            ]);

        // Restore Studio — original seed
        DB::table('subscription_plans')
            ->where('code', 'studio')
            ->update([
                'tagline'        => 'Agency / Corporate · API + White-label',
                'description'    => 'พื้นที่ 2 TB · ทีม 10 คน · API + White-label · Account manager · SLA 99.9%',
                'max_team_seats' => 10,
                'features_json'  => json_encode([
                    '🏢 พื้นที่ 2 TB (fair use)',
                    '👥 ทีม 10 ผู้ใช้',
                    '🟢 LINE OA: ทุก feature + Webhook custom',
                    '🤖 AI ทุกฟีเจอร์ · 1,000,000 ภาพ/เดือน',
                    '🎬 Video thumbnail extraction',
                    '⚪ White-label (ซ่อนแบรนด์เรา)',
                    '🔌 API access เต็มรูปแบบ',
                    '👤 Dedicated Account Manager',
                    '📜 SLA 99.9% guaranteed',
                    '🧾 e-Tax + ใบเสร็จออกในนามบริษัท',
                    '💰 0% commission · daily payout option',
                ], JSON_UNESCAPED_UNICODE),
                'updated_at'     => now(),
            ]);
    }
};

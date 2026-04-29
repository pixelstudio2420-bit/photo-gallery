<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seed sensible SEO defaults into app_settings.
 *
 * Why
 * ---
 * Audit on 2026-04-28 found that pages whose controllers don't call
 * SeoService->set() (homepage, /photographers list) were rendering
 * `<title>Laravel</title>` because the fallback chain ended at
 * config('app.name'), which is the framework default. With these
 * defaults in place the same fallback chain now ends at a Thai-
 * keyword-rich title and description.
 *
 * Idempotent: only inserts settings that aren't already set, so an
 * admin who customised any of these via /admin/settings/seo keeps
 * their custom values.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('app_settings')) return;

        $defaults = [
            'seo_site_name'        => 'Loadroop · ขายรูปอีเวนต์ออนไลน์',
            'seo_site_tagline'     => 'AI ค้นหาด้วยใบหน้า · ส่งรูปเข้า LINE · 0% commission',
            'seo_site_description' => 'แพลตฟอร์มขายรูปงานอีเวนต์อันดับ 1 ในไทย — ลูกค้าค้นหาตัวเองด้วย AI ใน 3 วินาที, ส่งรูปเข้า LINE หลังจ่ายเงินอัตโนมัติ. ช่างภาพเก็บรายได้ 100% เริ่มฟรี',
            'seo_default_keywords' => 'ขายรูปออนไลน์, ช่างภาพ, ค้นหารูปด้วยใบหน้า, AI Face Search, ภาพอีเวนต์, รูปงานวิ่ง, รูปรับปริญญา, รูปงานแต่ง, รูปคอนเสิร์ต, ส่งรูปเข้า LINE',
            'seo_default_robots'   => 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1',
            'seo_og_locale'        => 'th_TH',
            'seo_title_separator'  => '·',
            'seo_theme_color'      => '#6366f1',
        ];

        foreach ($defaults as $key => $value) {
            $exists = DB::table('app_settings')->where('key', $key)->exists();
            if (!$exists) {
                // app_settings has only key/value/updated_at — no created_at.
                DB::table('app_settings')->insert([
                    'key'        => $key,
                    'value'      => $value,
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('app_settings')) return;
        DB::table('app_settings')->whereIn('key', [
            'seo_site_name', 'seo_site_tagline', 'seo_site_description',
            'seo_default_keywords', 'seo_default_robots', 'seo_og_locale',
            'seo_title_separator', 'seo_theme_color',
        ])->delete();
    }
};

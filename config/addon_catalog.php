<?php

/**
 * Photographer self-serve store catalog.
 *
 * Why config-as-data (not DB)
 * ---------------------------
 * Pricing changes infrequently and goes through code review, not admin
 * UI. Config-driven means:
 *   1. Zero DB query per catalog page load (Cloudflare-cacheable too)
 *   2. Changes are diff-reviewable + revertable via git
 *   3. The OrderObserver activation logic can match on `sku` directly
 *      without joining a catalog table
 *
 * Adding a new addon
 * ------------------
 *   1. Add the entry below with a unique `sku`
 *   2. Add the activation handler in AddonService::activate() match()
 *   3. Bump catalog cache (Cache::forget('addon_catalog_*')) — done
 *
 * SKU naming
 * ----------
 *   {category}.{variant}     boost.monthly, storage.200gb, ai_credits.20k
 *
 * Each row carries enough metadata that the rendering UI can build
 * cards from this alone — no per-addon Blade partials needed.
 */
return [

    // ───────────────────────────────────── PROMOTIONS (the "ads" tier) ─
    'promotion' => [
        'title'       => 'โปรโมทช่างภาพ · ขึ้นอันดับสูง',
        'description' => 'จ่ายเพื่อให้โปรไฟล์คุณแสดงก่อนคู่แข่งในผลการค้นหา · เพิ่ม impressions + booking 30-60%',
        'icon'        => 'bi-rocket-takeoff',
        'accent'      => '#6366f1',
        'items' => [
            [
                'sku'           => 'boost.daily',
                'label'         => 'Boost · 1 วัน',
                'tagline'       => 'ทดลอง 24 ชั่วโมง',
                'price_thb'     => 49,
                'kind'          => 'boost',         // → photographer_promotions.kind
                'cycle'         => 'daily',
                'boost_score'   => 8,
            ],
            [
                'sku'           => 'boost.monthly',
                'label'         => 'Boost · 1 เดือน',
                'tagline'       => 'คุ้มสุด · เริ่มเห็นผลใน 3-5 วัน',
                'price_thb'     => 299,
                'badge'         => 'ขายดี',
                'kind'          => 'boost',
                'cycle'         => 'monthly',
                'boost_score'   => 15,
            ],
            [
                'sku'           => 'boost.yearly',
                'label'         => 'Boost · 1 ปี',
                'tagline'       => 'ประหยัด 30% · 2,499 vs 3,588',
                'price_thb'     => 2499,
                'kind'          => 'boost',
                'cycle'         => 'yearly',
                'boost_score'   => 20,
            ],
            [
                'sku'           => 'featured.monthly',
                'label'         => 'Featured · 1 เดือน',
                'tagline'       => 'การ์ดเด่นในหน้าแรก + badge ⭐',
                'price_thb'     => 590,
                'kind'          => 'featured',
                'cycle'         => 'monthly',
                'boost_score'   => 20,
            ],
            [
                'sku'           => 'featured.yearly',
                'label'         => 'Featured · 1 ปี',
                'tagline'       => 'ประหยัด 22% · ครบที่สุด',
                'price_thb'     => 5490,
                'badge'         => 'พรีเมี่ยม',
                'kind'          => 'featured',
                'cycle'         => 'yearly',
                'boost_score'   => 25,
            ],
            [
                'sku'           => 'highlight.monthly',
                'label'         => 'Highlight Badge',
                'tagline'       => 'ใส่กรอบ + ป้ายเด่นในรายชื่อ',
                'price_thb'     => 149,
                'kind'          => 'highlight',
                'cycle'         => 'monthly',
                'boost_score'   => 8,
            ],
        ],
    ],

    // ────────────────────────────── STORAGE TOP-UP ──
    'storage' => [
        'title'       => 'พื้นที่เก็บงานเสริม',
        'description' => 'ครบ quota แล้ว? ซื้อเพิ่มได้โดยไม่ต้อง upgrade plan · 1 ครั้ง · ใช้ได้ตลอดอายุ subscription',
        'icon'        => 'bi-cloud-arrow-up-fill',
        'accent'      => '#0ea5e9',
        'items' => [
            ['sku' => 'storage.50gb',  'label' => '+50 GB',  'tagline' => 'งาน wedding 1-2 อีเวนต์', 'price_thb' => 290,  'storage_gb' => 50],
            ['sku' => 'storage.200gb', 'label' => '+200 GB', 'tagline' => 'งานวิ่ง 5,000-10,000 รูป',  'price_thb' => 990,  'storage_gb' => 200, 'badge' => 'แนะนำ'],
            ['sku' => 'storage.1tb',   'label' => '+1 TB',   'tagline' => 'ครอบคลุมงานทั้งปี',         'price_thb' => 3990, 'storage_gb' => 1024],
        ],
    ],

    // ────────────────────────────── AI CREDITS TOP-UP ──
    'ai_credits' => [
        'title'       => 'AI Credits เสริม',
        'description' => 'ใช้ AI Face Search / คัดรูป / Best Shot · 1 credit = 1 ภาพประมวลผล · ใช้ได้ตลอดเดือนเดียวกัน',
        'icon'        => 'bi-cpu-fill',
        'accent'      => '#a855f7',
        'items' => [
            ['sku' => 'ai_credits.5k',   'label' => '+5,000',   'tagline' => 'งานรับปริญญา 1 มหาลัย', 'price_thb' => 199,  'credits' => 5000],
            ['sku' => 'ai_credits.20k',  'label' => '+20,000',  'tagline' => 'งานวิ่งใหญ่ 1 อีเวนต์',  'price_thb' => 690,  'credits' => 20000, 'badge' => 'คุ้ม'],
            ['sku' => 'ai_credits.100k', 'label' => '+100,000', 'tagline' => 'ระดับเฟสติวัล',         'price_thb' => 2990, 'credits' => 100000],
        ],
    ],

    // ────────────────────────────── BRANDING / PRIORITY ──
    'branding' => [
        'title'       => 'Branding & Priority',
        'description' => 'จ่ายครั้งเดียว · ใช้ตลอดอายุ subscription · ตัดเหนือคู่แข่งในด้าน UX',
        'icon'        => 'bi-palette-fill',
        'accent'      => '#10b981',
        'items' => [
            [
                'sku'         => 'branding.custom_watermark',
                'label'       => 'Custom Watermark',
                'tagline'     => 'ลายน้ำเฉพาะแบรนด์คุณ · ฝังทุกรูปอัตโนมัติ',
                'price_thb'   => 990,
                'one_time'    => true,
            ],
            [
                'sku'         => 'priority.upload_lane',
                'label'       => 'Priority Upload',
                'tagline'     => 'อัปโหลดข้ามคิวธรรมดา · เร็วกว่า 2-3 เท่าช่วง peak',
                'price_thb'   => 290,
                'cycle'       => 'monthly',
            ],
        ],
    ],

];

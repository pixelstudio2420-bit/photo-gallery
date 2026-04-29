<?php

/**
 * Auto-SEO templates per route.
 *
 * The AutoSeoGenerator looks up the current request's route name in
 * this file and renders the matching template using context extracted
 * from the route parameters + request data.
 *
 * Placeholders
 * ------------
 * Use `:name` as placeholder. The generator substitutes from the
 * `context` array passed to ::generate(), falling back to empty string.
 *
 * Available context keys (provided by the generator base class):
 *   :brand          — site name (from app_settings.seo_site_name)
 *   :site_tagline   — site tagline
 *   :year           — current year
 *   :page           — pagination page number ("หน้า 2 of 10")
 *
 * Per-route generators ADD their own context:
 *   events.show     → :event_name, :event_date, :location, :photo_count, :photographer
 *   photographers.show → :photographer_name, :photographer_code, :events_count, :location
 *   seo.landing.*   → :niche, :scope, :niche_label, :pretty_keyword
 *
 * SEO best-practice length caps
 * -----------------------------
 *   title       : 50-60 chars (Google truncates 60 mobile / 70 desktop)
 *   description : 70-160 chars (155 ideal mobile)
 *   og_title    : ≤95 chars (Facebook limit)
 *   og_description : ≤200 chars
 *
 * The generator clips automatically using mb_substr — but writing the
 * template within the limits avoids ugly mid-word truncation.
 */
return [

    /* ──────────────────────────────────────────────────────────────────
       Per-route templates. Key = route name. The * suffix matches all
       sub-routes if a more specific route isn't found.
       ────────────────────────────────────────────────────────────────── */

    'routes' => [

        'home' => [
            'title'       => 'ค้นหาและซื้อรูปงานอีเวนต์ของคุณด้วย AI Face Search',
            'description' => 'แพลตฟอร์มซื้อขายรูปงานอีเวนต์อันดับ 1 ในไทย — งานวิ่ง รับปริญญา แต่งงาน คอนเสิร์ต. ค้นหาตัวเองด้วย AI ใน 3 วินาที, จ่ายเงิน → รับรูปทาง LINE ทันที',
            'keywords'    => 'ซื้อรูปออนไลน์, ค้นหารูปด้วยใบหน้า, AI Face Search, รูปงานวิ่ง, รูปรับปริญญา, รูปงานแต่ง, ภาพอีเวนต์, ช่างภาพไทย',
            'og_type'     => 'website',
        ],

        'sell-photos' => [
            'title'       => 'ขายรูปออนไลน์ฟรี · 0% commission · ส่งเข้า LINE อัตโนมัติ',
            'description' => 'แพลตฟอร์มสำหรับช่างภาพไทยขายรูปงานอีเวนต์ — เก็บได้ 100% เต็ม, AI Face Search, e-Tax invoice, auto-payout ทุกวันจันทร์',
            'keywords'    => 'ขายรูปออนไลน์, ขายรูปงานอีเวนต์, แพลตฟอร์มช่างภาพ, 0% commission',
            'og_type'     => 'website',
        ],

        'events.index' => [
            'title'       => 'อีเวนต์ทั้งหมด · ค้นหาตัวเองด้วย AI :page',
            'description' => 'รวมงานอีเวนต์ทั้งหมดที่ถ่ายไว้ — งานวิ่ง รับปริญญา แต่งงาน คอนเสิร์ต. คลิกเพื่อค้นหารูปตัวเองด้วย AI Face Search',
            'keywords'    => 'อีเวนต์, รูปงานวิ่ง, รูปรับปริญญา, รูปงานแต่ง, ค้นหารูปด้วยใบหน้า',
            'og_type'     => 'website',
        ],

        'events.show' => [
            'title'       => ':event_name · รูปจากงาน :location :event_date',
            'description' => 'รูปจากงาน :event_name โดย :photographer :location · :event_date · มี :photo_count รูป · ค้นหาตัวเองด้วย AI Face Search · รับรูปทาง LINE',
            'keywords'    => ':event_name, รูป :event_name, :location, ค้นหารูป, :photographer',
            'og_type'     => 'event',
        ],

        'photographers.show' => [
            'title'       => ':photographer_name · ช่างภาพอีเวนต์ :location',
            'description' => ':photographer_name (:photographer_code) — ช่างภาพอีเวนต์ที่มีงานในระบบ :events_count อีเวนต์. ดูพอร์ตโฟลิโอ จองงาน หรือค้นหารูปจากงานของช่างภาพคนนี้',
            'keywords'    => ':photographer_name, ช่างภาพ, :photographer_code, :location, จองช่างภาพ',
            'og_type'     => 'profile',
        ],

        'blog.index' => [
            'title'       => 'บทความ · เคล็ดลับและข่าวสารวงการช่างภาพ',
            'description' => 'รวมบทความและเคล็ดลับเกี่ยวกับการถ่ายภาพ การจัดการงานอีเวนต์ และเทคนิคการขายรูปออนไลน์ — อัปเดตทุกสัปดาห์',
            'keywords'    => 'บทความช่างภาพ, เทคนิคถ่ายภาพ, ข่าวสารช่างภาพ, blog ช่างภาพไทย',
            'og_type'     => 'website',
        ],

        'blog.show' => [
            'title'       => ':post_title',
            'description' => ':post_excerpt',
            'keywords'    => ':post_tags',
            'og_type'     => 'article',
        ],

        'products.index' => [
            'title'       => 'สินค้าดิจิทัล · พรีเซ็ตและทรัพยากรช่างภาพ',
            'description' => 'รวมสินค้าดิจิทัลสำหรับช่างภาพ — Lightroom presets, overlays, fonts, และทรัพยากรอื่น ๆ. ดาวน์โหลดได้ทันทีหลังจ่ายเงิน',
            'keywords'    => 'lightroom preset, ทรัพยากรช่างภาพ, สินค้าดิจิทัล, ดาวน์โหลด preset',
            'og_type'     => 'website',
        ],

        'products.show' => [
            'title'       => ':product_name · :product_price บาท',
            'description' => ':product_description · ดาวน์โหลดได้ทันทีหลังจ่ายเงิน',
            'og_type'     => 'product',
        ],

        'seo.landing.niche' => [
            'title'       => ':niche_label ทั่วประเทศ · ค้นหาด้วย AI · LINE',
            'description' => 'รวม:plural ทั่วประเทศ · ค้นหารูปตัวเองด้วย AI Face Search · ส่งเข้า LINE อัตโนมัติ · ดาวน์โหลดได้ทันที',
            'keywords'    => ':niche_label, :pretty_keyword, :long_tail_csv',
            'og_type'     => 'website',
        ],

        'seo.landing.province' => [
            'title'       => ':niche_label :province · ค้นหาด้วย AI · LINE',
            'description' => ':description_pat',
            'keywords'    => ':niche_label :province, :pretty_keyword :province, :long_tail_csv',
            'og_type'     => 'website',
        ],

        'help' => [
            'title'       => 'ศูนย์ช่วยเหลือ · คำถามที่พบบ่อย',
            'description' => 'รวมคำถามที่พบบ่อยและคู่มือการใช้งาน — สำหรับลูกค้าและช่างภาพ',
            'og_type'     => 'website',
        ],

        'contact' => [
            'title'       => 'ติดต่อเรา · ทีมงาน :brand',
            'description' => 'ติดต่อทีมงาน :brand — ส่งคำถามหรือปัญหา เราตอบกลับภายใน 24 ชั่วโมง',
            'og_type'     => 'website',
        ],

        'legal.show' => [
            'title'       => ':legal_title',
            'description' => ':legal_excerpt',
            'og_type'     => 'website',
            'meta_robots' => 'index, follow',
        ],
    ],

    /* ──────────────────────────────────────────────────────────────────
       Default fallback template — used when no route-specific entry
       matches. Avoids ever rendering a public page with empty SEO.
       ────────────────────────────────────────────────────────────────── */
    'default' => [
        'title'       => 'รูปงานอีเวนต์คุณภาพ · ค้นหาด้วย AI · ส่งเข้า LINE',
        'description' => ':site_tagline · เว็บไซต์ :brand รวบรวมงานอีเวนต์จากช่างภาพมืออาชีพทั่วประเทศ',
        'keywords'    => 'รูปอีเวนต์, ช่างภาพ, AI Face Search, รูปงานวิ่ง, รูปรับปริญญา, รูปงานแต่ง',
        'og_type'     => 'website',
    ],

    /* ──────────────────────────────────────────────────────────────────
       Routes that should NEVER auto-generate (extra belt+suspenders on
       top of AdminNoindex middleware). Match by route name prefix.
       ────────────────────────────────────────────────────────────────── */
    'suppress_routes' => [
        'admin.', 'photographer.', 'api.', '2fa.', 'auth.',
        'login', 'register', 'logout',
        'profile.', 'cart.', 'checkout.',
    ],

];

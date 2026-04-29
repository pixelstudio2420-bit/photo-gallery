<?php

/**
 * Programmatic SEO config — niche × province matrix.
 *
 * Why a flat config (not DB-backed)
 * ---------------------------------
 * The niche/province lists change once per quarter at most; serving
 * them from a config file means:
 *   - zero DB query per landing-page hit (Cloudflare-cacheable too)
 *   - changes go through code review with the rest of the marketing
 *     copy, instead of getting silently flipped via /admin
 *   - the sitemap generator can iterate without touching the DB
 *
 * Adding a niche or province:
 *   1. Add the row here (slug = ASCII-safe, label = Thai shown to user).
 *   2. Bump SeoLandingController::CACHE_VERSION to invalidate edge cache.
 *   3. Sitemap regenerates on next request — no migration needed.
 *
 * URL shape served by SeoLandingController:
 *   /pro/{niche}              → "ช่างภาพงานแต่งทั่วประเทศ"
 *   /pro/{niche}/{province}   → "ช่างภาพงานแต่ง กรุงเทพ"
 *
 * 6 niches × (1 nationwide + 12 provinces) = 78 unique landing pages.
 */
return [

    // ---------------------------------------------------------------- niches
    // Each row drives:
    //   - URL slug (ASCII so URLs stay copy-pasteable in old apps)
    //   - the H1, meta title, meta description templates
    //   - which keywords show up as <link rel="alternate" hreflang="..."> etc.
    'niches' => [
        'wedding' => [
            'label'           => 'ช่างภาพงานแต่ง',
            'plural'          => 'ช่างภาพงานแต่งงาน',
            'category_slug'   => 'wedding',          // matches event_categories.slug if present
            'pretty_keyword'  => 'ถ่ายภาพงานแต่ง',
            'long_tail'       => ['ช่างภาพงานแต่งงาน', 'ช่างภาพ pre-wedding', 'ช่างภาพแต่งงานไทย', 'ช่างภาพถ่ายเช้า'],
            'icon'            => 'bi-heart-fill',
            'accent_hex'      => '#ec4899',
            'description_pat' => 'รวมช่างภาพงานแต่งงานคุณภาพมืออาชีพ :scope: · ค้นหารูปตัวเองด้วย AI Face Search · ส่งรูปเข้า LINE อัตโนมัติ · จองออนไลน์ทันที · ดาวน์โหลดได้ทันทีหลังจ่ายเงิน',
            'h1_pat'          => 'ช่างภาพงานแต่งงาน :scope:',
            'sample_event_q'  => 'wedding|แต่ง',
        ],
        'graduation' => [
            'label'           => 'ช่างภาพรับปริญญา',
            'plural'          => 'ช่างภาพรับปริญญา',
            // event_categories has 'education' as the closest match;
            // no graduation slug exists.
            'category_slug'   => 'education',
            'pretty_keyword'  => 'ถ่ายภาพรับปริญญา',
            'long_tail'       => ['ช่างภาพรับปริญญามหาวิทยาลัย', 'ช่างภาพรับปริญญาคู่', 'ช่างภาพถ่ายรับปริญญานอกสถานที่', 'หารูปรับปริญญาด้วยใบหน้า'],
            'icon'            => 'bi-mortarboard-fill',
            'accent_hex'      => '#6366f1',
            'description_pat' => 'ช่างภาพรับปริญญามืออาชีพ :scope: รวมรูปอัตโนมัติทุกมหาวิทยาลัย · ค้นหารูปตัวเองด้วย AI Face Search ใน 3 วินาที · ดาวน์โหลดได้ทันทีหลังจ่ายเงิน',
            'h1_pat'          => 'ช่างภาพรับปริญญา :scope:',
            'sample_event_q'  => 'graduation|ปริญญา',
        ],
        'running' => [
            'label'           => 'ช่างภาพงานวิ่ง',
            'plural'          => 'ช่างภาพงานวิ่งมาราธอน',
            // 'sports' is the closest match in event_categories (no
            // dedicated 'running' slug). Filter then narrows by name LIKE.
            'category_slug'   => 'sports',
            'pretty_keyword'  => 'ภาพงานวิ่ง',
            'long_tail'       => ['รูปงานวิ่งมาราธอน', 'ค้นหารูปงานวิ่งด้วย bib', 'ช่างภาพงานวิ่ง 10K', 'รูปวิ่งฮาล์ฟมาราธอน'],
            'icon'            => 'bi-stopwatch-fill',
            'accent_hex'      => '#f59e0b',
            'description_pat' => 'รวมรูปงานวิ่งมาราธอน :scope: ทุกระยะ · ค้นหารูปตัวเองในงาน 5,000+ ภาพด้วย AI Face Search · ดาวน์โหลดผ่าน LINE ทันที · จองงานวิ่งครั้งต่อไปได้ในระบบ',
            'h1_pat'          => 'รูปงานวิ่ง :scope:',
            'sample_event_q'  => 'running|วิ่ง|marathon',
        ],
        'concert' => [
            'label'           => 'ช่างภาพคอนเสิร์ต',
            'plural'          => 'ช่างภาพคอนเสิร์ต/อีเวนต์ดนตรี',
            // 'entertainment' covers concerts in the existing taxonomy.
            'category_slug'   => 'entertainment',
            'pretty_keyword'  => 'รูปคอนเสิร์ต',
            'long_tail'       => ['รูปคอนเสิร์ตศิลปินไทย', 'ช่างภาพคอนเสิร์ต K-Pop', 'รูป meet & greet', 'รูปอีเวนต์ดนตรี'],
            'icon'            => 'bi-music-note-beamed',
            'accent_hex'      => '#a855f7',
            'description_pat' => 'รูปคอนเสิร์ตและอีเวนต์ดนตรี :scope: คุณภาพระดับสตูดิโอ · เผยแพร่หลังจบงาน · ค้นหารูปตัวเองในฝูงชนได้ภายในวินาที',
            'h1_pat'          => 'รูปคอนเสิร์ต :scope:',
            'sample_event_q'  => 'concert|คอนเสิร์ต|music',
        ],
        'corporate' => [
            'label'           => 'ช่างภาพอีเวนต์บริษัท',
            'plural'          => 'ช่างภาพอีเวนต์องค์กร',
            'category_slug'   => 'corporate',
            'pretty_keyword'  => 'ถ่ายภาพอีเวนต์บริษัท',
            'long_tail'       => ['ช่างภาพ company event', 'ช่างภาพ team building', 'ช่างภาพอีเวนต์เปิดตัวสินค้า', 'รูปงานเลี้ยงบริษัท'],
            'icon'            => 'bi-building',
            'accent_hex'      => '#0ea5e9',
            'description_pat' => 'ช่างภาพอีเวนต์บริษัท :scope: ส่งรูปลูกค้า/พนักงาน · ออกใบกำกับภาษีอัตโนมัติ · ใช้ในงาน PR ได้ทันที · ผูก LINE OA สำหรับลูกค้าองค์กร',
            'h1_pat'          => 'ช่างภาพอีเวนต์บริษัท :scope:',
            'sample_event_q'  => 'corporate|company|บริษัท',
        ],
        'prewedding' => [
            'label'           => 'ช่างภาพ Pre-Wedding',
            'plural'          => 'ช่างภาพ Pre-Wedding',
            // No dedicated event_categories.slug for prewedding — fall back
            // to the wedding category. Without this, /events?category=prewedding
            // would have hit the "invalid integer" error before the controller
            // gained slug-resolution.
            'category_slug'   => 'wedding',
            'pretty_keyword'  => 'pre-wedding',
            'long_tail'       => ['pre-wedding outdoor', 'pre-wedding ทะเล', 'pre-wedding studio', 'pre-wedding ในเมือง'],
            'icon'            => 'bi-camera-fill',
            'accent_hex'      => '#10b981',
            'description_pat' => 'ช่างภาพ Pre-Wedding :scope: รับถ่ายในและนอกสถานที่ · ทีมแต่งหน้า + จัดทรงให้ครบเซ็ต · ส่งรูปครบ 100+ ใบทาง LINE · ค้นหาช่างภาพมืออาชีพได้ทันที',
            'h1_pat'          => 'ช่างภาพ Pre-Wedding :scope:',
            'sample_event_q'  => 'prewedding|pre-wedding',
        ],
    ],

    // ------------------------------------------------------------- provinces
    // 12 high-traffic provinces (Bangkok + tourism + university hubs).
    // Population/economic data ranking, not Thai admin alphabetical.
    // Slug = ASCII; label = Thai display.
    'provinces' => [
        'bangkok'       => ['label' => 'กรุงเทพมหานคร',     'short' => 'กรุงเทพ'],
        'chiang-mai'    => ['label' => 'เชียงใหม่',          'short' => 'เชียงใหม่'],
        'phuket'        => ['label' => 'ภูเก็ต',             'short' => 'ภูเก็ต'],
        'pattaya'       => ['label' => 'พัทยา · ชลบุรี',      'short' => 'พัทยา'],
        'hua-hin'       => ['label' => 'หัวหิน · ประจวบฯ',    'short' => 'หัวหิน'],
        'khon-kaen'     => ['label' => 'ขอนแก่น',            'short' => 'ขอนแก่น'],
        'songkhla'      => ['label' => 'สงขลา · หาดใหญ่',    'short' => 'หาดใหญ่'],
        'nakhon-ratchasima' => ['label' => 'นครราชสีมา',     'short' => 'โคราช'],
        'ayutthaya'     => ['label' => 'พระนครศรีอยุธยา',     'short' => 'อยุธยา'],
        'rayong'        => ['label' => 'ระยอง',              'short' => 'ระยอง'],
        'chiang-rai'    => ['label' => 'เชียงราย',           'short' => 'เชียงราย'],
        'udon-thani'    => ['label' => 'อุดรธานี',           'short' => 'อุดรธานี'],
    ],

    // -------------------------------------------------------- shared content
    // Universal selling points injected into every landing page so we don't
    // duplicate copy in each Blade. Keeps content unique enough for Google
    // (the niche-specific paragraphs above carry the "different content"
    // signal) while still giving every page proof of platform features.
    // Customer-facing functional benefits — replaces price-centric copy.
    // Order = visible importance: speed-of-result first, ease-of-receipt
    // second, peace-of-mind third, follow-up fourth.
    'usp_bullets' => [
        ['icon' => 'bi-person-bounding-box', 'title' => 'ค้นหาตัวเองด้วย AI', 'body' => 'อัปโหลด selfie 1 ใบ → ระบบสแกนหารูปคุณในงานทั้งหมด ภายในไม่กี่วินาที'],
        ['icon' => 'bi-line',                'title' => 'รับรูปทาง LINE ทันที', 'body' => 'จ่ายเงินเสร็จ → ระบบส่งรูปเข้า LINE คุณอัตโนมัติ ไม่ต้องรอช่างส่ง Drive link'],
        ['icon' => 'bi-shield-lock',         'title' => 'จองออนไลน์ปลอดภัย', 'body' => 'ชำระผ่าน PromptPay / LINE Pay / บัตรเครดิต · ไม่มีการกักรูปคืนเงิน 100% หากมีปัญหา'],
        ['icon' => 'bi-cloud-arrow-down',    'title' => 'ดาวน์โหลดได้ทันที', 'body' => 'รูปคุณภาพสูง full-resolution พร้อมใช้ทันทีหลังจ่ายเงิน · เก็บลิงก์ไว้ดาวน์โหลดซ้ำได้'],
    ],

    // FAQ — same template across pages but the {scope} substitution makes
    // each rendered FAQ block differ, which is enough for FAQ-rich-snippet
    // eligibility while keeping the canonical Q&A maintenance simple.
    // FAQ — focuses on "how do I use this?" not "how much?". The pricing
    // question was removed by request: prices vary too much by photographer
    // and event size to give a useful single number, and price-anchored
    // FAQs scare off browsers who haven't yet seen the photos.
    'faqs' => [
        ['q' => 'ใช้งานยังไง? ขั้นตอนทั้งหมด?',
         'a' => '1) เปิดอีเวนต์ที่คุณไป  2) อัปโหลด selfie 1 ใบให้ระบบ AI สแกนหา  3) เลือกรูปที่เจอ → จ่ายผ่าน QR PromptPay / LINE Pay / บัตรเครดิต  4) ระบบส่งรูปคุณภาพสูงเข้า LINE ทันที — ดาวน์โหลดเก็บได้ตลอด'],
        ['q' => 'AI ค้นหาด้วยใบหน้าใช้ยังไง?',
         'a' => 'อัปโหลด selfie 1 ใบ (รูปหน้าตัวเองชัด ๆ) → ระบบสแกนรูปทุกใบในงานและคืนรูปที่ "มีหน้าคุณ" ภายในไม่กี่วินาที. แม่นยำมาก แม้คุณใส่หมวก/แว่น/มาส์กก็ยังเจอ.'],
        ['q' => 'รับรูปทาง LINE จริงไหม? ใช้บัญชี LINE ตัวเองได้?',
         'a' => 'จริง — ระบบเชื่อม LINE Login ในขั้นตอนชำระเงิน. หลังจ่าย → รูปคุณภาพสูงส่งเข้า LINE ของคุณ พร้อมลิงก์ดาวน์โหลดอีกครั้ง (กรณีลบเผลอ).'],
        ['q' => 'จองช่างภาพล่วงหน้าได้ไหม? ดูคิวว่าง?',
         'a' => 'ได้ — เข้าโปรไฟล์ช่างภาพคนที่ชอบ → กด "จองงาน" → ระบุวัน-เวลา → ระบบเช็คคิวว่างให้ทันที. ช่างยืนยัน → ได้รับการแจ้งเตือนทาง LINE.'],
        ['q' => 'ถ้าไม่พอใจรูป?',
         'a' => 'ติดต่อช่างภาพได้โดยตรงผ่านระบบ chat. กรณีปัญหาคุณภาพรูป ทีมงานช่วยคืนเงิน 100% ภายใน 7 วันแรกหลังซื้อ.'],
        ['q' => 'มีใบกำกับภาษีไหม? (สำหรับบริษัท)',
         'a' => 'มี — ระบบออก e-Tax invoice อัตโนมัติทุกออเดอร์ที่ระบุข้อมูลบริษัท. ใช้นำไปยื่นภาษีหรือเบิกค่าใช้จ่ายได้ทันที.'],
    ],

];

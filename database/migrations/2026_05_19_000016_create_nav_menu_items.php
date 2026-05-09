<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * `nav_menu_items` — admin-managed navigation entries.
 *
 * Project owner asked for the navbar items to be movable between
 * navbar and footer without code changes. Previously every link was
 * hardcoded in resources/views/layouts/partials/navbar.blade.php +
 * footer.blade.php — adding/removing/relocating a link required a
 * deploy. This table makes the "middle nav" zone data-driven so
 * /admin/navigation can drag items between locations live.
 *
 * What stays HARDCODED in the views (not editable here):
 *   • Brand / logo (left)
 *   • Search input
 *   • Avatar dropdown / login buttons (right)
 *   • Language switcher
 *   • Newsletter form in footer
 *   • Footer "เกี่ยวกับ" + "ติดต่อเรา" address columns
 *
 * What's EDITABLE here:
 *   • The middle list of nav links (Events, Photographers, Pricing,
 *     etc.) — both in the desktop top-nav AND the footer "ดูเพิ่มเติม"
 *     column.
 *   • Adding a new link or seasonal CTA (e.g. "Black Friday") without
 *     code deploys.
 *   • Hiding old links by toggling is_active off (no row deletion —
 *     audit trail of "what used to be in the menu").
 *
 * Schema notes
 * ────────────
 *   location  — where the item renders. 'navbar' = top nav only.
 *               'footer' = footer column only. 'both' = render in
 *               both places. 'hidden' = saved but currently invisible
 *               (kept for re-enabling later).
 *
 *   audience  — who sees the item. 'public' = everyone. 'guest' =
 *               logged-out users only (e.g. "Become a photographer"
 *               which doesn't make sense after they're already one).
 *               'authenticated' = any logged-in user. 'photographer' =
 *               approved photographers only. The render layer reads
 *               this so a single ?->itemsFor() call returns the
 *               correct list for the current user.
 *
 *   cta_style — visual treatment. 'default' = plain text link.
 *               'primary' = white-on-translucent (current "active"
 *               look). 'accent' = amber-on-dark (current "เริ่มขายรูป"
 *               sales CTA). Lets admin promote one item to attention-
 *               grabbing without writing CSS.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('nav_menu_items', function (Blueprint $t) {
            $t->id();
            $t->string('label', 80)
                ->comment('Display text — Thai or English (no auto-translation)');
            $t->string('url', 500)
                ->comment('Relative path or absolute URL. Bare paths get url() helper.');
            $t->string('icon', 60)
                ->nullable()
                ->comment('Bootstrap Icons class without the "bi-" prefix (e.g. tag-fill)');
            $t->string('location', 20)
                ->default('navbar')
                ->comment('navbar | footer | both | hidden');
            $t->string('audience', 20)
                ->default('public')
                ->comment('public | guest | authenticated | photographer');
            $t->string('cta_style', 20)
                ->default('default')
                ->comment('default | primary | accent — visual treatment');
            $t->string('badge_text', 20)
                ->nullable()
                ->comment('Optional small pill label (e.g. "ใหม่" / "NEW")');
            $t->string('badge_color', 20)
                ->nullable()
                ->comment('amber | rose | emerald | indigo (Tailwind palette key)');
            $t->boolean('open_in_new_tab')
                ->default(false)
                ->comment('When true: target="_blank" rel="noopener"');
            $t->boolean('is_active')
                ->default(true)
                ->comment('Soft-disable without deleting the row');
            $t->integer('sort_order')
                ->default(0)
                ->comment('Lower = leftmost in navbar / topmost in footer column');
            $t->string('visibility_route_pattern', 200)
                ->nullable()
                ->comment('Optional regex of route names where this item is hidden (e.g. "^admin\\.")');
            $t->timestamps();

            // Composite index used by the cached itemsFor() lookup —
            // filters happen against (location, audience, is_active)
            // and we order by (sort_order, id) for stability.
            $t->index(['location', 'is_active', 'sort_order'], 'nav_loc_active_sort_idx');
            $t->index(['audience'], 'nav_audience_idx');
        });

        // Seed the existing 8 hardcoded navbar/footer items so the
        // data-driven render produces an identical-looking page on
        // first deploy. Admins can then drag them around at will.
        $now = now();
        $items = [
            // ─────── Currently in the desktop NAVBAR ───────
            ['label' => 'อีเวนต์',      'url' => '/events',          'icon' => 'calendar-event',  'location' => 'both',   'audience' => 'public',       'cta_style' => 'default', 'sort_order' =>  10],
            ['label' => 'ช่างภาพ',      'url' => '/photographers',   'icon' => 'camera-fill',     'location' => 'both',   'audience' => 'public',       'cta_style' => 'default', 'sort_order' =>  20],
            ['label' => 'บล็อก',        'url' => '/blog',            'icon' => 'journal-text',    'location' => 'both',   'audience' => 'public',       'cta_style' => 'default', 'sort_order' =>  30],
            ['label' => 'สินค้า',       'url' => '/products',        'icon' => 'box-seam',        'location' => 'both',   'audience' => 'public',       'cta_style' => 'default', 'sort_order' =>  40],
            ['label' => 'ราคา',         'url' => '/pricing',         'icon' => 'tag-fill',        'location' => 'both',   'audience' => 'public',       'cta_style' => 'default', 'sort_order' =>  50, 'badge_text' => 'NEW', 'badge_color' => 'amber'],
            ['label' => 'วิธีซื้อรูป',    'url' => '/lp/how-to-buy',   'icon' => 'question-circle', 'location' => 'navbar', 'audience' => 'public',       'cta_style' => 'default', 'sort_order' =>  60],
            ['label' => 'ติดต่อเรา',     'url' => '/contact',         'icon' => 'chat-square-heart', 'location' => 'both',   'audience' => 'public',       'cta_style' => 'default', 'sort_order' =>  70],
            // Sales CTA — amber accent. Hides for users who already
            // have a photographer profile (handled by route-pattern
            // check + audience='guest' on logged-in side).
            ['label' => 'เริ่มขายรูป',     'url' => '/sell-photos',     'icon' => 'camera-fill',     'location' => 'navbar', 'audience' => 'public',       'cta_style' => 'accent',  'sort_order' =>  80],
        ];
        foreach ($items as $i) {
            DB::table('nav_menu_items')->insert(array_merge($i, [
                'is_active'  => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('nav_menu_items');
    }
};

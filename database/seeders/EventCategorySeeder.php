<?php

namespace Database\Seeders;

use App\Models\EventCategory;
use Illuminate\Database\Seeder;

/**
 * Seeds the 9 umbrella event categories.
 *
 * History
 * -------
 * Previously seeded 30 narrow categories (Marathon, Cycling, Triathlon,
 * Swimming, etc.). That organic growth made the public filter UI a wall
 * of pills. The 2026_05_03 consolidation migration folds those into 9
 * umbrella buckets; this seeder is the single source of truth going
 * forward.
 *
 * Icons follow Bootstrap Icons naming (bundled with the frontend layout).
 *
 * Idempotent: upserts by slug, so re-running on an existing DB updates
 * names/icons without duplicating rows or disturbing FKs.
 */
class EventCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            // Any athletic event — marathon, cycling, triathlon, team
            // sports, school sports, martial arts, gymnastics…
            ['slug' => 'sports',        'name' => 'กีฬา / Sports',            'icon' => 'bi-trophy-fill'],

            // Weddings + pre-wedding shoots.
            ['slug' => 'wedding',       'name' => 'งานแต่งงาน / Wedding',      'icon' => 'bi-heart-fill'],

            // Cultural & religious observances: Songkran, Loy Krathong,
            // ordination, any community festival.
            ['slug' => 'festival',      'name' => 'งานเทศกาล / Festival',     'icon' => 'bi-balloon-heart'],

            // B2B work: corporate parties, conferences, product launches,
            // trade-show booths.
            ['slug' => 'corporate',     'name' => 'งานองค์กร / Corporate',    'icon' => 'bi-building'],

            // Graduation ceremonies + school events (non-sports).
            ['slug' => 'education',     'name' => 'งานการศึกษา / Education',  'icon' => 'bi-mortarboard'],

            // Concerts, nightlife, private parties.
            ['slug' => 'entertainment', 'name' => 'บันเทิง / Entertainment',  'icon' => 'bi-music-note-beamed'],

            // Studio/on-location portraits and fashion shoots.
            ['slug' => 'portrait',      'name' => 'พอร์ตเทรต / Portrait',     'icon' => 'bi-person-badge'],

            // Travel galleries, pet photography, food & lifestyle work.
            ['slug' => 'lifestyle',     'name' => 'ไลฟ์สไตล์ / Lifestyle',    'icon' => 'bi-airplane'],

            // Catch-all for events that don't fit the eight above — rare
            // by design, but lets a photographer still publish while the
            // taxonomy catches up.
            ['slug' => 'other',         'name' => 'อื่นๆ / Other',             'icon' => 'bi-three-dots'],
        ];

        foreach ($categories as $cat) {
            EventCategory::updateOrCreate(
                ['slug' => $cat['slug']],
                [
                    'name'   => $cat['name'],
                    'icon'   => $cat['icon'],
                    'status' => 'active',
                ]
            );
        }

        $this->command?->info('✓ Event categories seeded: ' . count($categories) . ' umbrella categories.');
    }
}

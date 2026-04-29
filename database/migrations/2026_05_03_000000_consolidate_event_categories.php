<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Consolidate 30 narrow event categories into 9 umbrella categories.
 *
 * Why
 * ---
 * The original list was organic: every time a photographer asked for a new
 * niche (pet photography, triathlon, trade show), we added a row. After a
 * year of that, the taxonomy had 30 entries — many of them single-digit
 * edge cases — and the public filter UI was a wall of pills nobody could
 * scan. Collapsing to 9 main buckets keeps every use case reachable while
 * making the filter UI actually navigable.
 *
 * Mapping table
 * -------------
 *   sports         ← marathon, cycling, triathlon, swimming, football,
 *                    basketball, golf, martial-arts, gymnastics,
 *                    school-sports, งานกีฬามหาวิทยาลัย
 *   wedding        ← wedding, pre-wedding
 *   festival       ← festival, songkran, loy-krathong, ordination
 *   corporate      ← corporate, conference, launch-event, trade-show
 *   education      ← graduation, school-event
 *   entertainment  ← concert, party
 *   portrait       ← portrait, fashion-shoot
 *   lifestyle      ← travel, pet
 *   other          ← other (unchanged)
 *
 * Safety
 * ------
 * Idempotent: re-running this migration on an already-consolidated DB is a
 * no-op (the new slugs already exist; the old ones are already gone). No
 * events are orphaned — every event_events row is repointed to the new
 * parent category BEFORE the old row is deleted.
 */
return new class extends Migration
{
    /**
     * old_slug => new_slug. Anything not listed here is left alone (so
     * custom admin-created categories survive the migration).
     */
    private array $slugMap = [
        // Sports
        'marathon'       => 'sports',
        'cycling'        => 'sports',
        'triathlon'      => 'sports',
        'swimming'       => 'sports',
        'football'       => 'sports',
        'basketball'     => 'sports',
        'golf'           => 'sports',
        'martial-arts'   => 'sports',
        'gymnastics'     => 'sports',
        'school-sports'  => 'sports',

        // Wedding
        'pre-wedding'    => 'wedding',

        // Festival & Tradition
        'songkran'       => 'festival',
        'loy-krathong'   => 'festival',
        'ordination'     => 'festival',

        // Corporate
        'conference'     => 'corporate',
        'launch-event'   => 'corporate',
        'trade-show'     => 'corporate',

        // Education
        'graduation'     => 'education',
        'school-event'   => 'education',

        // Entertainment
        'concert'        => 'entertainment',
        'party'          => 'entertainment',

        // Portrait & Fashion
        'fashion-shoot'  => 'portrait',

        // Lifestyle
        'travel'         => 'lifestyle',
        'pet'            => 'lifestyle',
    ];

    /**
     * Target taxonomy — the 9 umbrella categories. Icon stays pointing at
     * something recognisable for the first photographer who lands on the
     * picker dropdown.
     */
    private array $newCategories = [
        ['slug' => 'sports',        'name' => 'กีฬา / Sports',              'icon' => 'bi-trophy-fill'],
        ['slug' => 'wedding',       'name' => 'งานแต่งงาน / Wedding',       'icon' => 'bi-heart-fill'],
        ['slug' => 'festival',      'name' => 'งานเทศกาล / Festival',       'icon' => 'bi-balloon-heart'],
        ['slug' => 'corporate',     'name' => 'งานองค์กร / Corporate',      'icon' => 'bi-building'],
        ['slug' => 'education',     'name' => 'งานการศึกษา / Education',    'icon' => 'bi-mortarboard'],
        ['slug' => 'entertainment', 'name' => 'บันเทิง / Entertainment',    'icon' => 'bi-music-note-beamed'],
        ['slug' => 'portrait',      'name' => 'พอร์ตเทรต / Portrait',       'icon' => 'bi-person-badge'],
        ['slug' => 'lifestyle',     'name' => 'ไลฟ์สไตล์ / Lifestyle',      'icon' => 'bi-airplane'],
        ['slug' => 'other',         'name' => 'อื่นๆ / Other',               'icon' => 'bi-three-dots'],
    ];

    public function up(): void
    {
        if (!Schema::hasTable('event_categories')) {
            return;
        }

        // The event_categories table was designed without an updated_at
        // column (only created_at), so our inserts omit it. Detect both
        // shapes so this migration is safe on older and newer schemas.
        $hasUpdatedAt = Schema::hasColumn('event_categories', 'updated_at');
        $hasCreatedAt = Schema::hasColumn('event_categories', 'created_at');

        DB::transaction(function () use ($hasUpdatedAt, $hasCreatedAt) {
            // 1) Upsert the 9 umbrella categories by slug so they exist
            //    before we remap — otherwise the re-link step has nowhere
            //    to point at.
            foreach ($this->newCategories as $cat) {
                $values = [
                    'name'   => $cat['name'],
                    'icon'   => $cat['icon'],
                    'status' => 'active',
                ];
                if ($hasUpdatedAt) {
                    $values['updated_at'] = now();
                }
                if ($hasCreatedAt) {
                    // Only set created_at on the insert branch — updateOrInsert
                    // won't overwrite existing rows' timestamps anyway, but
                    // leaving the COALESCE expression out of the payload keeps
                    // the SQL portable for tables without the column.
                    $values['created_at'] = now();
                }
                DB::table('event_categories')->updateOrInsert(
                    ['slug' => $cat['slug']],
                    $values
                );
            }

            // 2) Resolve slug → id for every new umbrella in one query.
            $slugToId = DB::table('event_categories')
                ->whereIn('slug', array_column($this->newCategories, 'slug'))
                ->pluck('id', 'slug')
                ->toArray();

            // 3) For each old slug, look up the old category id and the
            //    target new id, then repoint any events + delete the old
            //    row. Done one slug at a time so a missing row can't
            //    poison the whole remap.
            foreach ($this->slugMap as $oldSlug => $newSlug) {
                $oldId = DB::table('event_categories')->where('slug', $oldSlug)->value('id');
                $newId = $slugToId[$newSlug] ?? null;

                if (!$oldId || !$newId || $oldId === $newId) {
                    continue; // nothing to migrate — slug already gone or already merged
                }

                // Move events to the new parent (if the column exists —
                // this migration may run before the events table in a
                // fresh install, but the Schema::hasTable() guard above
                // catches that case).
                if (Schema::hasTable('event_events') && Schema::hasColumn('event_events', 'category_id')) {
                    DB::table('event_events')
                        ->where('category_id', $oldId)
                        ->update(['category_id' => $newId]);
                }

                // Remove the old row — it's now redundant.
                DB::table('event_categories')->where('id', $oldId)->delete();
            }

            // 4) Handle the edge case: "งานกีฬามหาวิทยาลัย" was seeded with
            //    an empty slug, so it doesn't match any entry in $slugMap
            //    by slug. Remap by name instead.
            $uniSportRow = DB::table('event_categories')
                ->where('name', 'งานกีฬามหาวิทยาลัย')
                ->first();
            if ($uniSportRow && isset($slugToId['sports'])) {
                if (Schema::hasTable('event_events') && Schema::hasColumn('event_events', 'category_id')) {
                    DB::table('event_events')
                        ->where('category_id', $uniSportRow->id)
                        ->update(['category_id' => $slugToId['sports']]);
                }
                DB::table('event_categories')->where('id', $uniSportRow->id)->delete();
            }
        });
    }

    /**
     * Restoring the old 30-category taxonomy on rollback would require
     * re-running the original seeder + data you no longer have (how do
     * you know which old sub-category an event *used to* belong to after
     * we've merged it?). The honest answer is "you don't roll this back
     * — you re-seed manually." Leaving down() as a no-op rather than
     * faking a restoration that produces wrong data.
     */
    public function down(): void
    {
        // intentionally empty: consolidation is one-way
    }
};

<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Event-category consolidation migration test.
 *
 * The 2026_05_03_000000 migration folds 30 narrow slugs (marathon, cycling,
 * pre-wedding, graduation, concert, …) into 9 umbrella categories
 * (sports, wedding, education, entertainment, …). Because it runs against
 * live production data, three properties matter:
 *
 *   1. Events pointing at an old category get repointed to the umbrella
 *      — no orphaned FKs.
 *   2. Old category rows are removed so the admin picker isn't cluttered.
 *   3. Re-running the migration on an already-consolidated DB is a no-op.
 *
 * `RefreshDatabase` already runs every migration (including this one) into
 * a fresh sqlite :memory: at setup, so the table starts with just the 9
 * new slugs. These tests seed the OLD shape by hand and invoke the
 * migration's `up()` directly to prove the remap still works from the
 * real pre-consolidation state.
 */
class EventCategoryConsolidationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Path to the migration file. Used so we can `require` the anonymous
     * class directly without relying on artisan migrate running it twice.
     */
    private function migrationPath(): string
    {
        return database_path('migrations/2026_05_03_000000_consolidate_event_categories.php');
    }

    private function loadMigration(): object
    {
        return require $this->migrationPath();
    }

    // ─── Remap moves events and deletes the old row ───

    public function test_migration_repoints_events_to_umbrella_and_deletes_old_category(): void
    {
        // Simulate a pre-consolidation state: "marathon" as an old narrow
        // category with an event attached, before the migration has ever
        // run on this data.
        $marathonId = DB::table('event_categories')->insertGetId([
            'name'       => 'Marathon',
            'slug'       => 'marathon',
            'icon'       => 'bi-stopwatch',
            'status'     => 'active',
            'created_at' => now(),
        ]);

        $eventId = DB::table('event_events')->insertGetId([
            'name'        => 'Bangkok Marathon 2026',
            'slug'        => 'bangkok-marathon-2026-' . uniqid(),
            'category_id' => $marathonId,
            'status'      => 'published',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        // Re-run the consolidation migration on this seeded data.
        $this->loadMigration()->up();

        // The old narrow category should be gone.
        $this->assertDatabaseMissing('event_categories', ['slug' => 'marathon']);

        // The event must now point at the umbrella — never orphaned.
        $sportsId = DB::table('event_categories')->where('slug', 'sports')->value('id');
        $this->assertNotNull($sportsId, 'sports umbrella category must exist after migration');

        $this->assertEquals(
            $sportsId,
            DB::table('event_events')->where('id', $eventId)->value('category_id'),
            'event should be repointed to sports umbrella'
        );
    }

    // ─── Running twice leaves state untouched (idempotent) ───

    public function test_migration_is_idempotent(): void
    {
        $migration = $this->loadMigration();

        // First run on a clean (already-consolidated) DB.
        $migration->up();

        $snapshot = DB::table('event_categories')
            ->orderBy('slug')
            ->pluck('slug')
            ->toArray();

        // Second run — must not duplicate rows or throw.
        $migration->up();

        $after = DB::table('event_categories')
            ->orderBy('slug')
            ->pluck('slug')
            ->toArray();

        $this->assertSame(
            $snapshot,
            $after,
            'running the migration twice must not duplicate or reorder categories'
        );
    }

    // ─── All 9 umbrella slugs exist after migration ───

    public function test_all_umbrella_categories_exist_after_migration(): void
    {
        $this->loadMigration()->up();

        $expected = [
            'sports', 'wedding', 'festival', 'corporate', 'education',
            'entertainment', 'portrait', 'lifestyle', 'other',
        ];

        foreach ($expected as $slug) {
            $this->assertDatabaseHas('event_categories', ['slug' => $slug]);
        }
    }

    // ─── Remap handles the empty-slug "งานกีฬามหาวิทยาลัย" edge case ───

    public function test_legacy_empty_slug_row_is_remapped_by_name(): void
    {
        // The legacy "งานกีฬามหาวิทยาลัย" row was seeded with a blank slug,
        // so the slugMap can't catch it by slug. The migration has a
        // fallback that matches by name — this locks that behaviour in.
        // sqlite's UNIQUE(slug) forbids another empty string, so we use a
        // throwaway unique value. The migration's fallback key is name.
        $legacyId = DB::table('event_categories')->insertGetId([
            'name'       => 'งานกีฬามหาวิทยาลัย',
            'slug'       => 'legacy-uni-sport-' . uniqid(),
            'icon'       => 'bi-trophy',
            'status'     => 'active',
            'created_at' => now(),
        ]);

        $eventId = DB::table('event_events')->insertGetId([
            'name'        => 'University Games',
            'slug'        => 'uni-games-' . uniqid(),
            'category_id' => $legacyId,
            'status'      => 'published',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $this->loadMigration()->up();

        $sportsId = DB::table('event_categories')->where('slug', 'sports')->value('id');
        $this->assertEquals(
            $sportsId,
            DB::table('event_events')->where('id', $eventId)->value('category_id'),
            'university-sport legacy row should be remapped by name → sports umbrella'
        );

        $this->assertDatabaseMissing('event_categories', ['id' => $legacyId]);
    }

    // ─── Admin-created custom slugs survive consolidation ───

    public function test_custom_admin_categories_are_preserved(): void
    {
        // An admin adds a niche category that isn't in the slugMap. The
        // migration should leave it alone — we only remap the 22 old
        // narrow slugs we explicitly know about.
        $customId = DB::table('event_categories')->insertGetId([
            'name'       => 'Drone Photography',
            'slug'       => 'drone',
            'icon'       => 'bi-airplane',
            'status'     => 'active',
            'created_at' => now(),
        ]);

        $this->loadMigration()->up();

        $this->assertDatabaseHas('event_categories', [
            'id'   => $customId,
            'slug' => 'drone',
        ]);
    }
}

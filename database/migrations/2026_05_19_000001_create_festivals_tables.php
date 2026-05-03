<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Festivals — themed seasonal popups that surface to customers in
 * their celebratory windows (Songkran, Loy Krathong, NYE, etc.).
 *
 * Why a separate table from `announcements`:
 *   • Festivals are a recurring CONCEPT (admin maintains the rule
 *     once and bumps the year). Announcements are individual messages.
 *   • Themed visuals — water/lantern/firework — need their own variant
 *     enum and renderer; mixing with general announcements would
 *     muddy the priority + audience semantics.
 *   • Admin needs a separate "is the Songkran popup live right now"
 *     screen, which is hard to surface when filtered through 200 ad-hoc
 *     announcements.
 *
 * Dismissal model mirrors announcement_dismissals — same pattern, same
 * semantics ("once dismissed, never seen again until admin un-dismisses
 * or creates a new festival_id"). Keeping schemas symmetric makes the
 * popup partial code interchangeable.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('festivals')) {
            Schema::create('festivals', function (Blueprint $t) {
                $t->id();

                // ── Identity ───────────────────────────────────────
                $t->string('slug', 80)->unique();
                $t->string('name', 200);                  // "สงกรานต์ 2026"
                $t->string('short_name', 80)->nullable(); // "สงกรานต์" — used in compact UIs

                // ── Theme ──────────────────────────────────────────
                // Stored as a string so adding a new theme variant
                // doesn't require a schema migration. Service layer
                // maps slug → CSS palette + decorative assets.
                $t->string('theme_variant', 40)->default('water-blue');
                $t->string('emoji', 30)->nullable();      // '💦', '🏮', '🎆'

                // ── Active window ──────────────────────────────────
                // starts_at/ends_at = the actual celebration days.
                // popup_lead_days = how many days BEFORE starts_at the
                // popup begins teasing the festival. Default 7 = a
                // week's notice, enough to drive bookings without
                // exhausting the audience.
                $t->date('starts_at');
                $t->date('ends_at');
                $t->unsignedSmallInteger('popup_lead_days')->default(7);

                // ── Recurrence ─────────────────────────────────────
                // True for annual festivals — admin bumps starts_at/ends_at
                // each year. Recurrence detection itself isn't automated
                // (calendars vary: lunar, fixed, royal-decree). Admin
                // gets a yearly reminder via a console command.
                $t->boolean('is_recurring')->default(true);

                // ── Content ────────────────────────────────────────
                $t->string('headline', 250);
                $t->text('body_md')->nullable();          // markdown
                $t->string('cta_label', 80)->nullable();
                $t->string('cta_url', 500)->nullable();
                $t->string('cover_image_path', 500)->nullable(); // R2 key

                // ── Targeting (optional) ───────────────────────────
                // NULL province_id = nationwide. Setting a province
                // limits the popup to users in that province (e.g.
                // Loy Krathong is celebrated everywhere but lantern-
                // releases are mostly Chiang Mai → admin can scope).
                $t->unsignedBigInteger('target_province_id')->nullable();

                // ── State ──────────────────────────────────────────
                $t->boolean('enabled')->default(true);
                $t->unsignedSmallInteger('show_priority')->default(0); // higher wins on overlap

                $t->timestamps();
                $t->softDeletes();

                // Indexes for the popup query — most-common filter is
                // (enabled, time-window-active) so index those first.
                $t->index(['enabled', 'starts_at', 'ends_at'], 'festivals_active_idx');
                $t->index('target_province_id');
            });
        }

        if (!Schema::hasTable('festival_dismissals')) {
            Schema::create('festival_dismissals', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('festival_id');
                $t->unsignedBigInteger('user_id');
                $t->timestamp('dismissed_at')->useCurrent();

                // One dismissal per (festival, user). Re-trying a POST
                // is idempotent because of this unique constraint.
                $t->unique(['festival_id', 'user_id']);
                $t->index('user_id');
            });
        }

        // ── Window sanity check ──────────────────────────────────
        // ends_at must be ≥ starts_at, and popup_lead_days reasonable
        // (0-90 days). Belt-and-braces: same checks live in admin
        // form validation, but a bad seed/manual SQL update should
        // also bounce off the DB.
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("
                ALTER TABLE festivals
                ADD CONSTRAINT festivals_window_chk
                CHECK (ends_at >= starts_at AND popup_lead_days BETWEEN 0 AND 90)
            ");
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE festivals DROP CONSTRAINT IF EXISTS festivals_window_chk');
        }
        Schema::dropIfExists('festival_dismissals');
        Schema::dropIfExists('festivals');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Track WHERE each festival's dates came from. Three source values:
 *
 *   'manual'   — admin set dates by hand (custom festivals or edits)
 *   'internal' — FestivalsSeeder used internal logic (fixed-date helpers
 *                or LUNAR_DATES table)
 *   'google'   — Google Calendar API matched + overrode internal date
 *
 * Used by the admin UI to badge each card with where its dates came
 * from (so admin understands "if I sync with Google, this Songkran
 * date will be replaced by Google's"), and by the sync command to
 * preserve manual edits when re-syncing.
 *
 * Default 'internal' for existing rows — the seeder seeded them.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('festivals')) return;
        if (Schema::hasColumn('festivals', 'date_source')) return;

        Schema::table('festivals', function (Blueprint $t) {
            $t->string('date_source', 20)->default('internal')->after('is_recurring');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('festivals')) return;
        if (!Schema::hasColumn('festivals', 'date_source')) return;

        Schema::table('festivals', function (Blueprint $t) {
            $t->dropColumn('date_source');
        });
    }
};

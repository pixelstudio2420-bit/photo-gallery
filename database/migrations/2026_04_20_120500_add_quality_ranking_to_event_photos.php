<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('event_photos', function (Blueprint $t) {
            // Composite 0-100 score from sharpness, exposure, moderation, engagement
            $t->decimal('quality_score', 5, 2)->nullable()->after('moderation_score');
            // Dense 1-based rank within the same event (1 = best)
            $t->unsignedInteger('rank_position')->nullable()->after('quality_score');
            // Raw signals for transparency / debugging
            $t->json('quality_signals')->nullable()->after('rank_position');
            // Last time the scorer ran
            $t->timestamp('quality_scored_at')->nullable()->after('quality_signals');

            $t->index(['event_id', 'rank_position']);
            $t->index(['event_id', 'quality_score']);
        });
    }

    public function down(): void
    {
        Schema::table('event_photos', function (Blueprint $t) {
            $t->dropIndex(['event_id', 'rank_position']);
            $t->dropIndex(['event_id', 'quality_score']);
            $t->dropColumn(['quality_score', 'rank_position', 'quality_signals', 'quality_scored_at']);
        });
    }
};

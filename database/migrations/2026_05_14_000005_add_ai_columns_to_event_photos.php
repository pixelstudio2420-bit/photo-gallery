<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-photo AI metadata.
 *
 * Each AI feature stamps its result on the photo row so:
 *   - Subsequent AI features can build on it (best_shot reads quality + faces)
 *   - The photographer's gallery can filter / sort by it
 *   - Re-running the same feature on the same photo is a no-op
 *
 * Columns:
 *   - phash:           perceptual hash (16 hex chars) for duplicate detection
 *   - quality_score:   0..100 from quality_filter (blur + exposure)
 *   - is_blurry:       fast boolean derived from quality_score
 *   - ai_tags:         JSON array of label strings from auto_tagging
 *   - face_count:      detected faces (from face_search indexing)
 *   - best_shot_score: 0..100 composite ranking from best_shot
 *   - color_enhanced_path: path to the enhanced variant (color_enhance)
 *   - caption:         smart_captions output
 */
return new class extends Migration
{
    public function up(): void
    {
        // event_photos already has quality_score, quality_signals,
        // moderation_labels, rekognition_face_id from prior migrations.
        // We only add the AI metadata that doesn't yet exist.
        Schema::table('event_photos', function (Blueprint $t) {
            if (!Schema::hasColumn('event_photos', 'phash')) {
                $t->string('phash', 32)->nullable();
                $t->index('phash');
            }
            if (!Schema::hasColumn('event_photos', 'is_blurry')) {
                $t->boolean('is_blurry')->default(false);
            }
            if (!Schema::hasColumn('event_photos', 'ai_tags')) {
                $t->json('ai_tags')->nullable();
            }
            if (!Schema::hasColumn('event_photos', 'face_count')) {
                $t->unsignedSmallInteger('face_count')->default(0);
            }
            if (!Schema::hasColumn('event_photos', 'best_shot_score')) {
                $t->unsignedTinyInteger('best_shot_score')->nullable();
            }
            if (!Schema::hasColumn('event_photos', 'color_enhanced_path')) {
                $t->string('color_enhanced_path')->nullable();
            }
            if (!Schema::hasColumn('event_photos', 'caption')) {
                $t->text('caption')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('event_photos', function (Blueprint $t) {
            foreach (['phash','is_blurry','ai_tags','face_count','best_shot_score','color_enhanced_path','caption'] as $col) {
                if (Schema::hasColumn('event_photos', $col)) {
                    $t->dropColumn($col);
                }
            }
        });
    }
};

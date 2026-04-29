<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lightroom-style preset library.
 *
 * Each row is one preset — either a system-shipped one (photographer_id NULL)
 * that every photographer can use as-is, or a per-photographer custom preset
 * (created from scratch or imported from a Lightroom .xmp file).
 *
 * The `settings` JSON stores normalised adjustments that the PHP/GD pipeline
 * understands. Lightroom's full feature set (HSL per-color, tone curve, lens
 * correction) isn't possible in pure GD — we extract the high-impact basics:
 *
 *   exposure       : -1.0 .. +1.0  (EV stops)
 *   contrast       : -100 .. +100
 *   highlights     : -100 .. +100
 *   shadows        : -100 .. +100
 *   whites         : -100 .. +100
 *   blacks         : -100 .. +100
 *   vibrance       : -100 .. +100
 *   saturation     : -100 .. +100
 *   temperature    : -100 .. +100  (warm + / cool -)
 *   tint           : -100 .. +100  (magenta + / green -)
 *   clarity        : -100 .. +100  (mid-contrast)
 *   sharpness      : 0 .. 100
 *   grayscale      : bool          (B&W conversion)
 *   vignette       : -100 .. +100  (negative = darken edges)
 *
 * `preview_path` is a small JPEG thumbnail (rendered on a sample image)
 * that the photographer sees in the preset picker.
 *
 * `is_default` is per-photographer — when set, ProcessUploadedPhotoJob
 * applies this preset automatically to every uploaded photo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('photographer_presets', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('photographer_id')->nullable(); // NULL = system preset
            $t->string('name', 100);
            $t->string('description', 250)->nullable();
            $t->json('settings');               // adjustments above
            $t->string('preview_path')->nullable();
            $t->string('source_xmp_path')->nullable(); // original .xmp for re-import
            $t->boolean('is_system')->default(false);
            $t->boolean('is_default')->default(false);  // auto-apply on upload
            $t->boolean('is_active')->default(true);
            $t->unsignedSmallInteger('sort_order')->default(0);
            $t->timestamps();

            $t->index(['photographer_id', 'is_active']);
            $t->index(['is_system', 'sort_order']);
        });

        // Photo-level stamps — which preset was applied + where the rendered
        // output lives. Different from color_enhanced_path (which is the AI
        // auto-enhance output) because presets carry photographer intent.
        Schema::table('event_photos', function (Blueprint $t) {
            if (!Schema::hasColumn('event_photos', 'preset_id')) {
                $t->unsignedBigInteger('preset_id')->nullable()->index();
            }
            if (!Schema::hasColumn('event_photos', 'preset_applied_path')) {
                $t->string('preset_applied_path')->nullable();
            }
            if (!Schema::hasColumn('event_photos', 'preset_applied_at')) {
                $t->timestamp('preset_applied_at')->nullable();
            }
        });

        // Default preset — set per-photographer so every uploaded photo
        // gets the same look without having to click anything.
        Schema::table('photographer_profiles', function (Blueprint $t) {
            if (!Schema::hasColumn('photographer_profiles', 'default_preset_id')) {
                $t->unsignedBigInteger('default_preset_id')->nullable()->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('photographer_profiles', function (Blueprint $t) {
            if (Schema::hasColumn('photographer_profiles', 'default_preset_id')) {
                $t->dropColumn('default_preset_id');
            }
        });
        Schema::table('event_photos', function (Blueprint $t) {
            foreach (['preset_id', 'preset_applied_path', 'preset_applied_at'] as $col) {
                if (Schema::hasColumn('event_photos', $col)) {
                    $t->dropColumn($col);
                }
            }
        });
        Schema::dropIfExists('photographer_presets');
    }
};

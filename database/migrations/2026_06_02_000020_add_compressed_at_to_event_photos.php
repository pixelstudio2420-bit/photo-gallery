<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add `compressed_at` to event_photos for the CompressAgedOriginalsCommand
 * idempotency check. NULL = original-quality file still on R2;
 * non-NULL = already passed through the recompression pipeline.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('event_photos', function (Blueprint $table) {
            if (!Schema::hasColumn('event_photos', 'compressed_at')) {
                $table->timestamp('compressed_at')->nullable()->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('event_photos', function (Blueprint $table) {
            if (Schema::hasColumn('event_photos', 'compressed_at')) {
                $table->dropColumn('compressed_at');
            }
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-event toggle for the face-search feature.
 *
 * Why opt-out (default TRUE) rather than opt-in:
 *   • Auto-indexing already runs on every upload, so by the time an admin
 *     decides to disable search, photos are still indexed — we just hide the
 *     public UI and block the /api/face-search/{id} endpoint.
 *   • Most events (concerts, marathons, weddings) want face-search on by
 *     default because that's the main selling point of buying access.
 *
 * Admins disable it per-event for:
 *   • Private corporate events where attendees are under NDA
 *   • Events with minors where stricter controls are needed
 *   • Any client who asks for biometric processing to be off
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('event_events')) {
            return;
        }

        if (!Schema::hasColumn('event_events', 'face_search_enabled')) {
            Schema::table('event_events', function (Blueprint $table) {
                $table->boolean('face_search_enabled')
                      ->default(true)
                      ->after('visibility');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('event_events')) {
            return;
        }

        if (Schema::hasColumn('event_events', 'face_search_enabled')) {
            Schema::table('event_events', function (Blueprint $table) {
                $table->dropColumn('face_search_enabled');
            });
        }
    }
};

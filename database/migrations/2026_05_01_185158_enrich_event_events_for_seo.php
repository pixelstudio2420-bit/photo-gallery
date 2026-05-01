<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Enrich event_events with the fields that the public event page,
 * Schema.org Event JSON-LD, and pSEO landing cards actually want
 * but the table never carried.
 *
 * Why each column earns its keep
 * ------------------------------
 *   start_time / end_time
 *     Schema.org Event.startDate is required; endDate makes the SERP
 *     show "Today, 10:00–18:00" instead of just a date. Customers
 *     also want to know "is this an evening event or daytime?" before
 *     deciding to scroll the gallery.
 *
 *   venue_name
 *     `location` is free-text and usually contains the city/area.
 *     A separate venue field lets us emit Schema.org Place.name
 *     ("Impact Arena") AND keep the area as Place.address — Google
 *     scores BOTH and combines them in rich results.
 *
 *   organizer
 *     Some events have a sponsor/organizer that is searched for
 *     directly ("Bangkok Marathon 2026"). Putting it in JSON-LD as
 *     Event.organizer.name gives Google an anchor to rank against.
 *
 *   event_type
 *     A normalized tag — wedding/graduation/running/etc. — that we
 *     can pivot on for the pSEO event_archive landing pages and for
 *     the customer-side "browse by event type" filter.
 *
 *   expected_attendees
 *     Drives a "200+ ผู้ร่วมงาน" stat strip on the public page +
 *     feeds Schema.org Event.maximumAttendeeCapacity.
 *
 *   highlights, tags
 *     JSONB arrays. Highlights = 2-4 bullet points the photographer
 *     wants to surface ("ฟรีน้ำดื่ม", "มีรูปกลุ่ม"). Tags = free-form
 *     keywords for our internal search/filter — separate from the
 *     SEO keywords meta which is auto-built.
 *
 *   contact_phone, contact_email, website_url, facebook_url
 *     For events the photographer didn't organize themselves —
 *     customers asking about the event itself, not the photos. Also
 *     emitted in Schema.org Event.organizer.{telephone,email,sameAs}.
 *
 *   dress_code, parking_info
 *     Page-display only; NOT in JSON-LD. Customers ask these all the
 *     time before turning up to the venue.
 *
 * Every field is nullable so existing rows stay valid; the controller
 * + view fall back gracefully when a field is empty.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('event_events', function (Blueprint $table) {
            // ── Time window ─────────────────────────────────────────
            $table->time('start_time')->nullable()->after('shoot_date');
            $table->time('end_time')->nullable()->after('start_time');

            // ── Venue / organizer ───────────────────────────────────
            $table->string('venue_name', 200)->nullable()->after('location_detail');
            $table->string('organizer', 200)->nullable()->after('venue_name');

            // ── Categorization ──────────────────────────────────────
            // Free-text instead of an enum so photographers can use
            // anything that fits ("wedding", "graduation", "running",
            // "concert", "corporate", "prewedding", "portrait",
            // "festival", "other"). The admin form provides the
            // canonical list as a datalist for autocomplete.
            $table->string('event_type', 50)->nullable()->after('organizer');

            // ── Numbers ─────────────────────────────────────────────
            $table->integer('expected_attendees')->nullable()->after('event_type');

            // ── Marketing JSON arrays ──────────────────────────────
            // jsonb so PG can ?-test individual elements for the tag
            // filter on the customer search page later.
            $table->jsonb('highlights')->nullable()->after('expected_attendees');
            $table->jsonb('tags')->nullable()->after('highlights');

            // ── Contact ────────────────────────────────────────────
            $table->string('contact_phone', 30)->nullable()->after('tags');
            $table->string('contact_email', 150)->nullable()->after('contact_phone');

            // ── Links ──────────────────────────────────────────────
            $table->string('website_url', 500)->nullable()->after('contact_email');
            $table->string('facebook_url', 500)->nullable()->after('website_url');

            // ── On-site logistics (page display only) ──────────────
            $table->string('dress_code', 200)->nullable()->after('facebook_url');
            $table->string('parking_info', 500)->nullable()->after('dress_code');
        });

        // Index event_type for the "browse by type" filter — without
        // it the upcoming pSEO event_archive page would scan the
        // whole table on every render. Partial index on visible
        // events keeps it tiny.
        try {
            \DB::statement("
                CREATE INDEX IF NOT EXISTS event_events_event_type_visible_idx
                ON event_events (event_type)
                WHERE status IN ('active','published') AND visibility = 'public'
            ");
        } catch (\Throwable $e) {
            // SQLite (test) — skip the partial-index syntax.
        }
    }

    public function down(): void
    {
        try {
            \DB::statement("DROP INDEX IF EXISTS event_events_event_type_visible_idx");
        } catch (\Throwable) {}

        Schema::table('event_events', function (Blueprint $table) {
            $table->dropColumn([
                'start_time', 'end_time',
                'venue_name', 'organizer', 'event_type', 'expected_attendees',
                'highlights', 'tags',
                'contact_phone', 'contact_email',
                'website_url', 'facebook_url',
                'dress_code', 'parking_info',
            ]);
        });
    }
};

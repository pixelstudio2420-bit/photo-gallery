<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Booking system — round 2: recurrence + reverse-sync infrastructure.
 *
 * What this adds
 * ==============
 *
 * 1) booking_series        — owns a recurrence pattern. One row per
 *    "every Monday 9am for 12 weeks" definition. The materializer
 *    creates concrete `bookings` rows for each upcoming occurrence.
 *
 * 2) bookings.series_id    — back-reference so each child booking
 *    knows which series it came from (enables "cancel just this
 *    instance" vs "cancel the whole series").
 *
 * 3) bookings.timezone     — the TZ the customer chose at booking
 *    time. scheduled_at is still stored as UTC (Laravel's default),
 *    but display + reminder time-zone math needs to know the
 *    customer's preference. Without this, a customer in Japan
 *    booking a Bangkok shoot got "morning at 09:00" displayed as
 *    "06:00 Asia/Tokyo" — confusing and incorrect.
 *
 * 4) gcal_watch_channels   — Google Calendar push-notification
 *    channels. We call events.watch for each photographer who's
 *    enabled GCal sync; Google then POSTs to our webhook whenever
 *    the photographer edits an event in Google. The channel
 *    expires (~7 days), so we also need a renewal cron.
 */
return new class extends Migration {
    public function up(): void
    {
        // ── booking_series ─────────────────────────────────────────────
        if (!Schema::hasTable('booking_series')) {
            Schema::create('booking_series', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('customer_user_id');
                $t->unsignedInteger('photographer_id');
                $t->string('title', 200);
                $t->text('description')->nullable();
                $t->string('location', 500)->nullable();
                $t->decimal('location_lat', 10, 7)->nullable();
                $t->decimal('location_lng', 10, 7)->nullable();
                $t->unsignedInteger('duration_minutes')->default(120);
                $t->decimal('agreed_price', 10, 2)->nullable();
                $t->string('package_name', 100)->nullable();
                $t->string('customer_phone', 30)->nullable();
                $t->text('customer_notes')->nullable();
                $t->string('timezone', 64)->default('Asia/Bangkok');

                // Recurrence rule encoded as JSON (our own simpler shape
                // rather than RFC 5545 RRULE — easier to validate, covers
                // 95% of photographer needs).
                //   { "freq":"weekly", "interval":1,
                //     "by_day":["MO","WE","FR"], "time":"09:00",
                //     "starts_on":"2026-05-20", "until":"2026-12-31",
                //     "count":24, "exceptions":["2026-07-04"] }
                $t->json('recurrence');

                // Lifecycle: active (materializer keeps generating) →
                // ended (passed end date or count) | cancelled (stopped early)
                $t->string('status', 16)->default('active');

                // The most recent date the materializer has produced a
                // booking for. We don't materialise the entire series
                // upfront — only the next ~90 days' worth — so the cron
                // re-runs daily to extend the horizon.
                $t->timestamp('materialized_until')->nullable();
                $t->timestamps();

                $t->index(['photographer_id', 'status']);
                $t->index(['customer_user_id', 'status']);
                $t->index('materialized_until');
            });
        }

        // ── bookings.series_id + timezone ──────────────────────────────
        Schema::table('bookings', function (Blueprint $t) {
            if (!Schema::hasColumn('bookings', 'series_id')) {
                $t->unsignedBigInteger('series_id')->nullable()->after('event_id');
                $t->index('series_id');
            }
            if (!Schema::hasColumn('bookings', 'timezone')) {
                $t->string('timezone', 64)->nullable()->after('scheduled_at');
            }
        });

        // ── gcal_watch_channels ────────────────────────────────────────
        if (!Schema::hasTable('gcal_watch_channels')) {
            Schema::create('gcal_watch_channels', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('photographer_id');
                // Google-issued IDs.
                $t->string('channel_id', 128)->unique();   // we generate (UUID)
                $t->string('resource_id', 128);             // Google returns
                $t->string('resource_uri', 1000)->nullable();
                // Watch token — Google echoes this back on each push so we
                // can verify the request actually originated from our watch.
                $t->string('token', 128)->nullable();
                $t->timestamp('expiration_at');             // ~7 days from create
                $t->timestamp('last_renewed_at')->nullable();
                $t->string('status', 16)->default('active');// active|expired|stopped
                $t->timestamps();

                $t->index(['photographer_id', 'status']);
                $t->index('expiration_at');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('gcal_watch_channels');

        Schema::table('bookings', function (Blueprint $t) {
            if (Schema::hasColumn('bookings', 'series_id')) {
                $t->dropIndex(['series_id']);
                $t->dropColumn('series_id');
            }
            if (Schema::hasColumn('bookings', 'timezone')) {
                $t->dropColumn('timezone');
            }
        });

        Schema::dropIfExists('booking_series');
    }
};

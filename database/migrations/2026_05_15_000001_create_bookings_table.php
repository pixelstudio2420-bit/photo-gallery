<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Photographer booking / job queue.
 *
 * A `Booking` is a customer's reservation of a specific photographer's time
 * for a future shoot — distinct from `Order` (which is post-shoot photo
 * purchase) and `Event` (which is the photographer-managed gallery).
 *
 * Lifecycle:
 *   pending    customer requested → awaiting photographer confirmation
 *   confirmed  photographer accepted
 *   completed  shoot done
 *   cancelled  either side cancelled (with reason)
 *   no_show    one side didn't show up
 *
 * LINE reminders fire on 4 windows before scheduled_at:
 *   T-3 days · T-1 day · T-1 hour · T-0 (day-of)
 * Each window's `reminder_*_sent_at` is stamped to prevent duplicates.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('bookings')) return;

        Schema::create('bookings', function (Blueprint $t) {
            $t->id();

            // ── Parties ─────────────────────────────────────────────────
            $t->unsignedBigInteger('customer_user_id');
            $t->unsignedBigInteger('photographer_id'); // = auth_users.id
            $t->unsignedBigInteger('event_id')->nullable(); // optional link

            // ── What & When ────────────────────────────────────────────
            $t->string('title', 255);
            $t->text('description')->nullable();
            $t->dateTime('scheduled_at');
            $t->unsignedSmallInteger('duration_minutes')->default(120);

            // ── Where ──────────────────────────────────────────────────
            $t->string('location', 500)->nullable();
            $t->decimal('location_lat', 10, 7)->nullable();
            $t->decimal('location_lng', 10, 7)->nullable();

            // ── Commercial ─────────────────────────────────────────────
            $t->string('package_name', 100)->nullable();
            $t->unsignedSmallInteger('expected_photos')->nullable();
            $t->decimal('agreed_price', 10, 2)->nullable();
            $t->decimal('deposit_paid', 10, 2)->default(0);

            // ── Communication ──────────────────────────────────────────
            $t->string('customer_phone', 30)->nullable();
            $t->text('customer_notes')->nullable();
            $t->text('photographer_notes')->nullable();

            // ── Status flow ────────────────────────────────────────────
            $t->enum('status', ['pending', 'confirmed', 'completed', 'cancelled', 'no_show'])
              ->default('pending');
            $t->text('cancellation_reason')->nullable();
            $t->enum('cancelled_by', ['customer', 'photographer', 'admin'])->nullable();
            $t->timestamp('cancelled_at')->nullable();
            $t->timestamp('confirmed_at')->nullable();
            $t->timestamp('completed_at')->nullable();

            // ── Reminder de-duplication ────────────────────────────────
            //   3-day (T-3d), 1-day (T-1d), 1-hour (T-1h), day-of (T-0)
            $t->timestamp('reminder_3d_sent_at')->nullable();
            $t->timestamp('reminder_1d_sent_at')->nullable();
            $t->timestamp('reminder_1h_sent_at')->nullable();
            $t->timestamp('reminder_day_sent_at')->nullable();
            $t->timestamp('post_shoot_review_sent_at')->nullable();

            $t->timestamps();

            // ── Indexes ────────────────────────────────────────────────
            $t->index(['photographer_id', 'scheduled_at']);
            $t->index(['customer_user_id', 'scheduled_at']);
            $t->index(['status', 'scheduled_at']);
            $t->index('event_id');
            $t->index('scheduled_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};

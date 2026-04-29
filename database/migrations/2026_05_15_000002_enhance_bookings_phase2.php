<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2 enhancements to bookings — deposit gating, waitlist support,
 * Google Calendar sync, and admin audit columns.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('bookings')) return;

        Schema::table('bookings', function (Blueprint $t) {
            // ── Waitlist ────────────────────────────────────────────────
            // When two customers request the same time slot, we keep the
            // second as `is_waitlist=true`. If the primary booking
            // cancels, the waitlist auto-promotes (first by created_at).
            if (!Schema::hasColumn('bookings', 'is_waitlist')) {
                $t->boolean('is_waitlist')->default(false)->after('status');
            }
            if (!Schema::hasColumn('bookings', 'waitlist_for_id')) {
                $t->unsignedBigInteger('waitlist_for_id')->nullable()->after('is_waitlist');
            }
            if (!Schema::hasColumn('bookings', 'promoted_from_waitlist_at')) {
                $t->timestamp('promoted_from_waitlist_at')->nullable()->after('waitlist_for_id');
            }

            // ── Deposit gating (optional) ───────────────────────────────
            // If `deposit_required_pct > 0`, the booking can only graduate
            // from "confirmed" → "deposit_paid" → fulfilled when at least
            // that % of agreed_price has been paid.
            if (!Schema::hasColumn('bookings', 'deposit_required_pct')) {
                $t->unsignedTinyInteger('deposit_required_pct')->default(0)
                  ->after('deposit_paid');
            }
            if (!Schema::hasColumn('bookings', 'deposit_paid_at')) {
                $t->timestamp('deposit_paid_at')->nullable()->after('deposit_required_pct');
            }
            if (!Schema::hasColumn('bookings', 'deposit_payment_id')) {
                $t->string('deposit_payment_id', 64)->nullable()->after('deposit_paid_at');
            }

            // ── Google Calendar sync ────────────────────────────────────
            //  Stores the Calendar event id we created on photographer's
            //  GCal so we can update / delete it on changes.
            if (!Schema::hasColumn('bookings', 'gcal_event_id')) {
                $t->string('gcal_event_id', 128)->nullable()->after('post_shoot_review_sent_at');
            }
            if (!Schema::hasColumn('bookings', 'gcal_synced_at')) {
                $t->timestamp('gcal_synced_at')->nullable()->after('gcal_event_id');
            }

            // ── Admin audit ─────────────────────────────────────────────
            //  When admin overrides cancellation/no-show — track who.
            if (!Schema::hasColumn('bookings', 'admin_id')) {
                $t->unsignedBigInteger('admin_id')->nullable()->after('cancelled_by');
            }
            if (!Schema::hasColumn('bookings', 'admin_notes')) {
                $t->text('admin_notes')->nullable()->after('admin_id');
            }

            // Indexes for new lookups
            if (!Schema::hasColumn('bookings', 'is_waitlist')) {
                // (skip — already created above)
            }
        });

        // Add indexes outside the column-creation block (Laravel limitation
        // when re-running on partially-applied migrations).
        Schema::table('bookings', function (Blueprint $t) {
            try {
                $t->index(['is_waitlist', 'waitlist_for_id'], 'bookings_waitlist_idx');
            } catch (\Throwable) { /* index already exists */ }
            try {
                $t->index(['gcal_event_id'], 'bookings_gcal_idx');
            } catch (\Throwable) {}
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('bookings')) return;
        Schema::table('bookings', function (Blueprint $t) {
            $cols = [
                'is_waitlist', 'waitlist_for_id', 'promoted_from_waitlist_at',
                'deposit_required_pct', 'deposit_paid_at', 'deposit_payment_id',
                'gcal_event_id', 'gcal_synced_at', 'admin_id', 'admin_notes',
            ];
            foreach ($cols as $c) {
                if (Schema::hasColumn('bookings', $c)) $t->dropColumn($c);
            }
        });
    }
};

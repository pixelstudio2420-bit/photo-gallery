<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit log for every change touching a pricing_packages row.
 *
 * Why this matters: bundle prices drive real money movements (buyers pay
 * the listed price; commission flows back to the photographer). Without
 * an audit trail there's no way to investigate disputes — "the price was
 * ฿879 when I added to cart but ฿4,990 on the receipt" — and no way to
 * detect anti-fraud patterns like:
 *
 *   • A photographer flipping a bundle to ฿1 to gift photos to a friend
 *     then flipping it back the next minute.
 *   • Someone abusing the recalc endpoint to pump bundles up after the
 *     cart-add step (defeated by Order/OrderItem snapshots, but worth
 *     having proof).
 *   • Bulk price-tampering across many events in a short window.
 *
 * Schema covers create / update / delete via the `action` column. For
 * updates we store both old + new full row state as JSON — slightly
 * more storage, dramatically easier forensic queries ("show me every
 * change Pat made to bundle 42 last week"). Timestamp-only index on
 * the foreign keys means listing recent changes for one event/package
 * stays fast even at millions of rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pricing_package_logs', function (Blueprint $table) {
            $table->id();

            // Foreign keys — both nullable so a delete log row survives
            // the deletion of the underlying package, and so admin-side
            // audit views can render even when the parent rows have
            // since been hard-deleted.
            $table->unsignedBigInteger('package_id')->nullable();
            $table->unsignedBigInteger('event_id')->nullable();

            // What happened. Constrained set so query patterns stay
            // predictable and indexable.
            //   create  — row inserted (seed, manual add, template apply)
            //   update  — row mutated (price, discount_pct, etc.)
            //   delete  — row removed
            //   recalc  — system-driven price refresh (auto-recalc on
            //             per_photo change, "recalculate prices" button)
            //   feature — is_featured toggled on/off
            $table->string('action', 30);

            // Full-state snapshot. Skipped for create (no old) and
            // delete (no new). Allows detail diffs without an extra join.
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();

            // Who. Photographer's user_id, admin's user_id, or NULL when
            // the change came from a system observer (auto-recalc, seed).
            $table->unsignedBigInteger('changed_by')->nullable();
            $table->string('changed_by_role', 30)->nullable();
                // ^ 'photographer' | 'admin' | 'system'

            // Free-text justification — set by the controller method that
            // initiated the change. e.g. "applyTemplate to standard",
            // "auto-recalc on per_photo change ฿100→฿200".
            $table->string('reason', 255)->nullable();

            // Forensic — useful when the same actor abuses across multiple
            // photographer accounts. Kept short (max IPv6 = 39 chars).
            $table->string('ip_address', 64)->nullable();

            // We only care about created_at — the row is immutable after
            // insert (it IS the audit, no updates to itself).
            $table->timestamp('created_at')->useCurrent();

            // Indexes optimised for the two most common queries:
            //   1. "show recent changes for package X"
            //   2. "show recent changes for event Y"
            //   3. "show recent changes by user Z"
            $table->index(['package_id', 'created_at'], 'pkg_log_pkg_idx');
            $table->index(['event_id', 'created_at'],   'pkg_log_event_idx');
            $table->index(['changed_by', 'created_at'], 'pkg_log_user_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pricing_package_logs');
    }
};

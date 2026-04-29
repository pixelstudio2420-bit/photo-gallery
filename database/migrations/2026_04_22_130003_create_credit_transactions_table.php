<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Immutable ledger of every credit movement.
 *
 * Purpose: audit trail. We reconcile photographer_profiles.credits_balance_cached
 * against SUM(credit_transactions.delta WHERE photographer_id=X) to detect drift,
 * and surface the timeline on both admin and photographer history views.
 *
 * `reference_type` + `reference_id` form a polymorphic pointer to the thing
 * that caused the movement (e.g. an event_photos.id for consumes, an orders.id
 * for purchases, a users.id for admin adjustments).
 *
 * Never UPDATE or DELETE — only INSERT. If a mistake was made, append a
 * compensating 'adjust' row.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('credit_transactions')) {
            return;
        }

        Schema::create('credit_transactions', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('photographer_id');
            $t->unsignedBigInteger('bundle_id')->nullable();
            $t->string('kind', 32);  // purchase|consume|refund|grant|expire|adjust|bonus
            $t->integer('delta');    // +N for credit grants, -N for consumes (signed int)
            $t->unsignedInteger('balance_after'); // snapshot right after this txn
            $t->string('reference_type', 64)->nullable(); // event_photo|order|user|gift
            $t->string('reference_id', 64)->nullable();
            $t->json('meta')->nullable();  // arbitrary metadata (bank slip, note, etc)
            $t->unsignedBigInteger('actor_user_id')->nullable(); // admin user, null = system
            $t->timestamp('created_at')->nullable();

            $t->index(['photographer_id', 'created_at'], 'idx_ct_photog_time');
            $t->index(['reference_type', 'reference_id'], 'idx_ct_reference');
            $t->index('kind');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_transactions');
    }
};

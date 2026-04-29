<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A consumer user's active (or historical) cloud-storage subscription.
 *
 * Mirrors `photographer_subscriptions` but scoped to the generic `auth_users`
 * table — any signed-up account can have one storage subscription at a time.
 *
 * Status lifecycle:
 *   pending   — created, awaiting first payment
 *   active    — paid & within current_period_end
 *   grace     — renewal failed, within grace window (default 7d) before downgrade
 *   cancelled — user cancelled; may still be active until period_end if
 *               cancel_at_period_end is true
 *   expired   — grace ran out; user auto-downgraded to Free
 *
 * Renewal: the same `subscriptions:charge-renewals` cron (or a dedicated
 * `user-storage:charge-renewals` if we split later) finds subs due in the
 * next 24h and creates an Order with order_type='user_storage_subscription'.
 * OrderFulfillmentService routes the paid order to UserStorageService.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('user_storage_subscriptions')) {
            return;
        }

        Schema::create('user_storage_subscriptions', function (Blueprint $t) {
            $t->id();

            // Owner — references auth_users.id (the generic user row)
            $t->unsignedBigInteger('user_id')->index();
            $t->unsignedBigInteger('plan_id')->index();

            // Lifecycle
            $t->string('status', 16)->default('pending')->index();
            // pending | active | grace | cancelled | expired
            $t->timestamp('started_at')->nullable();
            $t->timestamp('current_period_start')->nullable();
            $t->timestamp('current_period_end')->nullable()->index();
            $t->boolean('cancel_at_period_end')->default(false);
            $t->timestamp('cancelled_at')->nullable();
            $t->timestamp('grace_ends_at')->nullable();
            $t->timestamp('last_renewed_at')->nullable();

            // Renewal controls
            $t->unsignedInteger('renewal_attempts')->default(0);
            $t->timestamp('next_retry_at')->nullable();
            $t->string('last_failure_reason', 255)->nullable();

            // Payment gateway references (same pattern as photographer_subscriptions)
            $t->string('payment_method_type', 40)->nullable();
            $t->string('omise_customer_id', 100)->nullable();
            $t->string('omise_schedule_id', 100)->nullable();

            // Free-form metadata
            $t->json('meta')->nullable();

            $t->timestamps();

            $t->foreign('plan_id')->references('id')->on('storage_plans')->restrictOnDelete();
            $t->index(['user_id', 'status']);
            $t->index(['status', 'current_period_end']);
            $t->index(['status', 'grace_ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_storage_subscriptions');
    }
};

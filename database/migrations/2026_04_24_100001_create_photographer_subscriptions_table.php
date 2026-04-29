<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A photographer's active (or historical) subscription row.
 *
 * One photographer can have one `active|grace|pending` subscription at a time
 * — prior cycles become `cancelled` or `expired` for audit.
 *
 * `status` lifecycle:
 *   pending   — just created, awaiting first payment
 *   active    — currently paid & within current period
 *   grace     — renewal failed, within grace window (7d) before downgrade
 *   cancelled — user cancelled (may still be active until period_end if cancel_at_period_end)
 *   expired   — grace ran out; quota already downgraded
 *
 * Renewal strategy: a nightly cron (`subscriptions:charge-renewals`) finds
 * active subs due in the next 24 hours and creates a fresh payment order.
 * Works with any gateway (Omise, Stripe, PromptPay) — no gateway-specific
 * recurring API required. If/when we wire Omise Schedules natively the
 * `omise_schedule_id` column is ready.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('photographer_subscriptions')) {
            return;
        }

        Schema::create('photographer_subscriptions', function (Blueprint $t) {
            $t->id();

            // Photographer user id (matches the convention used across the app
            // where photographer_id = users.id rather than photographer_profiles.id)
            $t->unsignedBigInteger('photographer_id')->index();
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

            // Payment gateway references
            $t->string('payment_method_type', 40)->nullable();  // 'omise' | 'promptpay' | 'bank_transfer'
            $t->string('omise_customer_id', 100)->nullable();   // reusable token
            $t->string('omise_schedule_id', 100)->nullable();   // if using Omise /schedules

            // Free-form metadata
            $t->json('meta')->nullable();

            $t->timestamps();

            $t->foreign('plan_id')->references('id')->on('subscription_plans')->restrictOnDelete();
            $t->index(['photographer_id', 'status']);
            $t->index(['status', 'current_period_end']);
            $t->index(['status', 'grace_ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('photographer_subscriptions');
    }
};

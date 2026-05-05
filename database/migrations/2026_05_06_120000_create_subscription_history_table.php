<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `subscription_history` — immutable audit trail of every subscription
 * state transition.
 *
 * Why this matters:
 *   • Customer support — "I paid twice but my plan is free, why?" → one
 *     query lists every event in chronological order.
 *   • Compliance — Thai PDPA §39 + general SaaS audit obligations require
 *     a tamper-evident log of billing-relevant changes.
 *   • Analytics — churn / upgrade rate / time-to-upgrade come straight
 *     out of this table without joining payouts + invoices.
 *   • Debugging — when the cron + webhook race somehow leaves a
 *     subscription in a weird state, this table tells you exactly which
 *     piece fired and in what order.
 *
 * Append-only by convention: writers use create(), nothing should
 * update or delete rows. The cascade on subscription_id is intentional
 * (when an admin hard-deletes a sub for cleanup, the audit is gone too —
 * if compliance requires preservation, switch to nullOnDelete and keep
 * an orphan trail).
 *
 * Event types we record:
 *    created     — first sub for a photographer
 *    activated   — pending → active (after paid invoice)
 *    renewed     — period rolled forward (same plan)
 *    upgraded    — plan_id changed to a higher-tier plan
 *    downgraded  — plan_id changed to a lower-tier plan
 *    cancelled   — cancel_at_period_end set OR force-cancelled
 *    reactivated — cancel_at_period_end cleared while still active
 *    grace_entered — payment failed, sub flipped to grace
 *    expired     — period_end passed without payment
 *    refunded    — admin refunded an invoice → sub downgraded to free
 *    plan_change_scheduled — pending_plan_code set (deferred downgrade)
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('subscription_history', function (Blueprint $t) {
            $t->id();
            $t->foreignId('subscription_id')
                ->constrained('photographer_subscriptions')
                ->cascadeOnDelete();
            $t->foreignId('photographer_id')
                ->constrained('auth_users')
                ->cascadeOnDelete();
            $t->string('event_type', 32)
                ->comment('created|activated|renewed|upgraded|downgraded|cancelled|reactivated|grace_entered|expired|refunded|plan_change_scheduled');
            $t->foreignId('from_plan_id')
                ->nullable()
                ->constrained('subscription_plans')
                ->nullOnDelete();
            $t->foreignId('to_plan_id')
                ->nullable()
                ->constrained('subscription_plans')
                ->nullOnDelete();
            $t->decimal('amount_thb', 12, 2)
                ->nullable()
                ->comment('Amount charged or refunded in this transition');
            $t->string('triggered_by', 20)
                ->default('system')
                ->comment('user|admin|cron|webhook|system');
            $t->foreignId('triggered_by_id')
                ->nullable()
                ->constrained('auth_users')
                ->nullOnDelete()
                ->comment('admin user_id or null for cron/webhook');
            $t->jsonb('metadata')
                ->nullable()
                ->comment('Free-form context: invoice_id, days_remaining, reason, etc.');
            $t->timestamp('created_at')->useCurrent();

            $t->index(['subscription_id', 'created_at'], 'sh_sub_chrono_idx');
            $t->index(['photographer_id', 'event_type'], 'sh_pg_event_idx');
            $t->index('event_type', 'sh_event_idx');
            $t->index('created_at', 'sh_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_history');
    }
};

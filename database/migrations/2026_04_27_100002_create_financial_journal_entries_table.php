<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per atomic financial transaction. Holds metadata + idempotency
 * key + a foreign-key target that journal_lines pivot off.
 *
 * Idempotency
 * -----------
 * `idempotency_key` is the caller-controlled fingerprint of "this
 * action, exactly once". Examples:
 *   - 'order.{id}.paid'                  — when an order's payment lands
 *   - 'payout.{id}.created'              — when a photographer payout is split out
 *   - 'disbursement.{id}.{provider_txn}' — when a payout batch settles at the gateway
 *   - 'refund.{id}.approved'             — when a refund is approved
 *
 * The UNIQUE constraint stops a webhook retry from posting twice. The
 * service code throws JournalEntryAlreadyPostedException on conflict
 * so callers can read the existing entry rather than treating it as an
 * error.
 *
 * Reversals
 * ---------
 * NEVER edit a posted entry. Reversals create a NEW entry with
 * `reversed_by_id` pointing at the new one, and flipped DR/CR lines.
 * This preserves a complete audit trail.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('financial_journal_entries', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->uuid('journal_uuid')->unique();
            // 'order.paid', 'payout.disbursed', 'subscription.charged',
            // 'refund.approved', 'tax.accrued', 'cost.recorded'
            $t->string('type', 48);
            $t->text('description')->nullable();
            // Customer-supplied idempotency key — protects against
            // double-posting under webhook retry.
            $t->string('idempotency_key', 128)->unique();
            $t->json('metadata')->nullable();
            $t->timestampTz('posted_at');
            // 'system' / 'webhook:omise' / 'admin:42' / 'api:user_99'
            $t->string('posted_by', 64)->nullable();
            // Reversal pointer — set when this entry was reversed
            $t->unsignedBigInteger('reversed_by_id')->nullable();
            $t->foreign('reversed_by_id')->references('id')->on('financial_journal_entries')->nullOnDelete();
            $t->timestampsTz();

            $t->index(['type', 'posted_at']);
            $t->index('posted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_journal_entries');
    }
};

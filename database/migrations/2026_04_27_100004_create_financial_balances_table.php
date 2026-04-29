<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Denormalised account balances — kept in sync by LedgerService::post()
 * inside the same transaction as the journal lines.
 *
 * Why denormalise?
 *   The hot path (an admin dashboard or a "show me my pending payout"
 *   API) needs the current balance fast. Computing
 *     SUM(DR) - SUM(CR) on journal_lines for an account
 *   on every read scales O(transactions) — fine at 1k journals,
 *   painful at 1M. The denormalised row is a primary-key lookup.
 *
 * Why is this safe to denormalise?
 *   - Updated atomically with the journal posting (one DB transaction).
 *   - ReconciliationCommand recomputes from the ledger nightly and
 *     SCREAMS if balance drift > 0 satang.
 *   - The ledger (journal_lines) is the source of truth — this table
 *     is a cache. If it's wrong it can be rebuilt from scratch.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('financial_balances', function (Blueprint $t) {
            $t->unsignedBigInteger('account_id')->primary();
            $t->foreign('account_id')->references('id')->on('financial_accounts')->cascadeOnDelete();
            // Signed: revenue/liability accounts naturally have a credit
            // balance which we represent as a positive number under the
            // "normal balance" convention; LedgerService normalises the
            // sign so callers don't have to think about it.
            $t->bigInteger('balance_minor')->default(0);
            $t->char('currency', 3);
            $t->timestampTz('updated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_balances');
    }
};

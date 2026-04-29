<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The actual debit/credit pairs. The hard invariant:
 *
 *   SUM(amount_minor WHERE direction='DR') = SUM(amount_minor WHERE direction='CR')
 *
 * …per journal_entry_id. The LedgerService::post() method enforces this
 * BEFORE inserting; the ReconciliationCommand verifies it nightly.
 *
 * Why integer minor units (BIGINT)?
 * --------------------------------
 *   - decimal(10,2) leaks into PHP as a float during arithmetic.
 *     `0.1 + 0.2 != 0.3`. This is the #1 bug class in fintech systems.
 *   - Integer satang (1 THB = 100 satang) keeps every arithmetic op
 *     deterministic on the JVM/PHP/Postgres side.
 *   - BIGINT supports up to 9.2 × 10^18 minor units. At satang precision
 *     that's THB 92 quadrillion — comfortably more than Thailand's GDP.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('financial_journal_lines', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('journal_entry_id');
            $t->foreign('journal_entry_id')->references('id')->on('financial_journal_entries')->cascadeOnDelete();
            $t->unsignedBigInteger('account_id');
            $t->foreign('account_id')->references('id')->on('financial_accounts')->restrictOnDelete();
            // 'DR' = debit, 'CR' = credit. Two characters keeps the column
            // tiny and matches accounting convention.
            $t->char('direction', 2);
            // Always positive. The direction column tells us the sign.
            $t->unsignedBigInteger('amount_minor');
            $t->char('currency', 3);
            $t->timestampTz('created_at');

            $t->index(['account_id', 'created_at']);
            $t->index('journal_entry_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_journal_lines');
    }
};

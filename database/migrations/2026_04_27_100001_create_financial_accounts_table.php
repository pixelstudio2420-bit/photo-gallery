<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chart of Accounts — every "bucket" money can sit in.
 *
 * Account types follow GAAP:
 *   asset      — what the platform owns (cash, gateway receivables)
 *   liability  — what the platform owes (photographer payouts pending,
 *                customer prepaid balances, taxes collected for govt)
 *   equity     — owner's stake (retained earnings)
 *   revenue    — money the platform earned (commission, subscription fees)
 *   expense    — money the platform spent (AI cost, R2 storage, refunds)
 *
 * Account codes follow a hierarchical scheme:
 *   1xxx  asset   (1000 cash, 1100 stripe receivable, 1200 omise receivable…)
 *   2xxx  liability (2000 photographer payable, 2100 customer credit,
 *                  2200 vat collected, 2300 refunds payable)
 *   3xxx  equity  (3000 retained earnings)
 *   4xxx  revenue (4000 commission, 4100 subscriptions, 4200 storage subs)
 *   5xxx  expense (5000 ai cost, 5100 r2 storage, 5200 bandwidth,
 *                  5300 refund expense)
 *
 * Per-user accounts (photographer_42_payable, customer_99_credit) use a
 * suffix scheme like 'P-42-PAYABLE' / 'C-99-CREDIT' so 1 row per
 * (user × account-type) pair. They are LIABILITY accounts on the
 * platform's books (we owe the photographer their share until disbursed).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('financial_accounts', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('account_code', 64)->unique();
            $t->string('account_type', 16);  // asset|liability|equity|revenue|expense
            $t->string('name', 128);
            $t->char('currency', 3)->default('THB');
            // owner_type/id link a per-user wallet to its principal:
            //   ('photographer', 42), ('customer', 99), ('platform', null)
            $t->string('owner_type', 32)->nullable();
            $t->unsignedBigInteger('owner_id')->nullable();
            $t->boolean('is_active')->default(true);
            $t->json('metadata')->nullable();
            $t->timestampsTz();

            $t->index(['account_type', 'currency']);
            $t->index(['owner_type', 'owner_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_accounts');
    }
};

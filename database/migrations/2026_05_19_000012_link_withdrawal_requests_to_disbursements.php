<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Link `withdrawal_requests` ↔ `photographer_disbursements`.
 *
 * Why this is needed
 * ──────────────────
 * The two systems coexist:
 *   • photographer_disbursements → per-transfer ledger (auto-payout cron)
 *   • withdrawal_requests        → photographer-initiated request UI
 *
 * The migration that created `withdrawal_requests` documented an intent
 * that the controller never fulfilled: "The photographer_payouts attached
 * are flipped to paid by the controller logic, mirroring the disbursement
 * path." In practice `Admin\WithdrawalController::markPaid()` was only
 * flipping the WithdrawalRequest row's status to 'paid' WITHOUT touching
 * the underlying PhotographerPayout rows — so after admin marked a
 * request paid, the same earnings stayed at status='pending' and the
 * photographer could request the same money again (`available_balance`
 * computed `unpaid_payouts − active_requests` and would re-include the
 * just-paid amount once the WithdrawalRequest scope filtered it out).
 *
 * The fix is to actually create a PhotographerDisbursement on markPaid
 * (mirroring auto-payout), attach the FIFO-oldest pending payouts to it,
 * and call markSucceeded() to flip them to 'paid' atomically. This
 * column gives us the audit trail back-link from the request to the
 * disbursement that settled it. Nullable for legacy rows that landed
 * before this fix shipped.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('withdrawal_requests', function (Blueprint $t) {
            $t->foreignId('disbursement_id')
                ->nullable()
                ->after('payment_reference')
                ->constrained('photographer_disbursements')
                ->nullOnDelete()
                ->comment('Created on mark-paid — links the manual request to the unified per-transfer ledger');
            $t->index('disbursement_id', 'wr_disbursement_idx');
        });
    }

    public function down(): void
    {
        Schema::table('withdrawal_requests', function (Blueprint $t) {
            $t->dropForeign(['disbursement_id']);
            $t->dropIndex('wr_disbursement_idx');
            $t->dropColumn('disbursement_id');
        });
    }
};

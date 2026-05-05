<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `withdrawal_requests` — photographer-initiated withdrawal flow.
 *
 * Distinct from `photographer_disbursements` (the existing auto-cron
 * payout bundles): this table stores REQUESTS the photographer
 * actively raised. The two systems coexist:
 *   • photographer_disbursements — admin runs payout cron, system
 *                                   bundles eligible photographer_payouts
 *                                   into a transfer batch automatically.
 *   • withdrawal_requests        — photographer hits "แจ้งถอน",
 *                                   admin reviews + manually marks paid.
 *
 * Status FSM:
 *   pending  → admin queue. Default state on create.
 *   approved → admin acknowledged + intends to pay. Optional intermediate
 *              step admins can use to commit before actual transfer.
 *   paid     → terminal: admin transferred + uploaded slip. The
 *              photographer_payouts attached are flipped to paid by
 *              the controller logic, mirroring the disbursement path.
 *   rejected → terminal: admin refused (e.g. wrong bank info, fraud).
 *              `rejection_reason` is required when transitioning here.
 *   cancelled → terminal: photographer cancelled before approval.
 *
 * Each request snapshots the bank/promptpay details at request time —
 * even if the photographer later edits their profile bank fields, the
 * historical request remembers what was paid where (audit trail).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('withdrawal_requests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('photographer_id')
                ->constrained('auth_users')
                ->cascadeOnDelete()
                ->comment('photographer (auth_users.id) who requested');
            $t->decimal('amount_thb', 12, 2)
                ->comment('Gross requested amount (before fee)');
            $t->decimal('fee_thb', 10, 2)
                ->default(0)
                ->comment('Platform fee deducted at payout');
            $t->decimal('net_thb', 12, 2)
                ->comment('amount_thb − fee_thb. Cached for fast list views.');
            $t->string('method', 32)
                ->comment('bank_transfer | promptpay | other');
            $t->jsonb('method_details')
                ->nullable()
                ->comment('Snapshotted bank/promptpay info — bank_name, account_name, account_number, promptpay_id, etc.');
            $t->string('status', 20)
                ->default('pending')
                ->comment('pending | approved | paid | rejected | cancelled');
            $t->text('photographer_note')
                ->nullable()
                ->comment('Optional note the photographer left (e.g. "for invoice ABC")');
            $t->text('admin_note')
                ->nullable()
                ->comment('Admin note for internal reference');
            $t->text('rejection_reason')
                ->nullable()
                ->comment('Required on status=rejected');
            $t->string('payment_slip_url', 500)
                ->nullable()
                ->comment('R2 / S3 URL for the transfer slip uploaded on mark-paid');
            $t->string('payment_reference', 100)
                ->nullable()
                ->comment('Bank txn ID / external reference');
            $t->foreignId('reviewed_by_admin_id')
                ->nullable()
                ->constrained('auth_users')
                ->nullOnDelete()
                ->comment('Which admin acted on this request');
            $t->timestamp('reviewed_at')->nullable();
            $t->timestamp('paid_at')->nullable();
            $t->timestamps();

            $t->index(['photographer_id', 'status'], 'wr_photographer_status_idx');
            $t->index(['status', 'created_at'], 'wr_status_created_idx');
        });

        // Seed default admin settings if not already present. These drive
        // the photographer-side "can I request now?" gate AND the public
        // marketing copy ("ยอดขั้นต่ำ ฿500, รอ 1-3 วัน, ฟรีค่าธรรมเนียม"
        // type lines on the earnings page).
        $defaults = [
            'withdrawal_request_enabled'  => '1',          // master switch
            'withdrawal_min_amount'       => '500',        // THB
            'withdrawal_max_amount'       => '500000',     // THB sanity ceiling
            'withdrawal_fee_thb'          => '0',          // flat fee in THB
            'withdrawal_methods_enabled'  => '["bank_transfer","promptpay"]',
            'withdrawal_processing_days'  => '3',          // marketing text
            'withdrawal_max_pending_per_photographer' => '1', // anti-spam
        ];
        foreach ($defaults as $key => $value) {
            $exists = DB::table('app_settings')->where('key', $key)->exists();
            if (!$exists) {
                DB::table('app_settings')->insert([
                    'key'        => $key,
                    'value'      => $value,
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('withdrawal_requests');
        DB::table('app_settings')->whereIn('key', [
            'withdrawal_request_enabled',
            'withdrawal_min_amount',
            'withdrawal_max_amount',
            'withdrawal_fee_thb',
            'withdrawal_methods_enabled',
            'withdrawal_processing_days',
            'withdrawal_max_pending_per_photographer',
        ])->delete();
    }
};

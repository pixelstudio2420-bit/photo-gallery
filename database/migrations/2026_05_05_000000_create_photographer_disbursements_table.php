<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Disbursement ledger — records each provider transfer we fire, regardless
 * of how many individual order-level payout rows roll up into it.
 *
 * Why a separate table from `photographer_payouts`:
 *   - `photographer_payouts` is one row PER ORDER (captured at checkout
 *     time via PaymentController::createPhotographerPayout). That's the
 *     earnings ledger — immutable, per-order split.
 *   - This table is one row PER PROVIDER TRANSFER. The trigger engine
 *     batches N pending payouts into a single PromptPay call, because
 *     per-order transfers would cost per-transfer fees and flood the
 *     photographer's statement with one line per photo sold.
 *   - When the provider call succeeds we flip all N payouts from
 *     status='pending' → 'paid' and stamp disbursement_id on each.
 *
 * States:
 *   pending    → row inserted, provider call not yet attempted.
 *   processing → provider call in flight (worker claimed the row).
 *   succeeded  → provider returned success.
 *   failed     → provider returned a permanent error; admin review needed.
 *
 * Idempotency:
 *   `idempotency_key` is unique — the CheckPayoutTriggersJob computes it as
 *   hash(photographer_id, batch_window_start) so two racing schedulers don't
 *   both disburse the same batch.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('photographer_disbursements', function (Blueprint $table) {
            $table->id();

            // The photographer being paid. FK deliberately omitted — we keep
            // history even if a photographer row is later deleted, and a
            // nullable FK would invite the "orphaned disbursement" state.
            $table->unsignedBigInteger('photographer_id')->index();

            // Totals at the moment of disbursement. Stored denormalised so a
            // later re-read doesn't drift if individual payout rows are
            // adjusted.
            $table->decimal('amount_thb', 12, 2);            // net paid to photographer
            $table->unsignedInteger('payout_count')->default(0); // orders rolled up

            // Provider routing + tracking.
            $table->string('provider', 30)->index();         // mock | omise | kbank…
            $table->string('idempotency_key', 80)->unique();
            $table->string('provider_txn_id', 120)->nullable()->index();

            // Status + audit trail. `status_reason` captures provider error
            // codes (e.g. "invalid_recipient") so admins can sort failures
            // by root cause without parsing free-text.
            $table->string('status', 20)->default('pending')->index();
            $table->string('status_reason', 80)->nullable();
            $table->text('error_message')->nullable();
            $table->json('raw_response')->nullable();        // provider payload for debugging

            // Trigger context — useful for analytics ("were we triggered by
            // schedule or threshold?") and for replaying a specific batch.
            $table->string('trigger_type', 20)->default('schedule'); // schedule | threshold | manual
            $table->timestamp('window_start_at')->nullable();
            $table->timestamp('window_end_at')->nullable();

            // Lifecycle timestamps.
            $table->timestamp('attempted_at')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->unsignedInteger('attempts')->default(0);

            $table->timestamps();

            $table->index(['photographer_id', 'status']);
            $table->index(['status', 'created_at']);
        });

        // Link individual payout rows back to the disbursement that paid them
        // out. Nullable because not every payout has been disbursed yet, and
        // legacy rows pre-date this system entirely.
        Schema::table('photographer_payouts', function (Blueprint $table) {
            if (!Schema::hasColumn('photographer_payouts', 'disbursement_id')) {
                $table->unsignedBigInteger('disbursement_id')->nullable()->after('status')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('photographer_payouts', function (Blueprint $table) {
            if (Schema::hasColumn('photographer_payouts', 'disbursement_id')) {
                try { $table->dropIndex(['disbursement_id']); } catch (\Throwable) {}
                $table->dropColumn('disbursement_id');
            }
        });

        Schema::dropIfExists('photographer_disbursements');
    }
};

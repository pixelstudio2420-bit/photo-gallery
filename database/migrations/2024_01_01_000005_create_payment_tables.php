<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->increments('id');
            $table->string('method_name', 100);
            // Full gateway list matches PaymentService::createGateway() —
            // see PaymentMethodSeeder for the canonical row set. The
            // 2026_05_09 migration rewrites this CHECK constraint on
            // existing production DBs that ran on the original 4-value
            // enum (mysql/pgsql); SQLite test DBs can't ALTER CHECK and
            // were silently rejecting 'omise' inserts during seeding,
            // which crashed 649 feature tests with SQLSTATE[23000]
            // method_type CHECK violations. Defining the full set HERE
            // means fresh installs (test + prod alike) get the right
            // constraint from migration #1, and the 2026_05_09 extension
            // becomes a safe no-op for new installs while still healing
            // pre-existing legacy databases.
            $table->enum('method_type', [
                'promptpay', 'bank_transfer', 'stripe', 'omise',
                'paypal', 'line_pay', 'truemoney', 'two_c_two_p', 'manual',
            ]);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
        });

        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->increments('id');
            $table->string('transaction_id', 60)->unique();
            $table->unsignedInteger('order_id')->nullable()->index();
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('payment_method_id')->nullable();
            $table->string('payment_gateway', 50)->nullable();
            $table->string('gateway_transaction_id', 200)->nullable();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('THB');
            $table->enum('status', ['pending','processing','completed','failed','refunded'])->default('pending')->index();
            $table->dateTime('paid_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_slips', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('order_id')->index();
            $table->unsignedInteger('transaction_id')->nullable();
            $table->string('slip_path', 500)->nullable();
            $table->decimal('amount', 10, 2)->default(0.00);
            $table->dateTime('transfer_date')->nullable();
            $table->string('reference_code', 100)->nullable()->index();
            $table->enum('verify_status', ['pending','approved','rejected'])->default('pending')->index();
            $table->unsignedInteger('verified_by')->nullable();
            $table->dateTime('verified_at')->nullable();
            $table->text('reject_reason')->nullable();
            $table->text('note')->nullable();
            $table->string('slip_hash', 64)->nullable()->index();
            $table->unsignedTinyInteger('verify_score')->nullable();
            $table->dateTime('uploaded_at')->useCurrent();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('payment_logs', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('transaction_id')->nullable();
            $table->string('log_type', 50)->index();
            $table->text('message');
            $table->json('response_data')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }
    public function down(): void {
        Schema::dropIfExists('payment_logs');
        Schema::dropIfExists('payment_slips');
        Schema::dropIfExists('payment_transactions');
        Schema::dropIfExists('payment_methods');
    }
};

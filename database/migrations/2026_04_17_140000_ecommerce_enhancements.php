<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // ═════════════════════════════════════════════════════════════════
        //  Abandoned Carts Tracking
        // ═════════════════════════════════════════════════════════════════
        Schema::create('abandoned_carts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('email', 255)->nullable(); // For guest tracking
            $table->string('session_id', 100)->nullable();
            $table->json('items'); // Cart items snapshot
            $table->unsignedInteger('item_count')->default(0);
            $table->decimal('estimated_total', 10, 2)->default(0);
            $table->timestamp('last_activity_at');
            $table->enum('recovery_status', ['pending', 'reminded_1', 'reminded_2', 'recovered', 'expired'])->default('pending');
            $table->timestamp('first_reminder_at')->nullable();
            $table->timestamp('second_reminder_at')->nullable();
            $table->timestamp('recovered_at')->nullable();
            $table->unsignedBigInteger('recovered_order_id')->nullable();
            $table->string('recovery_token', 64)->nullable()->unique();
            $table->timestamps();

            $table->index(['recovery_status', 'last_activity_at']);
            $table->index('user_id');
            $table->index('email');
            $table->index('session_id');
        });

        // ═════════════════════════════════════════════════════════════════
        //  Order Status History (Timeline)
        // ═════════════════════════════════════════════════════════════════
        Schema::create('order_status_history', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('order_id');
            $table->string('status', 50);
            $table->string('description', 500)->nullable();
            $table->unsignedBigInteger('changed_by_admin_id')->nullable();
            $table->unsignedBigInteger('changed_by_user_id')->nullable();
            $table->string('actor_name', 255)->nullable();
            $table->string('source', 50)->default('system'); // 'user', 'admin', 'system', 'gateway'
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['order_id', 'created_at']);
        });

        // ═════════════════════════════════════════════════════════════════
        //  Saved Payment Methods
        // ═════════════════════════════════════════════════════════════════
        Schema::create('saved_payment_methods', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->string('provider', 50); // stripe, omise, paypal
            $table->string('provider_customer_id', 100)->nullable(); // Customer ID at gateway
            $table->string('provider_method_id', 100); // Token/card ID
            $table->string('method_type', 30); // card, bank_account, wallet
            $table->string('display_name', 100); // e.g. "Visa ending 4242"
            $table->string('last4', 4)->nullable();
            $table->string('brand', 30)->nullable(); // visa, mastercard, jcb
            $table->string('exp_month', 2)->nullable();
            $table->string('exp_year', 4)->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['user_id', 'is_active']);
        });

        // ═════════════════════════════════════════════════════════════════
        //  Refund Requests (customer-initiated)
        // ═════════════════════════════════════════════════════════════════
        Schema::create('refund_requests', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('request_number', 20)->unique();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('user_id');
            $table->decimal('requested_amount', 10, 2);
            $table->enum('reason', [
                'wrong_order', 'duplicate_charge', 'poor_quality',
                'not_as_described', 'never_received', 'other',
            ]);
            $table->text('description');
            $table->json('attachments')->nullable();
            $table->enum('status', [
                'pending', 'under_review', 'approved', 'rejected',
                'processing', 'completed', 'cancelled',
            ])->default('pending');
            $table->text('admin_note')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->decimal('approved_amount', 10, 2)->nullable();
            $table->unsignedBigInteger('reviewed_by_admin_id')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->unsignedBigInteger('payment_refund_id')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('user_id');
            $table->index('order_id');
        });

        // Add tracking token to orders for guest tracking
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'tracking_token')) {
                $table->string('tracking_token', 40)->nullable()->after('order_number')->index();
            }
            if (!Schema::hasColumn('orders', 'guest_email')) {
                $table->string('guest_email', 255)->nullable()->after('user_id');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refund_requests');
        Schema::dropIfExists('saved_payment_methods');
        Schema::dropIfExists('order_status_history');
        Schema::dropIfExists('abandoned_carts');

        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'tracking_token')) $table->dropColumn('tracking_token');
            if (Schema::hasColumn('orders', 'guest_email'))    $table->dropColumn('guest_email');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('gift_cards', function (Blueprint $t) {
            $t->id();
            $t->string('code', 32)->unique();                // human-readable, uppercase
            $t->decimal('initial_amount', 10, 2);
            $t->decimal('balance', 10, 2);                   // drops as redeemed
            $t->string('currency', 3)->default('THB');

            // Who bought it (may be null for admin-issued gift cards)
            $t->unsignedInteger('purchaser_user_id')->nullable();
            $t->string('purchaser_email')->nullable();
            $t->string('purchaser_name')->nullable();

            // Intended recipient
            $t->string('recipient_name')->nullable();
            $t->string('recipient_email')->nullable();
            $t->text('personal_message')->nullable();

            // Redemption owner (first user who redeemed — locks ownership)
            $t->unsignedInteger('redeemed_by_user_id')->nullable();

            // Source
            $t->string('source', 24)->default('admin');      // admin | purchase | promo | refund
            $t->unsignedBigInteger('source_order_id')->nullable();

            // Lifecycle
            $t->string('status', 16)->default('active');     // active | redeemed | expired | voided
            $t->timestamp('expires_at')->nullable();
            $t->timestamp('activated_at')->nullable();

            // Admin bookkeeping
            $t->unsignedInteger('issued_by_admin_id')->nullable();
            $t->text('admin_note')->nullable();

            $t->timestamps();

            $t->index(['status', 'expires_at']);
            $t->index('purchaser_email');
            $t->index('recipient_email');

            $t->foreign('purchaser_user_id')->references('id')->on('auth_users')->nullOnDelete();
            $t->foreign('redeemed_by_user_id')->references('id')->on('auth_users')->nullOnDelete();
        });

        Schema::create('gift_card_transactions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('gift_card_id')->constrained('gift_cards')->cascadeOnDelete();
            $t->string('type', 16);                          // issue | redeem | refund | adjust | expire | void
            $t->decimal('amount', 10, 2);                    // positive = credit to balance, negative = debit
            $t->decimal('balance_after', 10, 2);

            $t->unsignedInteger('user_id')->nullable();
            $t->unsignedBigInteger('order_id')->nullable();
            $t->unsignedInteger('admin_id')->nullable();

            $t->string('note')->nullable();
            $t->json('meta')->nullable();

            $t->timestamps();
            $t->index(['gift_card_id', 'type']);
            $t->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gift_card_transactions');
        Schema::dropIfExists('gift_cards');
    }
};

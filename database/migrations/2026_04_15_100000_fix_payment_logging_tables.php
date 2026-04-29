<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Create missing payment_audit_log table
        if (!Schema::hasTable('payment_audit_log')) {
            Schema::create('payment_audit_log', function (Blueprint $table) {
                $table->increments('id');
                $table->string('transaction_id', 100)->nullable()->index();
                $table->unsignedInteger('order_id')->nullable()->index();
                $table->string('action', 100)->index();
                $table->string('actor_type', 30)->default('webhook');
                $table->unsignedInteger('actor_id')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->json('old_values')->nullable();
                $table->json('new_values')->nullable();
                $table->text('signature')->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }

        // 2. Fix payment_logs column mismatch — add columns that the webhook controller expects
        if (!Schema::hasColumn('payment_logs', 'order_id')) {
            Schema::table('payment_logs', function (Blueprint $table) {
                $table->unsignedInteger('order_id')->nullable()->after('id');
                $table->string('event_type', 50)->nullable()->after('order_id');
                $table->text('note')->nullable()->after('event_type');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_audit_log');

        if (Schema::hasColumn('payment_logs', 'order_id')) {
            Schema::table('payment_logs', function (Blueprint $table) {
                $table->dropColumn(['order_id', 'event_type', 'note']);
            });
        }
    }
};

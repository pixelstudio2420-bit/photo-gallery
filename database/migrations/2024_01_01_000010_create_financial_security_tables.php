<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('thai_banks', function (Blueprint $table) {
            $table->increments('id');
            $table->string('code', 10)->unique();
            $table->string('name_th', 100);
            $table->string('name_en', 100);
            $table->string('color', 10)->nullable();
            $table->string('swift_code', 20)->nullable();
            $table->string('icon', 50)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
        });

        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->increments('id');
            $table->string('bank_code', 10);
            $table->string('bank_name', 100);
            $table->string('bank_color', 10)->nullable();
            $table->string('account_number', 30);
            $table->string('account_holder_name', 200);
            $table->string('branch', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('payment_refunds', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('order_id');
            $table->unsignedInteger('transaction_id')->nullable();
            $table->unsignedInteger('user_id');
            $table->decimal('amount', 10, 2);
            $table->text('reason')->nullable();
            $table->enum('status', ['requested','approved','processing','completed','rejected'])->default('requested');
            $table->unsignedInteger('requested_by')->nullable();
            $table->unsignedInteger('approved_by')->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->dateTime('processed_at')->nullable();
            $table->string('refund_method', 50)->nullable();
            $table->string('refund_reference', 200)->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('security_login_attempts', function (Blueprint $table) {
            $table->increments('id');
            $table->string('ip', 45)->index();
            $table->string('email', 180)->nullable();
            $table->boolean('success')->default(false);
            $table->timestamp('attempted_at')->useCurrent();
        });

        Schema::create('event_photos_cache', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('event_id')->index();
            $table->string('file_id', 200);
            $table->string('file_name', 500)->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->string('thumbnail_link', 500)->nullable();
            $table->string('web_view_link', 500)->nullable();
            $table->timestamp('synced_at')->useCurrent();
            $table->unique(['event_id', 'file_id']);
        });

        Schema::create('email_logs', function (Blueprint $table) {
            $table->increments('id');
            $table->string('to_email', 200);
            $table->string('subject', 300);
            $table->text('body')->nullable();
            $table->enum('status', ['sent','failed'])->default('sent');
            $table->text('error')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }
    public function down(): void {
        Schema::dropIfExists('email_logs');
        Schema::dropIfExists('event_photos_cache');
        Schema::dropIfExists('security_login_attempts');
        Schema::dropIfExists('payment_refunds');
        Schema::dropIfExists('bank_accounts');
        Schema::dropIfExists('thai_banks');
    }
};

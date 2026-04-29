<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('app_settings', function (Blueprint $table) {
            $table->string('key', 100)->primary();
            $table->text('value')->nullable();
            $table->timestamp('updated_at')->useCurrent();
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('admin_id')->nullable();
            $table->unsignedInteger('user_id')->nullable();
            $table->string('action', 100);
            $table->string('target_type', 50)->nullable();
            $table->unsignedInteger('target_id')->nullable();
            $table->text('description')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['target_type', 'target_id']);
        });

        Schema::create('reviews', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('photographer_id')->nullable();
            $table->unsignedInteger('order_id')->nullable();
            $table->unsignedInteger('event_id')->nullable();
            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();
            $table->boolean('is_visible')->default(true);
            $table->text('admin_reply')->nullable();
            $table->dateTime('admin_reply_at')->nullable();
            $table->timestamps();
        });

        Schema::create('coupons', function (Blueprint $table) {
            $table->increments('id');
            $table->string('code', 50)->unique();
            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->enum('type', ['percent','fixed']);
            $table->decimal('value', 10, 2);
            $table->decimal('min_order', 10, 2)->default(0);
            $table->decimal('max_discount', 10, 2)->nullable();
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('usage_count')->default(0);
            $table->unsignedInteger('per_user_limit')->default(1);
            $table->dateTime('start_date')->nullable();
            $table->dateTime('end_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('coupon_usage', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('coupon_id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('order_id');
            $table->decimal('discount_amount', 10, 2);
            $table->dateTime('used_at')->useCurrent();
        });

        Schema::create('user_notifications', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id')->index();
            $table->string('type', 50);
            $table->string('title', 300);
            $table->text('message')->nullable();
            $table->boolean('is_read')->default(false);
            $table->string('action_url', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->dateTime('read_at')->nullable();
        });

        Schema::create('wishlists', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('event_id')->nullable();
            $table->unsignedInteger('product_id')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['user_id', 'event_id']);
        });

        Schema::create('contact_messages', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id')->nullable();
            $table->string('name', 200);
            $table->string('email', 200);
            $table->string('subject', 300);
            $table->text('message');
            $table->enum('status', ['new','read','replied'])->default('new');
            $table->text('admin_reply')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('contact_messages');
        Schema::dropIfExists('wishlists');
        Schema::dropIfExists('user_notifications');
        Schema::dropIfExists('coupon_usage');
        Schema::dropIfExists('coupons');
        Schema::dropIfExists('reviews');
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('app_settings');
    }
};

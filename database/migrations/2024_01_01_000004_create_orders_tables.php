<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id')->index();
            $table->unsignedInteger('event_id')->nullable()->index();
            $table->string('order_number', 40)->unique();
            $table->decimal('total', 10, 2)->default(0.00);
            $table->enum('status', ['cart','pending_payment','pending_review','paid','cancelled','refunded'])->default('pending_payment')->index();
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('order_id')->index();
            $table->string('photo_id', 200);
            $table->string('thumbnail_url', 500)->nullable();
            $table->decimal('price', 10, 2)->default(0.00);
        });
    }
    public function down(): void {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};

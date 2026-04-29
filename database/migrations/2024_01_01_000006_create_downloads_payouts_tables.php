<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('download_tokens', function (Blueprint $table) {
            $table->increments('id');
            $table->string('token', 80)->unique();
            $table->unsignedInteger('order_id')->index();
            $table->unsignedInteger('user_id');
            $table->string('photo_id', 200)->nullable();
            $table->dateTime('expires_at');
            $table->unsignedInteger('max_downloads')->default(5);
            $table->unsignedInteger('download_count')->default(0);
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('photographer_payouts', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('photographer_id');
            $table->unsignedInteger('order_id');
            $table->decimal('gross_amount', 10, 2);
            $table->decimal('commission_rate', 5, 2)->default(80.00);
            $table->decimal('payout_amount', 10, 2);
            $table->decimal('platform_fee', 10, 2);
            $table->enum('status', ['pending','processing','paid'])->default('pending')->index();
            $table->text('note')->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->timestamps();
            $table->unique(['photographer_id', 'order_id']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('photographer_payouts');
        Schema::dropIfExists('download_tokens');
    }
};

<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('photographer_profiles', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id')->unique();
            $table->string('photographer_code', 20)->unique();
            $table->string('display_name', 200);
            $table->text('bio')->nullable();
            $table->string('avatar', 500)->nullable();
            $table->string('bank_name', 100)->nullable();
            $table->string('bank_account_number', 30)->nullable();
            $table->string('bank_account_name', 200)->nullable();
            $table->string('promptpay_number', 20)->nullable();
            $table->string('portfolio_url', 500)->nullable();
            $table->decimal('commission_rate', 5, 2)->default(80.00);
            $table->enum('status', ['pending','approved','suspended'])->default('pending');
            $table->unsignedInteger('approved_by')->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('photographer_profiles'); }
};

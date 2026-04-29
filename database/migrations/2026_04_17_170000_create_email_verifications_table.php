<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Email verification tokens — used by AuthController for verifyEmail / sendVerificationEmail
        if (!Schema::hasTable('email_verifications')) {
            Schema::create('email_verifications', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('user_id')->index();
                $table->string('token', 255);
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('verified_at')->nullable();

                $table->index('created_at');
            });
        }

        // Password reset tokens — used by AuthController for forgot-password / resetPassword
        if (!Schema::hasTable('password_resets')) {
            Schema::create('password_resets', function (Blueprint $table) {
                $table->string('email', 180)->index();
                $table->string('token', 255);
                $table->string('guard', 20)->default('web');
                $table->timestamp('created_at')->useCurrent();

                $table->index(['email', 'guard']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('password_resets');
        Schema::dropIfExists('email_verifications');
    }
};

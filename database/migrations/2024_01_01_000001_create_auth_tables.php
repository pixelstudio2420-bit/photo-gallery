<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('auth_users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('username', 100)->nullable();
            $table->string('first_name', 100);
            $table->string('last_name', 100)->default('');
            $table->string('email', 180)->unique();
            $table->string('password_hash', 255)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('avatar', 500)->nullable();
            $table->enum('auth_provider', ['local','google','line','facebook'])->default('local');
            $table->string('provider_id', 255)->nullable();
            $table->enum('status', ['active','suspended'])->default('active');
            $table->boolean('email_verified')->default(false);
            $table->dateTime('email_verified_at')->nullable();
            $table->dateTime('last_login_at')->nullable();
            $table->unsignedInteger('login_count')->default(0);
            $table->timestamps();
        });

        Schema::create('auth_admins', function (Blueprint $table) {
            $table->increments('id');
            $table->string('email', 180)->unique();
            $table->string('password_hash', 255);
            $table->string('first_name', 100)->default('');
            $table->string('last_name', 100)->default('');
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('auth_social_logins', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id')->index();
            $table->string('provider', 30);
            $table->string('provider_id', 255);
            $table->string('avatar', 500)->nullable();
            $table->timestamps();
            $table->unique(['provider', 'provider_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auth_social_logins');
        Schema::dropIfExists('auth_admins');
        Schema::dropIfExists('auth_users');
    }
};

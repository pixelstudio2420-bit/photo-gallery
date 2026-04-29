<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->string('type', 50); // e.g. 'order', 'payment_approved', 'review', 'system'
            $table->boolean('in_app_enabled')->default(true);
            $table->boolean('email_enabled')->default(true);
            $table->boolean('sms_enabled')->default(false);
            $table->boolean('push_enabled')->default(true); // browser push
            $table->timestamps();

            $table->unique(['user_id', 'type']);
            $table->index('user_id');

            // FK — matches auth_users table in this project
            // Soft FK (no physical constraint) to allow flexibility with user types
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
    }
};

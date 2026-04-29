<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('security_rate_limits')) return;

        Schema::create('security_rate_limits', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('identifier', 191)->comment('IP address, user_id, or composite key (e.g. ip:route)');
            $table->string('route', 191)->nullable();
            $table->string('method', 10)->nullable();
            $table->unsignedInteger('attempts')->default(1);
            $table->unsignedInteger('max_attempts')->nullable();
            $table->timestamp('first_hit_at')->nullable();
            $table->timestamp('last_hit_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('blocked')->default(false);
            $table->timestamps();

            $table->unique(['identifier', 'route'], 'uq_rl_identifier_route');
            $table->index('expires_at', 'idx_rl_expires');
            $table->index('blocked', 'idx_rl_blocked');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_rate_limits');
    }
};

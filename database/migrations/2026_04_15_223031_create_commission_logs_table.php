<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commission_logs', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('photographer_id');
            $table->decimal('old_rate', 5, 2);
            $table->decimal('new_rate', 5, 2);
            $table->string('reason', 255)->nullable();
            $table->string('changed_by_type', 20)->default('admin');
            $table->unsignedInteger('changed_by_id')->nullable();
            $table->string('source', 50)->default('manual');
            $table->timestamps();

            $table->index('photographer_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_logs');
    }
};

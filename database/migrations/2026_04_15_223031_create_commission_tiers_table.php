<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commission_tiers', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 100);
            $table->decimal('min_revenue', 12, 2)->default(0);
            $table->decimal('commission_rate', 5, 2);
            $table->string('color', 7)->default('#6366f1');
            $table->string('icon', 50)->default('bi-award');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_tiers');
    }
};

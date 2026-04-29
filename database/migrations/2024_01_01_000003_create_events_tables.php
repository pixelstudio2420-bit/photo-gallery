<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('event_categories', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 100);
            $table->string('slug', 120)->unique();
            $table->string('icon', 50)->default('bi-camera');
            $table->enum('status', ['active','inactive'])->default('active');
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('event_events', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('photographer_id')->nullable()->index();
            $table->unsignedInteger('category_id')->nullable();
            $table->string('name', 300);
            $table->string('slug', 320)->unique();
            $table->text('description')->nullable();
            $table->string('cover_image', 500)->nullable();
            $table->string('drive_folder_id', 200)->nullable();
            $table->string('drive_folder_link', 500)->nullable();
            $table->string('location', 300)->nullable();
            $table->decimal('price_per_photo', 10, 2)->default(0.00);
            $table->boolean('is_free')->default(false);
            $table->enum('visibility', ['public','private','password'])->default('public');
            $table->string('event_password', 255)->nullable();
            $table->enum('status', ['draft','published','active','archived','hidden'])->default('draft')->index();
            $table->date('shoot_date')->nullable();
            $table->boolean('created_by_admin')->default(false);
            $table->unsignedInteger('view_count')->default(0);
            $table->timestamps();
        });

        Schema::create('pricing_event_prices', function (Blueprint $table) {
            $table->unsignedInteger('event_id')->primary();
            $table->decimal('price_per_photo', 10, 2);
            $table->boolean('set_by_admin')->default(false);
            $table->timestamp('updated_at')->useCurrent();
        });

        Schema::create('pricing_packages', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 200);
            $table->unsignedInteger('photo_count');
            $table->decimal('price', 10, 2);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('pricing_packages');
        Schema::dropIfExists('pricing_event_prices');
        Schema::dropIfExists('event_events');
        Schema::dropIfExists('event_categories');
    }
};

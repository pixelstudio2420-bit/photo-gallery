<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('digital_products', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 300);
            $table->string('slug', 320)->unique();
            $table->text('description')->nullable();
            $table->text('short_description')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->decimal('sale_price', 10, 2)->nullable();
            $table->string('cover_image', 500)->nullable();
            $table->json('gallery_images')->nullable();
            $table->enum('product_type', ['preset','template','overlay','other'])->default('other');
            $table->enum('file_source', ['drive','direct','local'])->default('drive');
            $table->string('drive_file_id', 200)->nullable();
            $table->string('drive_file_url', 500)->nullable();
            $table->string('direct_url', 500)->nullable();
            $table->string('local_file', 500)->nullable();
            $table->string('file_size', 50)->nullable();
            $table->string('file_format', 100)->nullable();
            $table->string('version', 20)->nullable();
            $table->string('compatibility', 300)->nullable();
            $table->json('features')->nullable();
            $table->text('requirements')->nullable();
            $table->string('demo_url', 500)->nullable();
            $table->unsignedInteger('download_limit')->default(5);
            $table->unsignedInteger('download_expiry_days')->default(30);
            $table->unsignedInteger('total_sales')->default(0);
            $table->decimal('total_revenue', 12, 2)->default(0);
            $table->enum('status', ['active','inactive','draft'])->default('draft');
            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('digital_orders', function (Blueprint $table) {
            $table->increments('id');
            $table->string('order_number', 40)->unique();
            $table->unsignedInteger('user_id')->index();
            $table->unsignedInteger('product_id');
            $table->decimal('amount', 10, 2);
            $table->string('payment_method', 50)->nullable();
            $table->string('payment_ref', 200)->nullable();
            $table->enum('status', ['pending','paid','cancelled','refunded'])->default('pending');
            $table->string('slip_image', 500)->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('digital_download_tokens', function (Blueprint $table) {
            $table->increments('id');
            $table->string('token', 80)->unique();
            $table->unsignedInteger('order_id')->index();
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('product_id');
            $table->unsignedInteger('download_count')->default(0);
            $table->unsignedInteger('max_downloads')->default(5);
            $table->dateTime('expires_at');
            $table->dateTime('last_download_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }
    public function down(): void {
        Schema::dropIfExists('digital_download_tokens');
        Schema::dropIfExists('digital_orders');
        Schema::dropIfExists('digital_products');
    }
};

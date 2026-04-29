<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_photos', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('event_id');
            $table->unsignedInteger('uploaded_by')->nullable();
            $table->enum('source', ['upload', 'drive'])->default('upload');

            // File info
            $table->string('filename', 500);
            $table->string('original_filename', 500);
            $table->string('mime_type', 100)->default('image/jpeg');
            $table->unsignedBigInteger('file_size')->default(0);
            $table->unsignedInteger('width')->default(0);
            $table->unsignedInteger('height')->default(0);

            // Storage paths (S3 keys or local disk paths)
            $table->string('storage_disk', 20)->default('public'); // 'public' or 's3'
            $table->string('original_path', 1000);
            $table->string('thumbnail_path', 1000)->nullable();
            $table->string('watermarked_path', 1000)->nullable();

            // Drive fields (when source='drive')
            $table->string('drive_file_id', 200)->nullable();
            $table->string('thumbnail_link', 1000)->nullable();

            // Display
            $table->unsignedInteger('sort_order')->default(0);
            $table->enum('status', ['active', 'processing', 'failed', 'deleted'])->default('active');

            $table->timestamps();

            $table->index('event_id');
            $table->index(['event_id', 'status']);
            $table->index('drive_file_id');
            $table->index('uploaded_by');

            $table->foreign('event_id')->references('id')->on('event_events')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_photos');
    }
};

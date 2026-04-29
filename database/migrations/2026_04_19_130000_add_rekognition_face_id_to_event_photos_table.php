<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a `rekognition_face_id` column to `event_photos` so that:
 *   • the upload pipeline can record the AWS Rekognition Face ID returned by IndexFaces
 *   • the face-search path short-circuits when a photo is already indexed (idempotent retries)
 *   • admins can identify / delete a photo's face from the collection later
 *
 * Column is NULLABLE — photos uploaded before face indexing existed (or when AWS is not
 * configured) simply stay NULL and remain compatible with the slow compareFaces fallback.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_photos', function (Blueprint $table) {
            if (!Schema::hasColumn('event_photos', 'rekognition_face_id')) {
                $table->string('rekognition_face_id', 100)
                      ->nullable()
                      ->after('watermarked_path')
                      ->comment('AWS Rekognition Face ID returned by IndexFaces');
            }
        });

        // Separate statement so adding the index is idempotent
        Schema::table('event_photos', function (Blueprint $table) {
            try {
                $table->index('rekognition_face_id', 'event_photos_rekognition_face_id_index');
            } catch (\Throwable $e) {
                // index already exists — ignore
            }
        });
    }

    public function down(): void
    {
        Schema::table('event_photos', function (Blueprint $table) {
            try {
                $table->dropIndex('event_photos_rekognition_face_id_index');
            } catch (\Throwable $e) {
                // ignore
            }
            if (Schema::hasColumn('event_photos', 'rekognition_face_id')) {
                $table->dropColumn('rekognition_face_id');
            }
        });
    }
};

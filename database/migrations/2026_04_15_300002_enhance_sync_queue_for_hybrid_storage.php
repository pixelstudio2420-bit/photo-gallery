<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Enhance sync_queue table for the Hybrid Storage Architecture.
 *
 * Adds columns needed for:
 *   - Queue job type identification (job_type)
 *   - Job payload storage (payload)
 *   - Retry management (attempts, max_attempts)
 *   - Processing timestamp (processed_at)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('sync_queue')) {
            return; // Table created in 2026_04_15_300000
        }

        Schema::table('sync_queue', function (Blueprint $table) {
            if (!Schema::hasColumn('sync_queue', 'job_type')) {
                $table->string('job_type', 50)->default('sync_photos')->after('event_id');
            }
            if (!Schema::hasColumn('sync_queue', 'payload')) {
                $table->json('payload')->nullable()->after('job_type');
            }
            if (!Schema::hasColumn('sync_queue', 'attempts')) {
                $table->unsignedSmallInteger('attempts')->default(0)->after('processed_files');
            }
            if (!Schema::hasColumn('sync_queue', 'max_attempts')) {
                $table->unsignedSmallInteger('max_attempts')->default(3)->after('attempts');
            }
            if (!Schema::hasColumn('sync_queue', 'processed_at')) {
                $table->timestamp('processed_at')->nullable()->after('completed_at');
            }
        });

        // Add index on job_type for faster lookups
        try {
            Schema::table('sync_queue', function (Blueprint $table) {
                $table->index('job_type');
            });
        } catch (\Throwable $e) {
            // Index may already exist
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('sync_queue')) {
            return;
        }

        Schema::table('sync_queue', function (Blueprint $table) {
            $columns = ['job_type', 'payload', 'attempts', 'max_attempts', 'processed_at'];
            $toDrop = [];
            foreach ($columns as $col) {
                if (Schema::hasColumn('sync_queue', $col)) {
                    $toDrop[] = $col;
                }
            }
            if (!empty($toDrop)) {
                $table->dropColumn($toDrop);
            }
        });
    }
};

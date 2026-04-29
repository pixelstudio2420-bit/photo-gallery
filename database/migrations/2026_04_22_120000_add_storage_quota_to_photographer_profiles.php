<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-photographer storage quota accounting.
 *
 *  storage_quota_bytes       — NULL = use tier default. Explicit value wins over
 *                              tier default (admin override per user).
 *  storage_used_bytes        — running total (bytes). Updated by Observer on
 *                              EventPhoto create/delete, reconciled nightly
 *                              by RecalculateStorageUsedJob.
 *  storage_recalculated_at   — last time the nightly recalc ran. Surfaces
 *                              drift to the admin ("last verified 6h ago").
 *
 * Tier defaults live in AppSettings so the admin can tune without a migration
 * (see the companion seed migration).
 *
 * Why bytes not MB: AWS/R2 bill by byte, EventPhoto.file_size is bytes, and
 * the math stays honest at petabyte scale. Displayed values get humanised in
 * StorageQuotaService.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('photographer_profiles', function (Blueprint $table) {
            $table->unsignedBigInteger('storage_quota_bytes')
                  ->nullable()
                  ->after('tier')
                  ->comment('NULL = use tier default; explicit = admin override');

            $table->unsignedBigInteger('storage_used_bytes')
                  ->default(0)
                  ->after('storage_quota_bytes')
                  ->comment('Current total bytes (all derivatives). Observer-maintained, recalc nightly.');

            $table->timestamp('storage_recalculated_at')
                  ->nullable()
                  ->after('storage_used_bytes')
                  ->comment('Last time RecalculateStorageUsedJob ran for this photographer.');

            // Hot query: find photographers who are near their quota
            // (used by admin dashboard "top usage" and upgrade-prompt emails).
            $table->index(['storage_used_bytes'], 'idx_photog_storage_used');
        });
    }

    public function down(): void
    {
        Schema::table('photographer_profiles', function (Blueprint $table) {
            $table->dropIndex('idx_photog_storage_used');
            $table->dropColumn(['storage_quota_bytes', 'storage_used_bytes', 'storage_recalculated_at']);
        });
    }
};

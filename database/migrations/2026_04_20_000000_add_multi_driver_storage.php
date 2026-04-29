<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-driver storage support: R2 / S3 / Drive / Local with hybrid mirroring
 * and direct signed-URL downloads for 5,000+ concurrent customers per day.
 *
 * Adds:
 *   - event_photos.storage_mirrors        JSON array of disks that hold this file
 *   - event_photos.last_mirror_check      timestamp the mirror state was verified
 *   - app_settings seeds                  11 keys governing driver selection
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('event_photos', function (Blueprint $table) {
            if (!Schema::hasColumn('event_photos', 'storage_mirrors')) {
                $table->json('storage_mirrors')->nullable()->after('storage_disk');
            }
            if (!Schema::hasColumn('event_photos', 'last_mirror_check')) {
                $table->timestamp('last_mirror_check')->nullable()->after('storage_mirrors');
            }
        });

        // Seed storage orchestration settings (idempotent)
        $now = now();
        $seeds = [
            // Master toggles
            ['storage_multi_driver_enabled', '0',            'Master switch for multi-driver storage orchestration'],
            ['storage_drive_enabled',        '1',            'Allow Google Drive as a storage driver (photographers upload here)'],
            ['storage_s3_enabled',           '0',            'Allow AWS S3 as a storage driver'],
            // Already seeded by R2 service, but duplicate-safe via updateOrInsert
            ['r2_enabled',                   '0',            'Allow Cloudflare R2 as a storage driver'],

            // Driver selection
            ['storage_primary_driver',       'auto',         'Primary read driver: r2|s3|drive|public|auto (auto = R2>S3>public)'],
            ['storage_upload_driver',        'auto',         'Where new uploads go: r2|s3|drive|public|auto'],
            ['storage_zip_disk',             'auto',         'Where ZIP archives are staged for customer download'],

            // Hybrid mirroring
            ['storage_mirror_enabled',       '0',            'Copy every upload to N additional drivers'],
            ['storage_mirror_targets',       '[]',           'JSON array of driver names to mirror into (e.g. ["s3","drive"])'],

            // Download flow
            ['storage_use_signed_urls',      '1',            'Redirect browser directly to cloud storage via signed URL (zero server bandwidth)'],
            ['storage_signed_url_ttl',       '3600',         'Signed URL lifetime in seconds'],
            ['storage_drive_read_fallback',  '1',            'If photo is not on primary driver yet, fall back to Google Drive'],
            ['storage_download_mode',        'redirect',     'redirect|proxy|auto — how single-photo downloads are served'],

            // Safety
            ['storage_zip_retention_hours',  '168',          'Delete built ZIPs after N hours (default 7 days)'],
        ];

        foreach ($seeds as [$key, $value, $description]) {
            DB::table('app_settings')->updateOrInsert(
                ['key' => $key],
                [
                    'value'      => $value,
                    'updated_at' => $now,
                ]
            );
        }
    }

    public function down(): void
    {
        Schema::table('event_photos', function (Blueprint $table) {
            if (Schema::hasColumn('event_photos', 'last_mirror_check')) {
                $table->dropColumn('last_mirror_check');
            }
            if (Schema::hasColumn('event_photos', 'storage_mirrors')) {
                $table->dropColumn('storage_mirrors');
            }
        });

        DB::table('app_settings')->whereIn('key', [
            'storage_multi_driver_enabled',
            'storage_drive_enabled',
            'storage_s3_enabled',
            'storage_primary_driver',
            'storage_upload_driver',
            'storage_zip_disk',
            'storage_mirror_enabled',
            'storage_mirror_targets',
            'storage_use_signed_urls',
            'storage_signed_url_ttl',
            'storage_drive_read_fallback',
            'storage_download_mode',
            'storage_zip_retention_hours',
        ])->delete();
    }
};

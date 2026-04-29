<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // ── Thai Geography Tables ──
        Schema::create('thai_provinces', function (Blueprint $table) {
            $table->unsignedSmallInteger('id')->primary();
            $table->string('name_th', 150);
            $table->string('name_en', 150);
            $table->string('geography_group', 50)->nullable();
        });

        Schema::create('thai_districts', function (Blueprint $table) {
            $table->unsignedMediumInteger('id')->primary();
            $table->unsignedSmallInteger('province_id');
            $table->string('name_th', 150);
            $table->string('name_en', 150);
            $table->foreign('province_id')->references('id')->on('thai_provinces')->cascadeOnDelete();
        });

        Schema::create('thai_subdistricts', function (Blueprint $table) {
            $table->unsignedInteger('id')->primary();
            $table->unsignedMediumInteger('district_id');
            $table->string('name_th', 150);
            $table->string('name_en', 150);
            $table->string('zip_code', 5)->nullable();
            $table->foreign('district_id')->references('id')->on('thai_districts')->cascadeOnDelete();
        });

        // ── Add location columns to events ──
        Schema::table('event_events', function (Blueprint $table) {
            $table->unsignedSmallInteger('province_id')->nullable()->after('location');
            $table->unsignedMediumInteger('district_id')->nullable()->after('province_id');
            $table->unsignedInteger('subdistrict_id')->nullable()->after('district_id');
            $table->string('location_detail', 500)->nullable()->after('subdistrict_id');
            $table->index('province_id');
            $table->index('district_id');
        });

        // ── Seed min_event_price setting ──
        \DB::table('app_settings')->insertOrIgnore([
            ['key' => 'min_event_price', 'value' => '5'],
        ]);
    }

    public function down(): void
    {
        Schema::table('event_events', function (Blueprint $table) {
            $table->dropIndex(['province_id']);
            $table->dropIndex(['district_id']);
            $table->dropColumn(['province_id', 'district_id', 'subdistrict_id', 'location_detail']);
        });

        Schema::dropIfExists('thai_subdistricts');
        Schema::dropIfExists('thai_districts');
        Schema::dropIfExists('thai_provinces');

        \DB::table('app_settings')->where('key', 'min_event_price')->delete();
    }
};

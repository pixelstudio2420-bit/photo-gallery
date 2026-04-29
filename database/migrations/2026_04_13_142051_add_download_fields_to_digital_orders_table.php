<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('digital_orders', function (Blueprint $table) {
            $table->string('download_token')->nullable()->after('status');
            $table->integer('downloads_remaining')->default(0)->after('download_token');
            $table->timestamp('expires_at')->nullable()->after('downloads_remaining');
        });
    }

    public function down(): void
    {
        Schema::table('digital_orders', function (Blueprint $table) {
            $table->dropColumn(['download_token', 'downloads_remaining', 'expires_at']);
        });
    }
};

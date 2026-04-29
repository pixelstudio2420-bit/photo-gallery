<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pricing_packages', function (Blueprint $table) {
            if (!Schema::hasColumn('pricing_packages', 'event_id')) {
                $table->unsignedBigInteger('event_id')->nullable()->after('id');
                $table->index('event_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pricing_packages', function (Blueprint $table) {
            $table->dropIndex(['event_id']);
            $table->dropColumn('event_id');
        });
    }
};

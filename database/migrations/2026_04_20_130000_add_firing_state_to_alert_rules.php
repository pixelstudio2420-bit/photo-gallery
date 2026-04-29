<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('alert_rules', function (Blueprint $t) {
            // Fire-once-per-episode state machine:
            //   firing=false + below threshold  → idle
            //   firing=false + above threshold  → fire now, set firing=true
            //   firing=true  + above threshold  → suppressed (until resolved)
            //   firing=true  + below threshold  → auto-resolve, set firing=false
            $t->boolean('firing')->default(false)->after('last_triggered_at');
            $t->timestamp('resolved_at')->nullable()->after('firing');
        });
    }

    public function down(): void
    {
        Schema::table('alert_rules', function (Blueprint $t) {
            $t->dropColumn(['firing', 'resolved_at']);
        });
    }
};

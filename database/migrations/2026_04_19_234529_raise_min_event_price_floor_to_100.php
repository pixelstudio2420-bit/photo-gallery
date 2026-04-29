<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Raise the `min_event_price` AppSetting floor from its legacy default (5 THB)
 * to 100 THB — enforcing the new business rule that every paid event must
 * charge at least 100 baht per photo.
 *
 * Only updates the value when the current stored value is BELOW 100 so that
 * admins who have already set a higher floor (e.g. 200) keep their choice.
 * If the row doesn't exist at all, inserts it at 100.
 */
return new class extends Migration {
    public function up(): void
    {
        $current = DB::table('app_settings')->where('key', 'min_event_price')->value('value');

        if ($current === null) {
            // Setting missing → seed at 100
            DB::table('app_settings')->insert([
                'key'        => 'min_event_price',
                'value'      => '100',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            return;
        }

        if ((float) $current < 100) {
            DB::table('app_settings')
                ->where('key', 'min_event_price')
                ->update([
                    'value'      => '100',
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        // Deliberately non-destructive: we don't roll the value back to 5 because
        // the application code now assumes a 100 floor. If an operator really
        // needs to lower it they can do so via the admin UI (subject to the
        // server-side min:100 guard).
    }
};

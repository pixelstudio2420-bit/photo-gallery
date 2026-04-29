<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sanitise admin_notifications.link column.
 *
 * Going forward `AdminNotification::notify()` strips absolute URLs and
 * protocol-relative paths before insert. This migration is a one-shot
 * backfill that fixes anything historical that doesn't conform — sets
 * link to NULL whenever it starts with `http://`, `https://`, or `//`,
 * so the bell-icon JS doesn't redirect admins off-site.
 *
 * A previous one-shot exists for `user_notifications` at
 * 2026_05_02_000000_fix_notification_action_urls.php — this is the
 * matching pass for the admin table.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('admin_notifications')) {
            return;
        }

        // NULL out any link starting with an absolute URL or //...
        DB::table('admin_notifications')
            ->where(function ($q) {
                $q->where('link', 'like', 'http://%')
                  ->orWhere('link', 'like', 'https://%')
                  ->orWhere('link', 'like', '//%');
            })
            ->update(['link' => null]);

        // Strip leading slashes from anything that's still relative —
        // controller's url($link) helper builds cleaner this way.
        // SQL dialects diverge on TRIM(LEADING …): MySQL/Postgres support
        // SQL-92 syntax, sqlite uses TRIM(str, chars). Branch by driver.
        $driver = DB::connection()->getDriverName();
        $rawTrim = $driver === 'sqlite'
            ? "ltrim(link, '/')"
            : "TRIM(LEADING '/' FROM link)";

        DB::table('admin_notifications')
            ->where('link', 'like', '/%')
            ->whereNotNull('link')
            ->update([
                'link' => DB::raw($rawTrim),
            ]);
    }

    public function down(): void
    {
        // Irreversible — original URLs are gone. Migration is idempotent
        // so the down() is a no-op.
    }
};

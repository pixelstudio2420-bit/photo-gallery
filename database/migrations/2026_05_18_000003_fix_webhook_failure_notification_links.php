<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One-shot fix for webhook_failure notifications that pointed to
 * `admin/payments/audit-log` — a route that never existed in this app
 * and resulted in 404 when admins clicked the bell entry.
 *
 * Updates them to `admin/security` (the security dashboard), which is
 * the most relevant surface for reviewing webhook signature failures
 * since they're security events.
 *
 * Idempotent — only matches rows with the broken link.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('admin_notifications')) {
            return;
        }

        DB::table('admin_notifications')
            ->where('link', 'admin/payments/audit-log')
            ->update(['link' => 'admin/security']);

        // Defensive: also catch any /admin/payments/audit-log variants
        // (with leading slash) that might have slipped past sanitiseLink.
        DB::table('admin_notifications')
            ->where('link', '/admin/payments/audit-log')
            ->update(['link' => 'admin/security']);
    }

    public function down(): void
    {
        // Irreversible — original link was a 404 anyway, so no point
        // restoring it.
    }
};

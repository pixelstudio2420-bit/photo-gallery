<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Fix legacy UserNotification.action_url values that were stored as absolute
 * URLs by the admin payment controller (which called route('orders.show', ...)
 * returning a full URL). The navbar template prepended "/" which produced
 * hrefs like "/http://127.0.0.1:8001/orders/5" → resolved by the browser to
 * "http://host/http://host/orders/5" → 404.
 *
 * The convention everywhere else is a leading-slash-less relative path like
 * "orders/5". This migration rewrites any stored absolute URL back to that
 * convention by stripping the scheme + host prefix and leading slash.
 *
 * Safe:
 *  - Only rewrites rows matching http(s):// prefix.
 *  - Leaves relative paths (current convention) untouched.
 *  - Records a count so admins can audit how many rows were touched.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('user_notifications')) {
            Log::info('fix_notification_action_urls: table missing, skipping');
            return;
        }

        $rows = DB::table('user_notifications')
            ->whereNotNull('action_url')
            ->where(function ($q) {
                $q->where('action_url', 'like', 'http://%')
                  ->orWhere('action_url', 'like', 'https://%');
            })
            ->select('id', 'action_url')
            ->get();

        if ($rows->isEmpty()) {
            Log::info('fix_notification_action_urls: no rows to fix');
            return;
        }

        $fixed = 0;
        foreach ($rows as $row) {
            $parts = parse_url((string) $row->action_url);
            $path  = $parts['path']     ?? '';
            $query = $parts['query']    ?? null;
            $frag  = $parts['fragment'] ?? null;

            $rel = ltrim($path, '/');
            if ($query !== null) $rel .= '?' . $query;
            if ($frag  !== null) $rel .= '#' . $frag;

            if ($rel === '') continue; // skip if we'd end up with an empty href

            DB::table('user_notifications')
                ->where('id', $row->id)
                ->update(['action_url' => $rel]);
            $fixed++;
        }

        Log::info("fix_notification_action_urls: rewrote {$fixed} rows to relative paths");
    }

    /**
     * No rollback — we don't know the original host a row was tied to, and
     * the "fixed" relative form works everywhere. Making this a no-op keeps
     * migrate:rollback safe.
     */
    public function down(): void
    {
        // intentionally empty
    }
};

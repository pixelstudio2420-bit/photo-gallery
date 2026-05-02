<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * notification_routing_rules — admin-managed matrix that decides:
 *
 *   "When event X happens, do we notify audience Y via channel Z?"
 *
 * The matrix is event_key × audience, with one row per cell. Channels
 * (in_app / email / line / sms / push) live as boolean columns on each
 * row so admin can toggle them independently. Existing notification
 * triggers across the codebase will consult this table via
 * NotificationRouter::shouldNotify($key, $audience, $channel).
 *
 * Why a flat matrix instead of a more flexible JSON config?
 *   - Admin UI is a clear table — one toggle per cell, no nested forms
 *   - SQL queries stay trivial (single boolean column lookup)
 *   - PG indexes on (event_key, audience) keep lookups <1ms even
 *     with hundreds of triggers firing per minute
 *
 * Audiences:
 *   customer      — buyer of photos / event browser (auth_users without photographer profile)
 *   photographer  — has photographer_profile row
 *   admin         — auth_admins guard
 *
 * The composite UNIQUE on (event_key, audience) prevents accidental
 * duplicates and makes idempotent UPDATEs cheap.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('notification_routing_rules', function (Blueprint $t) {
            $t->id();

            // The trigger event — namespaced like "order.created",
            // "slip.approved", "photo.uploaded". Free-form string so new
            // events don't require a migration; admin sees Thai labels in
            // the UI mapped from a static catalogue in NotificationRouter.
            $t->string('event_key', 64);

            // Which audience this row covers — one of 'customer',
            // 'photographer', 'admin'. We don't use enum/check here so
            // future audiences (e.g. 'partner', 'auditor') don't require
            // a migration; service-layer validation enforces the set.
            $t->string('audience', 20);

            // Per-channel switches. Default off so admin must explicitly
            // turn on email / LINE / SMS / push — saves accidental spam
            // on a fresh install. Only in_app defaults true (cheap +
            // expected).
            $t->boolean('in_app_enabled')->default(true);
            $t->boolean('email_enabled')->default(false);
            $t->boolean('line_enabled')->default(false);
            $t->boolean('sms_enabled')->default(false);
            $t->boolean('push_enabled')->default(false);

            // Master kill-switch for this row. Lets admin "park" a rule
            // without losing per-channel config — flip back on later
            // without re-toggling each channel.
            $t->boolean('is_enabled')->default(true);

            // Optional admin note for context — "ปิดเพราะ LINE quota เต็ม
            // 2026-04" so future admins know why something's off.
            $t->text('note')->nullable();

            $t->timestamps();

            // One rule per (event, audience) — UPSERT-friendly.
            $t->unique(['event_key', 'audience']);

            // Hot-path lookups in the service layer almost always filter
            // by event_key first, then audience. Index covers both.
            $t->index(['event_key', 'audience']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_routing_rules');
    }
};

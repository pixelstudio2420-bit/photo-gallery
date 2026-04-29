<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * LINE Messaging API hardening — adds the audit + idempotency tables the
 * webhook + send paths need to be production-safe.
 *
 * What this fixes
 * ===============
 *
 * 1) line_inbound_events  — every webhook event LINE sends is recorded
 *    BEFORE we process it. This gives us:
 *
 *      • idempotency: LINE retries a webhook delivery if we don't 200
 *        within 1s. Without dedup, a slow request creates duplicate
 *        contact_messages tickets per retry. The unique partial index
 *        on `event_id` (when LINE provides one) collapses retries to
 *        a single processed event.
 *
 *      • forensic trail: we can see which raw payload produced which
 *        ticket, which the current "log and pray" approach cannot.
 *
 *      • debug latency: payload lives next to the timestamp so we can
 *        see how long event processing actually took.
 *
 * 2) line_deliveries — every push/multicast/broadcast attempt out the
 *    door is logged with HTTP status, response body, attempt count.
 *
 *      • support troubleshooting: "did user X get the order
 *        notification?" → SELECT * FROM line_deliveries WHERE user_id=X
 *
 *      • idempotent send: caller supplies an idempotency_key (e.g.
 *        "order.{$orderId}.delivery"); a unique partial index makes
 *        a duplicate dispatch return the cached delivery row instead
 *        of paying LINE for two pushes.
 *
 *      • SLA reporting: latency / failure rate / per-channel volume
 *        all queryable from one table.
 *
 * Driver portability
 * ------------------
 * Postgres + sqlite both support the partial unique index syntax we
 * need (WHERE column IS NOT NULL). MySQL (< 8.0.13) does not — there
 * we fall back to a slightly stricter NULLs-not-equal constraint.
 */
return new class extends Migration {
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        // ── line_inbound_events ─────────────────────────────────────────
        if (!Schema::hasTable('line_inbound_events')) {
            Schema::create('line_inbound_events', function (Blueprint $t) {
                $t->id();
                // LINE supplies an `webhookEventId` since 2020; older OAs
                // / certain event types still don't. Fall back to a
                // computed hash of (user, type, timestamp, body) so dedup
                // still works.
                $t->string('event_id', 64)->nullable();
                // For message events: the LINE message id (also unique
                // per OA). Stored separately because we may want to
                // dedup by message_id even when event_id is unique
                // (defence in depth against a misconfigured retry).
                $t->string('message_id', 64)->nullable();
                // Coarse type — message | follow | unfollow | postback |
                // join | leave | accountLink | beacon | ...
                $t->string('event_type', 32);
                // Sub-type for message events: text | image | sticker |
                // video | audio | location | file. NULL for non-message
                // events.
                $t->string('message_type', 32)->nullable();
                $t->string('line_user_id', 64)->nullable();
                $t->json('payload')->nullable();
                // Lifecycle: pending → processed | failed | duplicate
                $t->string('processing_status', 16)->default('pending');
                $t->string('processing_error', 500)->nullable();
                $t->timestamp('received_at');
                $t->timestamp('processed_at')->nullable();

                $t->index('line_user_id');
                $t->index('event_type');
                $t->index('received_at');
                $t->index('processing_status');
            });
        }

        // ── line_deliveries (outbound audit trail) ─────────────────────
        if (!Schema::hasTable('line_deliveries')) {
            Schema::create('line_deliveries', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('user_id')->nullable();
                $t->string('line_user_id', 64);
                // push  — single recipient
                // multicast — up to 500 recipients (audit row PER recipient
                //              once expanded; service writes one row per
                //              line_user_id even when LINE call is batched)
                // broadcast — all OA followers (one row per call)
                // reply — replyToken-based response
                $t->string('delivery_type', 16);
                // text | image | flex | sticker | template
                $t->string('message_type', 32);
                $t->string('payload_summary', 500)->nullable();
                $t->json('payload_json')->nullable();
                // pending → sent | failed | skipped (dead user) | duplicate
                $t->string('status', 16)->default('pending');
                $t->unsignedSmallInteger('http_status')->nullable();
                $t->string('error', 500)->nullable();
                $t->unsignedTinyInteger('attempts')->default(0);
                // Caller-supplied; null means "no idempotency requested".
                // A unique (line_user_id, idempotency_key) constraint
                // makes the second attempt return the cached delivery.
                $t->string('idempotency_key', 64)->nullable();
                $t->timestamp('sent_at')->nullable();
                $t->timestamp('created_at');

                $t->index('user_id');
                $t->index('line_user_id');
                $t->index('status');
                $t->index('created_at');
                $t->index(['delivery_type', 'created_at']);
            });
        }

        // ── partial unique indexes ──────────────────────────────────────
        if ($driver === 'pgsql' || $driver === 'sqlite') {
            $this->maybeCreatePartialUnique(
                $driver,
                'uniq_line_inbound_event_id',
                "CREATE UNIQUE INDEX uniq_line_inbound_event_id
                 ON line_inbound_events(event_id) WHERE event_id IS NOT NULL"
            );
            $this->maybeCreatePartialUnique(
                $driver,
                'uniq_line_inbound_message_id',
                "CREATE UNIQUE INDEX uniq_line_inbound_message_id
                 ON line_inbound_events(message_id) WHERE message_id IS NOT NULL"
            );
            $this->maybeCreatePartialUnique(
                $driver,
                'uniq_line_deliveries_idempotency',
                "CREATE UNIQUE INDEX uniq_line_deliveries_idempotency
                 ON line_deliveries(line_user_id, idempotency_key)
                 WHERE idempotency_key IS NOT NULL"
            );
        } elseif ($driver === 'mysql' || $driver === 'mariadb') {
            // MySQL pre-8.0.13: no partial-index support. The constraint
            // is slightly stricter (treats NULL idempotency_key as a
            // single value per line_user_id) but still correct for the
            // happy path.
            try { DB::statement('CREATE UNIQUE INDEX uniq_line_inbound_event_id ON line_inbound_events(event_id)'); } catch (\Throwable) {}
            try { DB::statement('CREATE UNIQUE INDEX uniq_line_inbound_message_id ON line_inbound_events(message_id)'); } catch (\Throwable) {}
            try { DB::statement('CREATE UNIQUE INDEX uniq_line_deliveries_idempotency ON line_deliveries(line_user_id, idempotency_key)'); } catch (\Throwable) {}
        }
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();
        foreach (['uniq_line_deliveries_idempotency', 'uniq_line_inbound_event_id', 'uniq_line_inbound_message_id'] as $idx) {
            try {
                if ($driver === 'pgsql' || $driver === 'sqlite') {
                    DB::statement("DROP INDEX IF EXISTS {$idx}");
                } else {
                    DB::statement("DROP INDEX {$idx} ON line_deliveries");
                }
            } catch (\Throwable) {}
        }
        Schema::dropIfExists('line_deliveries');
        Schema::dropIfExists('line_inbound_events');
    }

    private function maybeCreatePartialUnique(string $driver, string $indexName, string $sql): void
    {
        $exists = DB::selectOne(
            $driver === 'pgsql'
                ? "SELECT 1 FROM pg_indexes WHERE indexname = ?"
                : "SELECT 1 FROM sqlite_master WHERE type='index' AND name = ?",
            [$indexName],
        );
        if (!$exists) {
            DB::statement($sql);
        }
    }
};

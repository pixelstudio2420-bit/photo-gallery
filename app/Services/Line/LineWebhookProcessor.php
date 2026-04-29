<?php

namespace App\Services\Line;

use App\Jobs\Line\DownloadLineMediaJob;
use App\Models\ContactMessage;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Processes a parsed LINE webhook payload — one event at a time, with
 * per-event idempotency.
 *
 * The class' single responsibility is: turn a LINE event into the right
 * domain side-effect, exactly once.
 *
 * Idempotency contract
 * --------------------
 * For each incoming event we INSERT a row into line_inbound_events with
 * either:
 *   • event_id (LINE's `webhookEventId`, unique per OA), or
 *   • a synthetic key derived from the event body (see eventDedupKey()).
 *
 * If the unique constraint fires we know it's a retry → skip. Otherwise
 * the row's processing_status starts at 'pending' and flips to
 * 'processed' / 'failed' once the handler runs. Concurrent webhook
 * deliveries on the same event therefore race on the INSERT, NOT on the
 * downstream side-effect — much narrower critical section.
 *
 * Supported event types (extend as needed)
 * ----------------------------------------
 *   • message / text     — create support ticket (existing behaviour)
 *   • message / image    — dispatch DownloadLineMediaJob → save to R2
 *   • message / sticker  — log only (no domain effect today)
 *   • follow             — log; future: welcome message
 *   • unfollow           — log; future: detach line_user_id
 *   • postback           — log; future: rich-menu actions
 *   • everything else    — recorded but not processed (visible in audit)
 *
 * Future event types (postback, follow, etc.) can hook in by extending
 * the dispatch() switch — the audit trail keeps working unchanged.
 */
class LineWebhookProcessor
{
    /**
     * Process a batch of events from one webhook delivery.
     *
     * @return array{processed:int, duplicate:int, failed:int, skipped:int}
     */
    public function processBatch(array $events): array
    {
        $stats = ['processed' => 0, 'duplicate' => 0, 'failed' => 0, 'skipped' => 0];

        foreach ($events as $event) {
            $result = $this->processOne($event);
            $stats[$result] = ($stats[$result] ?? 0) + 1;
        }

        return $stats;
    }

    /**
     * Process a single event with full idempotency. Returns one of:
     * 'processed' | 'duplicate' | 'failed' | 'skipped'.
     */
    public function processOne(array $event): string
    {
        $eventType   = (string) ($event['type']           ?? '');
        $messageType = (string) ($event['message']['type'] ?? '');
        $userId      = (string) ($event['source']['userId'] ?? '');
        $messageId   = (string) ($event['message']['id']    ?? '');

        // Prefer LINE's webhookEventId; fall back to a content-derived
        // hash so older OAs still get dedup. The hash is deterministic
        // for a given (user, type, timestamp, message_id, replyToken)
        // combo — exactly the granularity LINE uses for retries.
        $eventId = (string) ($event['webhookEventId'] ?? '');
        if ($eventId === '') {
            $eventId = $this->eventDedupKey($event);
        }

        // Atomically claim the event by INSERTing a 'pending' row. If
        // the unique constraint fires, this is a retry of an event
        // we've already seen — skip without touching the side-effect.
        try {
            $row = [
                'event_id'         => $eventId,
                'message_id'       => $messageId !== '' ? $messageId : null,
                'event_type'       => $eventType !== '' ? $eventType : 'unknown',
                'message_type'     => $messageType !== '' ? $messageType : null,
                'line_user_id'     => $userId !== '' ? $userId : null,
                'payload'          => json_encode($event, JSON_UNESCAPED_UNICODE),
                'processing_status'=> 'pending',
                'received_at'      => now(),
            ];
            $insertedId = DB::table('line_inbound_events')->insertGetId($row);
        } catch (QueryException $e) {
            if ($this->isUniqueViolation($e)) {
                Log::info('LineWebhookProcessor: duplicate event ignored', [
                    'event_id'   => $eventId,
                    'message_id' => $messageId,
                    'event_type' => $eventType,
                ]);
                return 'duplicate';
            }
            throw $e;
        }

        // Run the side-effect. We commit the audit row on either branch
        // so the operator sees exactly what happened to each event.
        try {
            $action = $this->dispatch($event);
            DB::table('line_inbound_events')
                ->where('id', $insertedId)
                ->update([
                    'processing_status' => 'processed',
                    'processed_at'      => now(),
                ]);
            return $action ?: 'processed';
        } catch (\Throwable $e) {
            DB::table('line_inbound_events')
                ->where('id', $insertedId)
                ->update([
                    'processing_status' => 'failed',
                    'processing_error'  => substr((string) $e->getMessage(), 0, 500),
                    'processed_at'      => now(),
                ]);
            Log::warning('LineWebhookProcessor: handler failed', [
                'event_id'   => $eventId,
                'event_type' => $eventType,
                'error'      => $e->getMessage(),
            ]);
            return 'failed';
        }
    }

    /**
     * Where the per-type business logic lives. Returning a non-default
     * status string lets the caller distinguish "ran but did nothing"
     * (skipped) from "ran successfully" (processed).
     */
    private function dispatch(array $event): ?string
    {
        $type = (string) ($event['type'] ?? '');

        return match ($type) {
            'message'  => $this->handleMessage($event),
            'follow'   => $this->handleFollow($event),
            'unfollow' => $this->handleUnfollow($event),
            'postback' => $this->handlePostback($event),
            default    => 'skipped',  // logged in audit table; no domain effect
        };
    }

    /* ─────────────────── per-type handlers ─────────────────── */

    private function handleMessage(array $event): string
    {
        $msg     = $event['message'] ?? [];
        $type    = (string) ($msg['type'] ?? '');
        $userId  = (string) ($event['source']['userId'] ?? '');
        $msgId   = (string) ($msg['id']   ?? '');

        if ($userId === '') {
            return 'skipped';
        }

        switch ($type) {
            case 'text':
                $body = trim((string) ($msg['text'] ?? ''));
                if ($body === '') return 'skipped';
                $this->createSupportInbound('line', $userId, $body, $msgId);
                return 'processed';

            case 'image':
            case 'video':
            case 'audio':
            case 'file':
                if ($msgId === '') return 'skipped';
                // Download happens out-of-band: webhook must return 200
                // within 1s, but media downloads can take 5-30s for a
                // full-size photo. The job runs on its own queue so a
                // burst of inbound media can't starve the web pool.
                DownloadLineMediaJob::dispatch(
                    messageId:   $msgId,
                    lineUserId:  $userId,
                    contentType: $type,
                );
                return 'processed';

            case 'sticker':
            case 'location':
                // No domain effect today; the audit row preserves the
                // raw payload so we can backfill handling later.
                return 'skipped';

            default:
                return 'skipped';
        }
    }

    private function handleFollow(array $event): string
    {
        // Future: dispatch a welcome message via LineNotifyService.
        // Today: just record (the audit row already captured payload).
        return 'processed';
    }

    private function handleUnfollow(array $event): string
    {
        $userId = (string) ($event['source']['userId'] ?? '');
        if ($userId === '') return 'skipped';

        // Detach the LINE user id so we don't keep pushing to a user who
        // has explicitly blocked the OA. (Sends would 400 anyway, but
        // detaching here reduces noise + log volume.)
        try {
            DB::table('users')
                ->where('line_user_id', $userId)
                ->update(['line_user_id' => null, 'updated_at' => now()]);
        } catch (\Throwable $e) {
            Log::info('LineWebhookProcessor.unfollow: detach skipped', [
                'error' => $e->getMessage(),
            ]);
        }
        return 'processed';
    }

    private function handlePostback(array $event): string
    {
        // Rich-menu / Flex button payloads. We persist the raw payload
        // and let downstream code (e.g. an order-action handler) read
        // it from line_inbound_events as needed.
        return 'processed';
    }

    /* ─────────────────── helpers ─────────────────── */

    /**
     * Synthetic dedup key for events without webhookEventId. The mix of
     * fields matches what LINE uses internally to decide whether two
     * deliveries are the same retry.
     */
    private function eventDedupKey(array $event): string
    {
        $parts = [
            (string) ($event['type'] ?? ''),
            (string) ($event['source']['userId'] ?? ''),
            (string) ($event['timestamp'] ?? ''),
            (string) ($event['message']['id'] ?? ''),
            (string) ($event['replyToken'] ?? ''),
            (string) ($event['postback']['data'] ?? ''),
        ];
        return 'syn:' . substr(sha1(implode('|', $parts)), 0, 56);
    }

    /**
     * Mirror of PaymentWebhookController::createSupportInbound() —
     * extracted here so the webhook controller can be a thin shim. The
     * `line_message_id` (when present) is stored in the ticket_number
     * suffix so support staff can correlate back to the LINE event.
     */
    private function createSupportInbound(string $channel, string $senderId, string $body, string $messageId): void
    {
        // The line_inbound_events unique-on-message_id constraint already
        // guarantees we only get here once per message. We can therefore
        // INSERT without a same-day body-hash dedup probe.
        //
        // category/status enums (per 2026_04_17_130000_enhance_contact_to_ticket_system):
        //   category: general | billing | technical | account | refund | photographer | other
        //   status:   new | read | replied
        // 'general' + 'new' is the closest match for an inbound chat from
        // an unknown sender — admin can re-categorise from the inbox UI.
        ContactMessage::create([
            'ticket_number'    => 'CHAT-' . strtoupper(substr($channel, 0, 2))
                                  . '-' . now()->format('ymd') . '-'
                                  . strtoupper(substr($messageId !== '' ? $messageId : bin2hex(random_bytes(2)), -6)),
            'name'             => 'LINE User ' . substr($senderId, 0, 8),
            'email'            => "{$channel}+{$senderId}@webhook.local",
            'subject'          => "ข้อความจาก {$channel}",
            'category'         => 'general',
            'priority'         => 'normal',
            'message'          => $body,
            'status'           => 'new',
            'last_activity_at' => now(),
        ]);
    }

    private function isUniqueViolation(\Throwable $e): bool
    {
        $msg = strtolower($e->getMessage());
        return $e->getCode() === '23505'                 // pgsql
            || $e->getCode() === '23000'                 // mysql/sqlite
            || str_contains($msg, 'duplicate')
            || str_contains($msg, 'unique constraint');
    }
}

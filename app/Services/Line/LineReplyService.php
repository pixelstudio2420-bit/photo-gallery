<?php

namespace App\Services\Line;

use App\Models\AppSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Replies to inbound LINE events using the `replyToken`.
 *
 * Why use replyToken instead of push?
 * -----------------------------------
 * LINE meters /v2/bot/message/push against your monthly quota — 200 free
 * pushes per month, then ~฿0.10–0.15 per push. /v2/bot/message/reply
 * is FREE (within the 1-minute reply window) because it's a direct
 * response to a user-initiated message.
 *
 * For a chatbot answering FAQ questions or echoing "we got your photo",
 * this can be the difference between a free deployment and a billable one.
 *
 * Reply token rules (per LINE docs)
 * ---------------------------------
 *   • Must be used within 1 minute of the original event.
 *   • Single use — calling reply twice with the same token is a 400.
 *   • Maximum 5 messages per reply call (same as push).
 *   • Only available on message / postback / follow / beacon events
 *     (NOT on join/leave/unfollow — those don't carry a token).
 *
 * Token sourcing
 * --------------
 * The token comes from the LINE webhook event payload, which we already
 * persist in line_inbound_events.payload. This service can either:
 *
 *   • take the token directly (caller already has it), or
 *   • look it up by line_inbound_events.id (caller has the row id).
 *
 * Audit
 * -----
 * Every reply attempt writes a line_deliveries row with delivery_type='reply'
 * — so reply quota use is observable side-by-side with push spend.
 */
class LineReplyService
{
    public function __construct(
        private readonly LineDeliveryLogger $logger,
    ) {}

    /**
     * Reply directly with a known reply token. Returns:
     *   ['ok' => bool, 'http_status' => ?int, 'error' => ?string]
     */
    public function replyWithToken(string $replyToken, array $messages, ?string $lineUserId = null): array
    {
        if ($replyToken === '') {
            return ['ok' => false, 'http_status' => null, 'error' => 'empty replyToken'];
        }

        $audit = $this->logger->begin(
            userId:         null,
            lineUserId:     $lineUserId ?? '(unknown)',
            deliveryType:   'reply',
            messageType:    (string) ($messages[0]['type'] ?? 'text'),
            payloadSummary: $this->summarise($messages),
            payloadJson:    $messages,
            // replyToken is naturally unique (single-use) — perfect dedup key.
            idempotencyKey: 'reply.' . substr($replyToken, 0, 50),
        );
        if ($audit['duplicate']) {
            // Already attempted — return the prior result via the row.
            $row = DB::table('line_deliveries')->where('id', $audit['id'])->first();
            $ok  = $row && $row->status === 'sent';
            return [
                'ok'          => $ok,
                'http_status' => $row?->http_status,
                'error'       => $ok ? null : ($row?->error ?? 'duplicate reply'),
            ];
        }

        $token = (string) AppSetting::get('line_channel_access_token', '');
        if ($token === '') {
            $this->logger->markFailed($audit['id'], null, 'channel access token missing');
            return ['ok' => false, 'http_status' => null, 'error' => 'missing token'];
        }

        try {
            $response = Http::withToken($token)
                ->timeout(10)
                ->post('https://api.line.me/v2/bot/message/reply', [
                    'replyToken' => $replyToken,
                    'messages'   => $messages,
                ]);

            if ($response->successful()) {
                $this->logger->markSent($audit['id'], $response->status());
                return ['ok' => true, 'http_status' => $response->status(), 'error' => null];
            }

            // 400 "Invalid reply token" usually means the 1-minute window
            // expired. Caller should fall back to push for time-insensitive
            // notifications.
            $body = $response->body();
            $this->logger->markFailed($audit['id'], $response->status(), substr($body, 0, 500));

            Log::warning('LineReplyService: reply failed', [
                'status' => $response->status(),
                'body'   => substr($body, 0, 200),
            ]);
            return [
                'ok'          => false,
                'http_status' => $response->status(),
                'error'       => substr($body, 0, 200),
            ];
        } catch (\Throwable $e) {
            $this->logger->markFailed($audit['id'], null, $e->getMessage());
            return ['ok' => false, 'http_status' => null, 'error' => $e->getMessage()];
        }
    }

    /**
     * Convenience wrapper — look up the replyToken from a previously
     * recorded inbound event row by id, then reply. Useful when a job
     * processes events out-of-band and only has the row id available.
     */
    public function replyToInboundEvent(int $inboundEventId, array $messages): array
    {
        $row = DB::table('line_inbound_events')->where('id', $inboundEventId)->first();
        if (!$row) {
            return ['ok' => false, 'http_status' => null, 'error' => 'inbound event not found'];
        }
        $payload = json_decode((string) $row->payload, true) ?: [];
        $token   = (string) ($payload['replyToken'] ?? '');
        if ($token === '') {
            return ['ok' => false, 'http_status' => null, 'error' => 'event has no replyToken'];
        }
        return $this->replyWithToken($token, $messages, $row->line_user_id);
    }

    /**
     * Convenience: send a plain text reply.
     */
    public function replyText(string $replyToken, string $text, ?string $lineUserId = null): array
    {
        return $this->replyWithToken($replyToken, [
            ['type' => 'text', 'text' => mb_substr($text, 0, 5000)],
        ], $lineUserId);
    }

    private function summarise(array $messages): string
    {
        $first = $messages[0] ?? [];
        $type  = (string) ($first['type'] ?? 'unknown');
        $body  = match ($type) {
            'text'  => (string) ($first['text'] ?? ''),
            default => "[{$type}]",
        };
        return mb_substr(preg_replace('/\s+/', ' ', $body) ?? '', 0, 500);
    }
}

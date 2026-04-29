<?php

namespace App\Services\Marketing;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * LINE Messaging API — broadcast to your Official Account friends.
 *
 * Docs: https://developers.line.biz/en/reference/messaging-api/#send-broadcast-message
 *
 * Free tier (2025): 200 messages/month (outbound).
 * After that: ฿0.10-0.15 per message (auto-billed via LINE Console).
 *
 * Prereq: Set marketing_line_channel_access_token + enable marketing_line_messaging.
 */
class LineBroadcastService
{
    public function __construct(protected MarketingService $marketing) {}

    protected const API_BASE = 'https://api.line.me/v2/bot';

    public function enabled(): bool
    {
        return $this->marketing->lineMessagingEnabled()
            && (bool) $this->marketing->get('line_channel_access_token');
    }

    /**
     * Broadcast a text message to ALL friends of the OA.
     * Returns ['ok' => bool, 'status' => int, 'error' => string|null].
     */
    public function broadcastText(string $text): array
    {
        return $this->broadcast([
            ['type' => 'text', 'text' => mb_substr($text, 0, 5000)],
        ]);
    }

    /**
     * Broadcast a flex message (rich card). Use official Flex Message Simulator
     * to design: https://developers.line.biz/flex-simulator/
     */
    public function broadcastFlex(string $altText, array $contents): array
    {
        return $this->broadcast([[
            'type'     => 'flex',
            'altText'  => mb_substr($altText, 0, 400),
            'contents' => $contents,
        ]]);
    }

    /**
     * Send a text message to a specific user (via userId from LINE Login).
     */
    public function pushText(string $userId, string $text): array
    {
        return $this->push($userId, [
            ['type' => 'text', 'text' => mb_substr($text, 0, 5000)],
        ]);
    }

    /**
     * Get remaining message quota for the current month.
     */
    public function quota(): array
    {
        if (!$this->enabled()) {
            return ['ok' => false, 'error' => 'LINE Messaging API disabled'];
        }
        try {
            $token = $this->marketing->get('line_channel_access_token');
            $resp = Http::timeout(5)->withToken($token)->get(self::API_BASE . '/message/quota');
            return ['ok' => $resp->successful(), 'data' => $resp->json(), 'status' => $resp->status()];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get count of messages sent this month.
     */
    public function consumption(): array
    {
        if (!$this->enabled()) return ['ok' => false];
        try {
            $token = $this->marketing->get('line_channel_access_token');
            $resp = Http::timeout(5)->withToken($token)->get(self::API_BASE . '/message/quota/consumption');
            return ['ok' => $resp->successful(), 'data' => $resp->json()];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    // ── Internals ───────────────────────────────────────────────

    protected function broadcast(array $messages): array
    {
        if (!$this->enabled()) {
            return ['ok' => false, 'error' => 'LINE Messaging API not enabled/configured'];
        }

        // ── Quota guard ────────────────────────────────────────────────
        // Broadcasts go to ALL OA followers, so one call costs N messages
        // (N = follower count). Without this guard, a misclick on the
        // marketing dashboard can blow through the free tier and start
        // billing immediately.
        //
        // Caller can opt-out via app_settings.line_broadcast_quota_check=0
        // for one-off marketing campaigns where the team has confirmed
        // they're OK paying. Default IS strict.
        $enforce = (string) \App\Models\AppSetting::get('line_broadcast_quota_check', '1') === '1';
        if ($enforce) {
            $check = $this->checkQuota();
            if (!$check['ok']) {
                Log::warning('LINE broadcast blocked by quota guard', [
                    'used'  => $check['used']  ?? null,
                    'limit' => $check['limit'] ?? null,
                ]);
                return [
                    'ok'    => false,
                    'error' => $check['reason'] ?? 'quota check failed',
                    'used'  => $check['used']   ?? null,
                    'limit' => $check['limit']  ?? null,
                ];
            }
        }

        try {
            $token = $this->marketing->get('line_channel_access_token');
            $resp = Http::timeout(10)
                ->withToken($token)
                ->asJson()
                ->post(self::API_BASE . '/message/broadcast', ['messages' => $messages]);

            if (!$resp->successful()) {
                Log::warning('LINE broadcast failed', ['status' => $resp->status(), 'body' => $resp->body()]);
            }
            return [
                'ok'     => $resp->successful(),
                'status' => $resp->status(),
                'error'  => $resp->successful() ? null : ($resp->json('message') ?? $resp->body()),
            ];
        } catch (\Throwable $e) {
            Log::error('LINE broadcast exception', ['error' => $e->getMessage()]);
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Compares current monthly consumption against the quota limit.
     * Returns ['ok' => bool, 'used' => int, 'limit' => int|null,
     *         'reason' => string|null].
     *
     * The 'ok' field means "broadcast is safe to fire right now" — it's
     * conservative: if we can't establish quota state (network glitch,
     * missing endpoint), we err on the side of letting the broadcast
     * through rather than blocking marketing on a transient API issue.
     * Callers who want strict-block can set
     * app_settings.line_broadcast_quota_strict=1.
     */
    public function checkQuota(): array
    {
        $strict = (string) \App\Models\AppSetting::get('line_broadcast_quota_strict', '0') === '1';

        $quotaResp       = $this->quota();
        $consumptionResp = $this->consumption();

        if (!$quotaResp['ok'] || !$consumptionResp['ok']) {
            return [
                'ok'     => !$strict,
                'used'   => null,
                'limit'  => null,
                'reason' => 'unable to read quota / consumption from LINE'
                            . ($strict ? ' (strict mode → blocked)' : ' (lenient mode → allowed)'),
            ];
        }

        // LINE response shape:
        //   /message/quota → { type: 'limited'|'none', value: int }  (value omitted when type=none)
        //   /message/quota/consumption → { totalUsage: int }
        $quotaData       = (array) ($quotaResp['data']       ?? []);
        $consumptionData = (array) ($consumptionResp['data'] ?? []);
        $type  = (string) ($quotaData['type']  ?? 'limited');
        $limit = $type === 'none' ? null : (int) ($quotaData['value'] ?? 0);
        $used  = (int) ($consumptionData['totalUsage'] ?? 0);

        // 'none' = unlimited paid plan, no need to block.
        if ($limit === null) {
            return ['ok' => true, 'used' => $used, 'limit' => null, 'reason' => null];
        }

        // Reserve a small headroom so a multi-recipient broadcast doesn't
        // exactly tip us over. 5% (or 50 messages, whichever is smaller).
        $headroom = (int) min(50, max(0, floor($limit * 0.05)));

        if ($used + $headroom >= $limit) {
            return [
                'ok'     => false,
                'used'   => $used,
                'limit'  => $limit,
                'reason' => sprintf(
                    'quota exhausted: used %d of %d (with %d-msg headroom)',
                    $used, $limit, $headroom,
                ),
            ];
        }

        return ['ok' => true, 'used' => $used, 'limit' => $limit, 'reason' => null];
    }

    protected function push(string $userId, array $messages): array
    {
        if (!$this->enabled()) return ['ok' => false, 'error' => 'LINE Messaging API not enabled'];

        try {
            $token = $this->marketing->get('line_channel_access_token');
            $resp = Http::timeout(10)
                ->withToken($token)
                ->asJson()
                ->post(self::API_BASE . '/message/push', [
                    'to'       => $userId,
                    'messages' => $messages,
                ]);
            return [
                'ok'     => $resp->successful(),
                'status' => $resp->status(),
                'error'  => $resp->successful() ? null : ($resp->json('message') ?? $resp->body()),
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}

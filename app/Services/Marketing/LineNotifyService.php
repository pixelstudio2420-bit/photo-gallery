<?php

namespace App\Services\Marketing;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * LINE Notify — lightweight notification service (separate from Messaging API).
 *
 * Good for: personal/group notifications (photo-ready, order-status).
 * The notify token is bound to a single user or group chat.
 *
 * Rate limits: 1000 requests/hour per token.
 *
 * Note: LINE Notify will be shut down 2025-03-31 per LINE announcement.
 * This service still works for current deployments — consider migrating
 * workflow-critical alerts to the Messaging API (push) before EOL.
 */
class LineNotifyService
{
    public function __construct(protected MarketingService $marketing) {}

    protected const API_URL = 'https://notify-api.line.me/api/notify';

    public function enabled(): bool
    {
        return $this->marketing->lineNotifyEnabled()
            && (bool) $this->marketing->get('line_notify_token');
    }

    /**
     * Send a notification message.
     *
     * @param string      $message   Text (max 1000 chars)
     * @param string|null $imageUrl  Optional image URL (thumbnail + full)
     * @param string|null $token     Override default token (for per-user tokens)
     * @return array{ok:bool,status?:int,error?:string}
     */
    public function send(string $message, ?string $imageUrl = null, ?string $token = null): array
    {
        $token = $token ?: $this->marketing->get('line_notify_token');
        if (!$token) return ['ok' => false, 'error' => 'LINE Notify not configured'];
        if (!$this->marketing->lineNotifyEnabled()) {
            return ['ok' => false, 'error' => 'LINE Notify disabled'];
        }

        try {
            $payload = ['message' => mb_substr($message, 0, 1000)];
            if ($imageUrl) {
                $payload['imageThumbnail'] = $imageUrl;
                $payload['imageFullsize']  = $imageUrl;
            }

            $resp = Http::timeout(8)
                ->withToken($token)
                ->asForm()
                ->post(self::API_URL, $payload);

            if (!$resp->successful()) {
                Log::warning('LINE Notify failed', ['status' => $resp->status(), 'body' => $resp->body()]);
            }
            return [
                'ok'     => $resp->successful(),
                'status' => $resp->status(),
                'error'  => $resp->successful() ? null : ($resp->json('message') ?? $resp->body()),
            ];
        } catch (\Throwable $e) {
            Log::error('LINE Notify exception', ['error' => $e->getMessage()]);
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Send a templated notification.
     * Examples: 'photo_ready', 'order_paid', 'new_event'
     */
    public function sendTemplate(string $template, array $vars = [], ?string $token = null): array
    {
        $templates = [
            'photo_ready'  => "📸 รูปของคุณพร้อมดาวน์โหลดแล้ว\nOrder: #{order_id}\nดาวน์โหลด: {url}",
            'order_paid'   => "✅ ได้รับการชำระเงินแล้ว\nOrder: #{order_id}\nยอด: {amount} บาท",
            'new_event'    => "🎉 อีเวนต์ใหม่: {event_name}\nดูรายละเอียด: {url}",
            'coupon'       => "🎁 คูปองใหม่ {code}\nลด {amount}\nหมดอายุ: {expires_at}",
        ];
        $text = $templates[$template] ?? '';
        foreach ($vars as $k => $v) {
            $text = str_replace('{' . $k . '}', (string) $v, $text);
        }
        return $this->send($text, $vars['image'] ?? null, $token);
    }
}

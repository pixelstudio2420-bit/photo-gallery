<?php

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * LINE delivery service (admin notifications + customer push).
 *
 * Class name retained for backward compatibility with the 11 callsites in
 * controllers/observers — historically this used "LINE Notify" but that API
 * was killed by LINE on 31 March 2025. We now push to admins via the LINE
 * Messaging API (multicast to admin LINE userIds) and to customers via push.
 *
 * Settings keys this service reads:
 *   line_messaging_enabled        master toggle (was line_notify_enabled too)
 *   line_channel_access_token     OA Messaging API channel access token
 *   line_admin_user_ids           comma/space-separated U... ids for admins
 *   line_admin_notify_*           per-event admin alert toggles (existing)
 *   line_user_push_*              per-event customer push toggles (existing)
 */
class LineNotifyService
{
    /** @var array|null Lazily cached settings for this request */
    private ?array $cachedSettings = null;

    // -------------------------------------------------------------------------
    // Status checks
    // -------------------------------------------------------------------------

    /**
     * Master toggle: is LINE delivery (admin + customer) enabled?
     * Returns true if Messaging API is configured.
     */
    public function isMessagingEnabled(): bool
    {
        return $this->isToggleOn('line_messaging_enabled');
    }

    /**
     * Umbrella feature gate — the SAME key admin can toggle from
     * /admin/features. Layers on top of isMessagingEnabled() and the
     * existing per-trigger line_admin_notify_* / line_user_push_*
     * toggles to give the admin a single kill-switch per category.
     *
     * Categories used here:
     *   line_delivery          → photo + download link push to BUYER
     *   line_notify_admin      → admin alerts (new order / slip / event)
     *   line_notify_customer   → buyer-facing order status messages
     *   line_broadcast         → LINE OA broadcast to followers
     *   line_lifecycle         → automated welcome / abandoned-cart
     *
     * Defaults to ON when no setting row exists so deployments that
     * upgrade from older schema don't silently lose their LINE flows.
     */
    public function umbrellaAllows(string $featureKey): bool
    {
        return (string) AppSetting::get('feature_' . $featureKey . '_enabled', '1') === '1';
    }

    // -------------------------------------------------------------------------
    // Admin push (replaces dead LINE Notify) — multicast to admin userIds via
    // LINE Messaging API. Falls back to no-op if no admin ids are configured.
    // -------------------------------------------------------------------------

    /**
     * Push a text message to every configured admin LINE userId.
     * Returns true only if at least one delivery succeeded.
     */
    public function notifyAdmin(string $message): bool
    {
        if (!$this->isMessagingEnabled() || !$this->umbrellaAllows('line_notify_admin')) return false;

        $userIds = $this->getAdminLineUserIds();
        if (empty($userIds)) return false;

        return $this->sendMulticast($userIds, [
            ['type' => 'text', 'text' => $message],
        ]);
    }

    /**
     * Push a text + image message to every configured admin LINE userId.
     * The image must be a fully-qualified HTTPS URL (LINE rejects http/local).
     */
    public function notifyAdminWithImage(string $message, string $imageUrl): bool
    {
        if (!$this->isMessagingEnabled() || !$this->umbrellaAllows('line_notify_admin')) return false;

        $userIds = $this->getAdminLineUserIds();
        if (empty($userIds)) return false;

        $messages = [['type' => 'text', 'text' => $message]];
        if ($imageUrl && str_starts_with($imageUrl, 'https://')) {
            $messages[] = [
                'type'              => 'image',
                'originalContentUrl'=> $imageUrl,
                'previewImageUrl'   => $imageUrl,
            ];
        }
        return $this->sendMulticast($userIds, $messages);
    }

    // -------------------------------------------------------------------------
    // Admin notification helpers (event-specific wrappers)
    // -------------------------------------------------------------------------

    public function notifyNewEvent(array $event, string $createdBy = 'admin'): bool
    {
        if (!$this->isMessagingEnabled() || !$this->umbrellaAllows('line_notify_admin') || !$this->isToggleOn('line_admin_notify_events')) {
            return false;
        }

        $name      = $event['name'] ?? '-';
        $shootDate = $event['shoot_date'] ?? '-';
        $message   = "📸 อีเวนต์ใหม่!\nชื่อ: {$name}\nวันที่: {$shootDate}\nสร้างโดย: {$createdBy}";

        return $this->notifyAdmin($message);
    }

    public function notifyNewOrder(array $order, string $type = 'photo'): bool
    {
        if (!$this->isMessagingEnabled() || !$this->umbrellaAllows('line_notify_admin') || !$this->isToggleOn('line_admin_notify_orders')) {
            return false;
        }

        $orderNumber  = $order['order_number'] ?? $order['id'] ?? '-';
        $totalAmount  = $order['total_amount'] ?? $order['total'] ?? 0;
        $message = "🛒 มีออเดอร์ใหม่!\nหมายเลข: {$orderNumber}\nจำนวน: {$totalAmount} บาท";

        return $this->notifyAdmin($message);
    }

    public function notifyNewSlip(array $order, string $type = 'photo'): bool
    {
        if (!$this->isMessagingEnabled() || !$this->umbrellaAllows('line_notify_admin') || !$this->isToggleOn('line_admin_notify_orders')) {
            return false;
        }

        $orderNumber = $order['order_number'] ?? $order['id'] ?? '-';
        $totalAmount = $order['total_amount'] ?? $order['total'] ?? 0;
        $message = "📎 มีสลิปโอนเงินใหม่!\nออเดอร์: {$orderNumber}\nจำนวน: {$totalAmount} บาท\nรอตรวจสอบ";

        return $this->notifyAdmin($message);
    }

    public function notifyAdminPendingSlip(array $order, string $type = 'photo'): bool
    {
        return $this->notifyNewSlip($order, $type);
    }

    public function notifyNewRegistration(array $user, string $type = 'user'): bool
    {
        if (!$this->isMessagingEnabled() || !$this->umbrellaAllows('line_notify_admin') || !$this->isToggleOn('line_admin_notify_registration')) {
            return false;
        }

        $name    = $user['name'] ?? '-';
        $email   = $user['email'] ?? '-';
        $message = "👤 สมาชิกใหม่!\nชื่อ: {$name}\nอีเมล: {$email}\nประเภท: {$type}";

        return $this->notifyAdmin($message);
    }

    public function notifyNewWithdrawal(array $payout): bool
    {
        if (!$this->isMessagingEnabled() || !$this->umbrellaAllows('line_notify_admin') || !$this->isToggleOn('line_admin_notify_payouts')) {
            return false;
        }

        $photographerName = $payout['photographer_name'] ?? '-';
        $amount           = $payout['amount'] ?? 0;
        $message = "💰 คำขอถอนเงิน!\nช่างภาพ: {$photographerName}\nจำนวน: {$amount} บาท";

        return $this->notifyAdmin($message);
    }

    public function notifyNewContact(array $contact): bool
    {
        if (!$this->isMessagingEnabled() || !$this->umbrellaAllows('line_notify_admin') || !$this->isToggleOn('line_admin_notify_contact')) {
            return false;
        }

        $name    = $contact['name'] ?? '-';
        $email   = $contact['email'] ?? '-';
        $subject = $contact['subject'] ?? '-';
        $message = "📩 ข้อความติดต่อใหม่!\nจาก: {$name}\nอีเมล: {$email}\nหัวข้อ: {$subject}";

        return $this->notifyAdmin($message);
    }

    public function notifyOrderCancelled(array $order, string $action = 'cancelled'): bool
    {
        if (!$this->isMessagingEnabled() || !$this->umbrellaAllows('line_notify_admin') || !$this->isToggleOn('line_admin_notify_cancellation')) {
            return false;
        }

        $orderNumber = $order['order_number'] ?? $order['id'] ?? '-';
        $message = "❌ ออเดอร์ถูก{$action}!\nหมายเลข: {$orderNumber}";

        return $this->notifyAdmin($message);
    }

    // -------------------------------------------------------------------------
    // LINE Messaging API (user push)
    // -------------------------------------------------------------------------

    public function pushToUser(int $userId, array $messages, ?string $idempotencyKey = null): bool
    {
        if (!$this->isMessagingEnabled() || !$this->isToggleOn('line_user_push_enabled')) {
            return false;
        }

        $lineUserId = $this->getLineUserId($userId);
        if (!$lineUserId) {
            return false;
        }

        return $this->sendPush($lineUserId, $messages, $idempotencyKey);
    }

    /**
     * Same as pushToUser, but the actual HTTP call is dispatched to the
     * 'notifications' queue instead of running inline. Use this when:
     *
     *   • the caller is a webhook (must ACK fast),
     *   • the caller fans out to many recipients (mass notify),
     *   • the call is non-critical for the request response (a payment
     *     succeeded; the customer can wait a few seconds for their
     *     notification).
     *
     * The audit row is opened synchronously (so the caller knows the
     * delivery was registered), but the wire-level HTTP call happens in
     * SendLinePushJob with full retry + exponential backoff.
     */
    public function queuePushToUser(int $userId, array $messages, ?string $idempotencyKey = null): bool
    {
        if (!$this->isMessagingEnabled() || !$this->isToggleOn('line_user_push_enabled')) {
            return false;
        }
        $lineUserId = $this->getLineUserId($userId);
        if (!$lineUserId || !$this->isValidLineUserId($lineUserId)) {
            return false;
        }

        $logger = app(\App\Services\Line\LineDeliveryLogger::class);
        $audit  = $logger->begin(
            userId:         $userId,
            lineUserId:     $lineUserId,
            deliveryType:   'push',
            messageType:    (string) ($messages[0]['type'] ?? 'text'),
            payloadSummary: $this->summariseMessages($messages),
            payloadJson:    $messages,
            idempotencyKey: $idempotencyKey,
        );
        // If we already sent this idempotent delivery, don't re-queue.
        // The LineDeliveryLogger::begin() catch on the partial unique
        // index `(line_user_id, idempotency_key)` collapsed the second
        // call into the first row — re-firing the push would either
        // return a 200 (LINE deduplicates by retry token) or worse
        // re-spam the chat. Either way, returning true here lets the
        // caller treat dispatch as "succeeded" without doing another
        // round-trip.
        if ($audit['duplicate']) {
            return true;
        }

        // Inline-vs-queue dispatch.
        //
        // The default is INLINE (`dispatchSync`) because Laravel Cloud
        // installs without a dedicated `notifications` queue worker
        // would otherwise see SendLinePushJob rows pile up in the jobs
        // table while line_deliveries.status stays 'pending' forever.
        // The customer never sees the photos and the admin has no
        // signal that anything went wrong (the audit row exists, but
        // it never flips to 'sent' or 'failed').
        //
        // Operators who DO have a worker running queue:work --queue=
        // notifications can flip `line_delivery_inline` to '0' to
        // restore the original async behaviour and save the HTTP
        // request a few hundred ms per push. The trade-off:
        //   inline  → admin's slip-approve click takes ~3-15s extra
        //             on a 30-photo order (worth it for the delivery
        //             confirmation appearing immediately)
        //   queue   → admin click is instant; delivery takes whatever
        //             the worker's polling interval is (default ~3s)
        $inline = (string) AppSetting::get('line_delivery_inline', '1') === '1';
        if ($inline) {
            \App\Jobs\Line\SendLinePushJob::dispatchSync($lineUserId, $messages, $audit['id']);
        } else {
            \App\Jobs\Line\SendLinePushJob::dispatch($lineUserId, $messages, $audit['id']);
        }
        return true;
    }

    /**
     * Queued variant of pushPhotos. Each chunk goes through SendLinePushJob
     * with its own idempotency key, so a job rerun (worker crash, double
     * dispatch) won't double-spam the customer.
     *
     * @param array<int, array{original_url:string, preview_url:string}> $images
     * @param string $idempotencyPrefix  e.g. "order.42.line.photos" — chunks become
     *                                   "order.42.line.photos.caption", ".0", ".1", ...
     */
    public function queuePushPhotos(int $userId, array $images, ?string $caption, string $idempotencyPrefix): bool
    {
        if (!$this->isMessagingEnabled() || !$this->umbrellaAllows('line_delivery') || !$this->isToggleOn('line_user_push_enabled')) {
            return false;
        }
        $lineUserId = $this->getLineUserId($userId);
        if (!$lineUserId) return false;

        $images = array_slice($images, 0, 100);
        $allOk  = true;

        if ($caption) {
            $allOk = $this->queuePushToUser(
                $userId,
                [['type' => 'text', 'text' => $caption]],
                idempotencyKey: $idempotencyPrefix . '.caption',
            ) && $allOk;
        }

        $chunkIdx = 0;
        foreach (array_chunk($images, 5) as $chunk) {
            $messages = [];
            foreach ($chunk as $img) {
                $orig = $img['original_url'] ?? null;
                $prev = $img['preview_url']  ?? $orig;
                if (!$orig || !$prev) continue;
                if (!str_starts_with($orig, 'https://') || !str_starts_with($prev, 'https://')) {
                    continue;
                }
                $messages[] = [
                    'type'              => 'image',
                    'originalContentUrl'=> $orig,
                    'previewImageUrl'   => $prev,
                ];
            }
            if (count($messages) > 0) {
                $allOk = $this->queuePushToUser(
                    $userId,
                    $messages,
                    idempotencyKey: $idempotencyPrefix . '.' . $chunkIdx,
                ) && $allOk;
            }
            $chunkIdx++;
        }
        return $allOk;
    }

    /**
     * Queued variant of pushDownloadLink. Identical Flex content, but the
     * actual HTTP call goes through SendLinePushJob with retry + audit.
     */
    public function queuePushDownloadLink(int $userId, array $order, string $url, array $meta = [], ?string $idempotencyKey = null): bool
    {
        if (!$this->isMessagingEnabled()
            || !$this->umbrellaAllows('line_delivery')
            || !$this->isToggleOn('line_user_push_enabled')
            || !$this->isToggleOn('line_user_push_download')) {
            return false;
        }

        $orderNumber = (string) ($order['order_number'] ?? ('#' . ($order['id'] ?? '')));
        $eventName   = (string) ($meta['event_name']  ?? 'ภาพถ่าย');
        $photoCount  = (int)    ($meta['photo_count'] ?? 0);
        $expiresAt   = $meta['expires_at'] ?? null;

        $rows = [
            $this->flexKvRow('ออเดอร์', $orderNumber),
            $this->flexKvRow('อีเวนต์', $eventName),
        ];
        if ($photoCount > 0)        $rows[] = $this->flexKvRow('จำนวน',  $photoCount . ' รูป');
        if (!empty($expiresAt))     $rows[] = $this->flexKvRow('หมดอายุ', (string) $expiresAt);

        $flex = [
            'type'     => 'flex',
            'altText'  => "📥 รูปภาพ {$orderNumber} พร้อมดาวน์โหลดแล้ว",
            'contents' => [
                'type'   => 'bubble',
                'size'   => 'mega',
                'header' => [
                    'type'   => 'box', 'layout' => 'vertical',
                    'contents' => [[
                        'type'  => 'text',  'text' => '📥 รูปภาพพร้อมดาวน์โหลด',
                        'weight'=> 'bold',  'color'=> '#ffffff',  'size' => 'lg',
                    ]],
                    'backgroundColor' => '#4f46e5',  'paddingAll' => '16px',
                ],
                'body'   => ['type' => 'box', 'layout' => 'vertical', 'spacing' => 'sm', 'contents' => $rows],
                'footer' => ['type' => 'box', 'layout' => 'vertical', 'spacing' => 'sm', 'contents' => [[
                    'type' => 'button', 'style' => 'primary', 'color' => '#4f46e5', 'height' => 'md',
                    'action' => ['type' => 'uri', 'label' => 'ดาวน์โหลดเลย', 'uri' => $url],
                ], [
                    'type'  => 'text', 'text' => 'ลิงก์ปลอดภัย — ใช้ได้เฉพาะคุณเท่านั้น',
                    'size'  => 'xs', 'color' => '#9ca3af', 'align' => 'center', 'margin' => 'sm',
                ]]],
            ],
        ];

        // Try Flex first; the queued job's failure handler doesn't fall
        // back to plain text (would need a sibling job). For the common
        // case where Flex is supported, this is fine. If we need
        // text-only fallback later, add a second queued attempt with a
        // suffixed idempotency key.
        return $this->queuePushToUser($userId, [$flex], idempotencyKey: $idempotencyKey);
    }

    /**
     * One-line preview of a message bundle for the audit row's
     * payload_summary column. We strip HTML / collapse whitespace so
     * the column stays grep-friendly.
     */
    private function summariseMessages(array $messages): string
    {
        $first = $messages[0] ?? [];
        $type  = (string) ($first['type'] ?? 'unknown');
        $body  = match ($type) {
            'text'  => (string) ($first['text'] ?? ''),
            'image' => (string) ($first['originalContentUrl'] ?? ''),
            'flex'  => (string) ($first['altText'] ?? '[flex]'),
            default => "[{$type}]",
        };
        return mb_substr(preg_replace('/\s+/', ' ', $body) ?? '', 0, 500);
    }

    public function pushText(int $userId, string $text): bool
    {
        return $this->pushToUser($userId, [
            ['type' => 'text', 'text' => $text],
        ]);
    }

    /**
     * Push photo images directly to a customer's LINE.
     *
     * Per LINE Messaging API, an image message requires:
     *   - originalContentUrl  (must be HTTPS, jpg/png, ≤10 MB, ≤4096×4096)
     *   - previewImageUrl     (must be HTTPS, jpg/png, ≤1 MB, ≤240×240)
     * Up to 5 messages per push call. We chunk into 5-image groups
     * automatically and send sequentially.
     *
     * Used after a buyer pays for an order — we push the watermark-
     * preview-or-thumbnail of each purchased photo so they have an
     * instant in-LINE gallery, plus a final text message with the
     * download link for full-resolution originals.
     *
     * Returns true if every chunk pushed successfully. Best-effort:
     * failures are logged.
     *
     * @param array<int, array{original_url:string, preview_url:string}> $images
     */
    public function pushPhotos(int $userId, array $images, ?string $caption = null): bool
    {
        if (!$this->isMessagingEnabled() || !$this->umbrellaAllows('line_delivery') || !$this->isToggleOn('line_user_push_enabled')) {
            return false;
        }
        $lineUserId = $this->getLineUserId($userId);
        if (!$lineUserId) {
            return false;
        }

        // Cap at 100 images per delivery — anything bigger should use
        // email or web download anyway. Avoids unbounded API calls.
        $images = array_slice($images, 0, 100);

        $allOk = true;

        // Send caption first (if present) so the buyer knows what's coming.
        if ($caption) {
            $this->sendPush($lineUserId, [
                ['type' => 'text', 'text' => $caption],
            ]);
        }

        // LINE limit: max 5 messages per push API call.
        foreach (array_chunk($images, 5) as $chunk) {
            $messages = [];
            foreach ($chunk as $img) {
                $orig = $img['original_url'] ?? null;
                $prev = $img['preview_url'] ?? $orig;
                if (!$orig || !$prev) continue;
                // LINE requires HTTPS URLs only.
                if (!str_starts_with($orig, 'https://') || !str_starts_with($prev, 'https://')) {
                    continue;
                }
                $messages[] = [
                    'type'              => 'image',
                    'originalContentUrl'=> $orig,
                    'previewImageUrl'   => $prev,
                ];
            }
            if (count($messages) > 0) {
                $ok = $this->sendPush($lineUserId, $messages);
                $allOk = $allOk && $ok;
            }
        }

        return $allOk;
    }

    public function pushOrderApproved(int $userId, array $order): bool
    {
        if (!$this->isMessagingEnabled() || !$this->umbrellaAllows('line_notify_customer') || !$this->isToggleOn('line_user_push_enabled')) {
            return false;
        }

        $orderNumber = $order['order_number'] ?? $order['id'] ?? '-';
        $text = "✅ ออเดอร์ {$orderNumber} ได้รับการอนุมัติแล้ว!";

        return $this->pushToUser($userId, [
            ['type' => 'text', 'text' => $text],
        ]);
    }

    public function pushOrderRejected(int $userId, array $order, string $reason = ''): bool
    {
        if (!$this->isMessagingEnabled() || !$this->umbrellaAllows('line_notify_customer') || !$this->isToggleOn('line_user_push_enabled')) {
            return false;
        }

        $orderNumber = $order['order_number'] ?? $order['id'] ?? '-';
        $text = "❌ ออเดอร์ {$orderNumber} ไม่ผ่านการตรวจสอบ\nเหตุผล: {$reason}";

        return $this->pushToUser($userId, [
            ['type' => 'text', 'text' => $text],
        ]);
    }

    public function pushDownloadReady(int $userId, array $order): bool
    {
        if (!$this->isMessagingEnabled()
            || !$this->umbrellaAllows('line_delivery')
            || !$this->isToggleOn('line_user_push_enabled')
            || !$this->isToggleOn('line_user_push_download')) {
            return false;
        }

        $orderNumber = $order['order_number'] ?? $order['id'] ?? '-';
        $text = "📥 รูปภาพพร้อมดาวน์โหลดแล้ว!\nออเดอร์: {$orderNumber}";

        return $this->pushToUser($userId, [
            ['type' => 'text', 'text' => $text],
        ]);
    }

    /**
     * Rich download-link push used by PhotoDeliveryService.
     *
     * Sends a Flex bubble with order meta + a prominent "ดาวน์โหลด" button so
     * the buyer can tap once and open the order page in their browser. Falls
     * back to a plain-text message if the Flex message fails to send (e.g. old
     * LINE client).
     *
     * `$meta` supports:
     *   - event_name string
     *   - photo_count int
     *   - expires_at  string|null pre-formatted "d/m/Y H:i"
     *   - total       string|null formatted total like "150.00 บาท"
     */
    public function pushDownloadLink(int $userId, array $order, string $url, array $meta = []): bool
    {
        if (!$this->isMessagingEnabled()
            || !$this->umbrellaAllows('line_delivery')
            || !$this->isToggleOn('line_user_push_enabled')
            || !$this->isToggleOn('line_user_push_download')) {
            return false;
        }

        $orderNumber = (string) ($order['order_number'] ?? ('#' . ($order['id'] ?? '')));
        $eventName   = (string) ($meta['event_name']  ?? 'ภาพถ่าย');
        $photoCount  = (int)    ($meta['photo_count'] ?? 0);
        $expiresAt   = $meta['expires_at'] ?? null;

        $rows = [
            $this->flexKvRow('ออเดอร์', $orderNumber),
            $this->flexKvRow('อีเวนต์', $eventName),
        ];
        if ($photoCount > 0) {
            $rows[] = $this->flexKvRow('จำนวน', $photoCount . ' รูป');
        }
        if (!empty($expiresAt)) {
            $rows[] = $this->flexKvRow('หมดอายุ', (string) $expiresAt);
        }

        $flex = [
            'type'     => 'flex',
            'altText'  => "📥 รูปภาพ {$orderNumber} พร้อมดาวน์โหลดแล้ว",
            'contents' => [
                'type' => 'bubble',
                'size' => 'mega',
                'header' => [
                    'type'   => 'box',
                    'layout' => 'vertical',
                    'contents' => [[
                        'type'   => 'text',
                        'text'   => '📥 รูปภาพพร้อมดาวน์โหลด',
                        'weight' => 'bold',
                        'color'  => '#ffffff',
                        'size'   => 'lg',
                    ]],
                    'backgroundColor' => '#4f46e5',
                    'paddingAll'      => '16px',
                ],
                'body' => [
                    'type'     => 'box',
                    'layout'   => 'vertical',
                    'spacing'  => 'sm',
                    'contents' => $rows,
                ],
                'footer' => [
                    'type'     => 'box',
                    'layout'   => 'vertical',
                    'spacing'  => 'sm',
                    'contents' => [[
                        'type'   => 'button',
                        'style'  => 'primary',
                        'color'  => '#4f46e5',
                        'height' => 'md',
                        'action' => [
                            'type'  => 'uri',
                            'label' => 'ดาวน์โหลดเลย',
                            'uri'   => $url,
                        ],
                    ], [
                        'type' => 'text',
                        'text' => 'ลิงก์ปลอดภัย — ใช้ได้เฉพาะคุณเท่านั้น',
                        'size' => 'xs',
                        'color' => '#9ca3af',
                        'align' => 'center',
                        'margin' => 'sm',
                    ]],
                ],
            ],
        ];

        // Try Flex first; on failure fall back to plain text so the buyer
        // always gets the URL even if rich content rendering isn't available.
        if ($this->pushToUser($userId, [$flex])) {
            return true;
        }

        $fallback = "📥 รูปภาพของคุณพร้อมดาวน์โหลดแล้ว!\n"
                  . "ออเดอร์: {$orderNumber}\n"
                  . "อีเวนต์: {$eventName}\n"
                  . ($photoCount > 0 ? "จำนวน: {$photoCount} รูป\n" : '')
                  . "\nดาวน์โหลด: {$url}";

        return $this->pushToUser($userId, [
            ['type' => 'text', 'text' => $fallback],
        ]);
    }

    /**
     * Helper: build a "label : value" row for Flex body.
     * Keeps bubble content compact & readable on small screens.
     */
    private function flexKvRow(string $label, string $value): array
    {
        return [
            'type'     => 'box',
            'layout'   => 'baseline',
            'spacing'  => 'sm',
            'contents' => [
                ['type' => 'text', 'text' => $label, 'size' => 'sm', 'color' => '#6b7280', 'flex' => 2],
                ['type' => 'text', 'text' => $value, 'size' => 'sm', 'color' => '#111827', 'flex' => 5, 'wrap' => true],
            ],
        ];
    }

    public function pushPayoutCompleted(int $userId, array $payout): bool
    {
        // Photographer-facing payout notification — gated by line_notify_admin
        // (it's a platform-internal notification, not a buyer-facing one).
        if (!$this->isMessagingEnabled()
            || !$this->umbrellaAllows('line_notify_admin')
            || !$this->isToggleOn('line_user_push_enabled')
            || !$this->isToggleOn('line_user_push_payout')) {
            return false;
        }

        $amount = $payout['amount'] ?? 0;
        $text = "💰 เงินถูกโอนเข้าบัญชีแล้ว!\nจำนวน: {$amount} บาท";

        return $this->pushToUser($userId, [
            ['type' => 'text', 'text' => $text],
        ]);
    }

    /**
     * Push a structured LifecycleMessage — single source of truth wording
     * for every photographer billing/plan/usage event.
     *
     * Same shape as pushPayoutMessage: flex bubble first (rich UI), text
     * fallback second so the notification preview shows canonical short
     * body even before the chat opens.
     *
     * Honours `line_user_push_payout` toggle for now (we share the same
     * preference key so admins can mute photographer billing pushes
     * globally with one switch). Future: split per kind if photographers
     * ask to mute "expiring soon" but keep "renewal failed".
     */
    public function pushLifecycleMessage(int $userId, \App\Services\Notifications\LifecycleMessage $message): bool
    {
        if (!$this->isMessagingEnabled()
            || !$this->umbrellaAllows('line_lifecycle')
            || !$this->isToggleOn('line_user_push_enabled')
            || !$this->isToggleOn('line_user_push_payout')) {
            return false;
        }

        return $this->pushToUser($userId, [
            [
                'type'     => 'flex',
                'altText'  => $message->shortBody,
                'contents' => $message->flexBubble,
            ],
            [
                'type' => 'text',
                'text' => $message->plainText(),
            ],
        ]);
    }

    /**
     * Push a structured PayoutMessage — single source of truth wording.
     *
     * Sends BOTH a flex bubble (rich UI) and a text fallback. LINE clients
     * render the flex when supported and only fall through to the text
     * when the flex parser fails (rare, but happens on outdated apps).
     * Sending both means we never end up showing nothing — the user
     * always sees the canonical numbers + CTA.
     *
     * Honours the same toggles as `pushPayoutCompleted` so admins can
     * still mute payout notifications globally without code changes.
     */
    public function pushPayoutMessage(int $userId, \App\Services\Notifications\PayoutMessage $message): bool
    {
        if (!$this->isMessagingEnabled()
            || !$this->umbrellaAllows('line_notify_admin')
            || !$this->isToggleOn('line_user_push_enabled')
            || !$this->isToggleOn('line_user_push_payout')) {
            return false;
        }

        // Two-message envelope: flex first, plain-text fallback second.
        // LINE shows them in order; the text serves as a notification-bar
        // preview (notification UIs can't render flex), so the user sees
        // the canonical short body even before opening the chat.
        $messages = [
            [
                'type'    => 'flex',
                'altText' => $message->shortBody,
                'contents' => $message->flexBubble,
            ],
            [
                'type' => 'text',
                'text' => $message->plainText(),
            ],
        ];

        return $this->pushToUser($userId, $messages);
    }

    public function broadcastNewEvent(array $event): bool
    {
        if (!$this->isMessagingEnabled()
            || !$this->umbrellaAllows('line_broadcast')
            || !$this->isToggleOn('line_user_push_enabled')
            || !$this->isToggleOn('line_user_push_events')) {
            return false;
        }

        $name      = $event['name'] ?? '-';
        $shootDate = $event['shoot_date'] ?? '-';
        $text = "📸 อีเวนต์ใหม่!\nชื่อ: {$name}\nวันที่: {$shootDate}";

        return $this->sendBroadcast([
            ['type' => 'text', 'text' => $text],
        ]);
    }

    // -------------------------------------------------------------------------
    // Test methods
    // -------------------------------------------------------------------------

    /**
     * Send a test admin push to verify Messaging API + admin user IDs are set up.
     * Replaces the old testNotify() (LINE Notify is dead since 31 Mar 2025).
     *
     * Method name retained for backward compat with AdminApiController; behaviour
     * upgraded to use Messaging API multicast instead of dead notify-api.line.me.
     */
    public function testNotify(): array
    {
        if (!$this->isMessagingEnabled()) {
            return ['success' => false, 'message' => 'LINE Messaging API is disabled (toggle "line_messaging_enabled")'];
        }

        $settings = $this->getSettings();
        if (empty($settings['line_channel_access_token'])) {
            return ['success' => false, 'message' => 'LINE Channel Access Token is not configured'];
        }

        $userIds = $this->getAdminLineUserIds();
        if (empty($userIds)) {
            return [
                'success' => false,
                'message' => 'ยังไม่ได้ตั้ง Admin LINE User IDs — เพิ่มในหน้าตั้งค่า LINE (line_admin_user_ids)',
            ];
        }

        $result = $this->notifyAdmin('🔔 ทดสอบการแจ้งเตือน LINE Messaging API (admin push) สำเร็จ!');

        return [
            'success' => $result,
            'message' => $result
                ? 'ส่งข้อความทดสอบไปยัง admin ' . count($userIds) . ' คนสำเร็จ'
                : 'ส่งไม่สำเร็จ — ตรวจสอบว่า admin ทุกคน Add OA เป็นเพื่อนแล้ว และดู log',
        ];
    }

    public function testMessaging(int $userId): array
    {
        if (!$this->isMessagingEnabled()) {
            return ['success' => false, 'message' => 'LINE Messaging API is disabled'];
        }

        $lineUserId = $this->getLineUserId($userId);
        if (!$lineUserId) {
            return ['success' => false, 'message' => "User {$userId} has no LINE account linked"];
        }

        $result = $this->sendPush($lineUserId, [
            ['type' => 'text', 'text' => '🔔 ทดสอบการแจ้งเตือน LINE Messaging API สำเร็จ!'],
        ]);

        return [
            'success' => $result,
            'message' => $result
                ? "LINE Messaging test sent to user {$userId}"
                : "Failed to send LINE Messaging test to user {$userId}",
        ];
    }

    /**
     * Test LINE Messaging API by sending directly to a raw LINE User ID (U...).
     *
     * Use case: admin has Messaging API credentials but no user has linked LINE
     * via OAuth yet (empty auth_social_logins). They paste their own U... ID
     * obtained from LINE webhook logs or Official Account Manager and verify
     * push delivery without needing the OAuth linking step.
     *
     * Note: the LINE account must have added the Bot as a friend for push
     * to succeed — otherwise LINE returns 400 "The user hasn't added the LINE
     * Official Account as a friend".
     */
    public function testMessagingDirect(string $lineUserId): array
    {
        if (!$this->isMessagingEnabled()) {
            return ['success' => false, 'message' => 'LINE Messaging API is disabled'];
        }

        $lineUserId = trim($lineUserId);
        if ($lineUserId === '' || !preg_match('/^U[0-9a-f]{32}$/i', $lineUserId)) {
            return [
                'success' => false,
                'message' => 'รูปแบบ LINE User ID ไม่ถูกต้อง (ต้องเริ่มด้วย U แล้วตามด้วย hex 32 ตัว)',
            ];
        }

        $result = $this->sendPush($lineUserId, [
            ['type' => 'text', 'text' => '🔔 ทดสอบ LINE Messaging API (direct) สำเร็จ!'],
        ]);

        return [
            'success' => $result,
            'message' => $result
                ? "ส่งข้อความทดสอบไปยัง {$lineUserId} สำเร็จ"
                : "ส่งไม่สำเร็จ — ตรวจสอบ: (1) LINE User ID ถูกต้อง (2) บัญชีนี้ Add Bot เป็นเพื่อนแล้ว (3) Channel Access Token ถูกต้อง — ดู log ที่ storage/logs/laravel.log",
        ];
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Multicast Messaging API delivery to a list of LINE userIds (e.g. admins).
     * LINE caps multicast at 500 recipients per call — we chunk transparently.
     * Best-effort: returns true only if every chunk succeeded.
     */
    private function sendMulticast(array $userIds, array $messages): bool
    {
        $token = $this->getSettings()['line_channel_access_token'] ?? '';
        if ($token === '' || empty($userIds)) return false;

        // Audit the multicast: one row per recipient. Without this, the
        // dashboard's "did admin X get the alert?" query has to look at
        // logs (= grep). Per-recipient rows let us aggregate failure
        // rate per user without parsing free-form text.
        $logger  = app(\App\Services\Line\LineDeliveryLogger::class);
        $summary = $this->summariseMessages($messages);
        $audits  = [];
        foreach ($userIds as $uid) {
            // Multicasts are typically admin alerts and don't need
            // request-level idempotency (caller-side dedup happens
            // upstream in the observer layer). Skip the idempotency_key
            // so the row always inserts.
            $audits[$uid] = $logger->begin(
                userId:         null,
                lineUserId:     $uid,
                deliveryType:   'multicast',
                messageType:    (string) ($messages[0]['type'] ?? 'text'),
                payloadSummary: $summary,
                payloadJson:    $messages,
            );
        }

        $allOk = true;
        foreach (array_chunk(array_values($userIds), 500) as $chunk) {
            try {
                $response = Http::withToken($token)
                    ->timeout(10)
                    ->post('https://api.line.me/v2/bot/message/multicast', [
                        'to'       => $chunk,
                        'messages' => $messages,
                    ]);

                if (!$response->successful()) {
                    Log::channel('single')->error('LINE Messaging multicast failed', [
                        'status' => $response->status(),
                        'body'   => $response->body(),
                        'count'  => count($chunk),
                    ]);
                    $allOk = false;
                    foreach ($chunk as $uid) {
                        if (isset($audits[$uid])) {
                            $logger->markFailed($audits[$uid]['id'], $response->status(),
                                substr($response->body(), 0, 500));
                        }
                    }
                } else {
                    foreach ($chunk as $uid) {
                        if (isset($audits[$uid])) {
                            $logger->markSent($audits[$uid]['id'], $response->status());
                        }
                    }
                }
            } catch (\Throwable $e) {
                Log::channel('single')->error('LINE Messaging multicast exception', [
                    'error' => $e->getMessage(),
                    'count' => count($chunk),
                ]);
                $allOk = false;
                foreach ($chunk as $uid) {
                    if (isset($audits[$uid])) {
                        $logger->markFailed($audits[$uid]['id'], null, $e->getMessage());
                    }
                }
            }
        }
        return $allOk;
    }

    /**
     * Parse comma/space/semicolon-separated LINE userIds from settings into a
     * validated list. Each id must match the U[0-9a-f]{32} format LINE uses.
     */
    private function getAdminLineUserIds(): array
    {
        $raw = (string) ($this->getSettings()['line_admin_user_ids'] ?? '');
        if ($raw === '') return [];

        $ids = preg_split('/[\s,;]+/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $valid = [];
        foreach ($ids as $id) {
            $id = trim($id);
            if (preg_match('/^U[0-9a-f]{32}$/i', $id)) {
                $valid[] = $id;
            }
        }
        // De-duplicate: same admin shouldn't get the same alert twice.
        return array_values(array_unique($valid));
    }

    private function sendPush(string $lineUserId, array $messages, ?string $idempotencyKey = null): bool
    {
        // Open the audit row regardless of the synchronous outcome — even
        // a "skipped" send (invalid id, missing token, dead user) is
        // worth recording so support can answer "was anything attempted?"
        $logger = app(\App\Services\Line\LineDeliveryLogger::class);
        $audit  = null;

        try {
            // Validate LINE user ID format up front — LINE user IDs are
            // 33 chars: 'U' followed by 32 hex chars. Bad IDs produce a
            // noisy 400 from LINE that pollutes error logs. Reject early.
            if (!$this->isValidLineUserId($lineUserId)) {
                Log::channel('single')->warning('LINE Messaging: invalid user ID format, skipping', [
                    'user_id_length' => strlen($lineUserId),
                ]);
                return false;
            }

            $settings = $this->getSettings();
            $token    = $settings['line_channel_access_token'] ?? '';

            if (empty($token)) {
                Log::channel('single')->error('LINE Messaging: channel access token is not configured');
                return false;
            }

            // Begin audit row — caller can pass idempotency_key (e.g.
            // "order.42.delivery") to make a second send a guaranteed
            // no-op via the unique constraint on (line_user_id, key).
            $audit = $logger->begin(
                userId:         null,
                lineUserId:     $lineUserId,
                deliveryType:   'push',
                messageType:    (string) ($messages[0]['type'] ?? 'text'),
                payloadSummary: $this->summariseMessages($messages),
                payloadJson:    $messages,
                idempotencyKey: $idempotencyKey,
            );
            // If a previous send with the same idempotency key already
            // succeeded, return true without firing another HTTP call.
            if ($audit['duplicate']) {
                $existing = \DB::table('line_deliveries')->where('id', $audit['id'])->first();
                if ($existing && $existing->status === 'sent') {
                    return true;
                }
            }

            $response = Http::withToken($token)
                ->timeout(10)
                ->post('https://api.line.me/v2/bot/message/push', [
                    'to'       => $lineUserId,
                    'messages' => $messages,
                ]);

            if (!$response->successful()) {
                $status = $response->status();
                $body   = $response->body();

                // 400 "Failed to send messages" typically means the user has
                // not added the LINE OA as a friend (or has blocked it). Log
                // at warning level (not error) since it's user-correctable,
                // and optionally detach the dead user_id so we don't keep
                // retrying on every notification.
                if ($status === 400 && str_contains($body, 'Failed to send messages')) {
                    Log::channel('single')->warning('LINE Messaging push skipped — user has not followed OA', [
                        'line_user_id' => substr($lineUserId, 0, 8) . '…',
                    ]);
                    $this->detachDeadLineUser($lineUserId);
                    if ($audit) $logger->markSkipped($audit['id'], 'recipient has not added OA');
                    return false;
                }

                Log::channel('single')->error('LINE Messaging push failed', [
                    'status' => $status,
                    'body'   => $body,
                ]);
                if ($audit) $logger->markFailed($audit['id'], $status, substr($body, 0, 500));
                return false;
            }

            if ($audit) $logger->markSent($audit['id'], $response->status());
            return true;
        } catch (\Throwable $e) {
            Log::channel('single')->error('LINE Messaging push exception', ['error' => $e->getMessage()]);
            if ($audit) $logger->markFailed($audit['id'], null, $e->getMessage());
            return false;
        }
    }

    /**
     * LINE user IDs must match /^U[0-9a-f]{32}$/i.
     */
    private function isValidLineUserId(string $id): bool
    {
        return (bool) preg_match('/^U[0-9a-f]{32}$/i', $id);
    }

    /**
     * When LINE returns 400 for a user ID, the user has either blocked the OA
     * or never added it as a friend. Clear the stale line_user_id from the
     * users table so subsequent notifications don't re-try the dead address.
     * Only touches the users table — social_logins is kept intact so the user
     * can still re-link LINE later.
     */
    private function detachDeadLineUser(string $lineUserId): void
    {
        try {
            if (\Schema::hasTable('users') && \Schema::hasColumn('users', 'line_user_id')) {
                \DB::table('users')
                    ->where('line_user_id', $lineUserId)
                    ->update(['line_user_id' => null]);
            }
        } catch (\Throwable $e) {
            // Best-effort cleanup — never propagate failure from a log side-effect.
        }
    }

    private function sendBroadcast(array $messages): bool
    {
        try {
            $settings = $this->getSettings();
            $token    = $settings['line_channel_access_token'] ?? '';

            if (empty($token)) {
                Log::channel('single')->error('LINE Messaging: channel access token is not configured');
                return false;
            }

            $response = Http::withToken($token)
                ->post('https://api.line.me/v2/bot/message/broadcast', [
                    'messages' => $messages,
                ]);

            if (!$response->successful()) {
                Log::channel('single')->error('LINE Messaging broadcast failed', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::channel('single')->error('LINE Messaging broadcast exception', ['error' => $e->getMessage()]);
            return false;
        }
    }

    private function getLineUserId(int $userId): ?string
    {
        $row = DB::table('auth_social_logins')
            ->where('provider', 'line')
            ->where('user_id', $userId)
            ->first();

        return $row?->provider_id ?? null;
    }

    private function isToggleOn(string $key, string $default = '1'): bool
    {
        $settings = $this->getSettings();
        $value    = $settings[$key] ?? $default;
        return in_array($value, ['1', 'true', true, 1], true);
    }

    private function getSettings(): array
    {
        if ($this->cachedSettings !== null) {
            return $this->cachedSettings;
        }

        $keys = [
            'line_messaging_enabled',
            'line_channel_access_token',
            'line_admin_user_ids',          // comma-separated U... ids for admin alerts (replaces dead Notify token)
            'line_admin_notify_orders',
            'line_admin_notify_events',
            'line_admin_notify_registration',
            'line_admin_notify_payouts',
            'line_admin_notify_contact',
            'line_admin_notify_cancellation',
            'line_user_push_enabled',
            'line_user_push_download',
            'line_user_push_events',
            'line_user_push_payout',
        ];

        $this->cachedSettings = [];
        foreach ($keys as $key) {
            $this->cachedSettings[$key] = AppSetting::get($key, '1');
        }

        return $this->cachedSettings;
    }
}

<?php

namespace App\Services;

use App\Models\NotificationRoutingRule;
use Illuminate\Support\Facades\Cache;

/**
 * NotificationRouter — single source of truth for "should this notification
 * fire?" decisions across the app.
 *
 * Usage in code that fires notifications:
 *
 *   $router = app(\App\Services\NotificationRouter::class);
 *   if ($router->shouldNotify('order.created', 'customer', 'email')) {
 *       Mail::to($order->user)->send(new OrderCreatedMail($order));
 *   }
 *   if ($router->shouldNotify('order.created', 'photographer', 'line')) {
 *       app(LineNotifyService::class)->pushOrderCreated($order);
 *   }
 *
 * Defaults:
 *   - When NO rule exists for (event_key, audience), shouldNotify returns
 *     TRUE for in_app and FALSE for everything else. This keeps the
 *     existing app behaviour intact when a new event is introduced —
 *     admin sees it in the UI, opts in to channels they want.
 *   - When the row exists but is_enabled=false, ALL channels return false
 *     (master kill-switch).
 *
 * The full table is cached in memory for the request lifecycle (~30 rules,
 * <1KB) so a route handler that fires multiple notifications doesn't hit
 * the DB N times.
 */
class NotificationRouter
{
    /**
     * Catalogue of every notification event the app fires.
     * Each entry has Thai label + which audiences are RELEVANT (admin can
     * still disable all channels but we never ask "does customer care
     * about admin.user_signed_up?").
     *
     * @var array<string, array{label: string, group: string, audiences: string[]}>
     */
    public static function catalogue(): array
    {
        return [
            // ── Order lifecycle ────────────────────────────────────────
            'order.created' => [
                'label'     => 'ลูกค้าสั่งซื้อใหม่',
                'group'     => 'ออเดอร์',
                'audiences' => ['customer', 'photographer', 'admin'],
            ],
            'order.completed' => [
                'label'     => 'ออเดอร์เสร็จสิ้น',
                'group'     => 'ออเดอร์',
                'audiences' => ['customer', 'photographer', 'admin'],
            ],
            'order.cancelled' => [
                'label'     => 'ออเดอร์ถูกยกเลิก',
                'group'     => 'ออเดอร์',
                'audiences' => ['customer', 'photographer', 'admin'],
            ],
            'order.expired' => [
                'label'     => 'ออเดอร์หมดเวลาชำระ',
                'group'     => 'ออเดอร์',
                'audiences' => ['customer', 'admin'],
            ],

            // ── Payment / slip ─────────────────────────────────────────
            'slip.uploaded' => [
                'label'     => 'ลูกค้าอัปโหลดสลิป',
                'group'     => 'การชำระเงิน',
                'audiences' => ['customer', 'admin'],
            ],
            'slip.approved' => [
                'label'     => 'สลิปอนุมัติแล้ว',
                'group'     => 'การชำระเงิน',
                'audiences' => ['customer', 'photographer', 'admin'],
            ],
            'slip.rejected' => [
                'label'     => 'สลิปถูกปฏิเสธ',
                'group'     => 'การชำระเงิน',
                'audiences' => ['customer', 'admin'],
            ],
            'payment.success' => [
                'label'     => 'ชำระเงินสำเร็จ',
                'group'     => 'การชำระเงิน',
                'audiences' => ['customer', 'photographer', 'admin'],
            ],
            'payment.failed' => [
                'label'     => 'ชำระเงินล้มเหลว',
                'group'     => 'การชำระเงิน',
                'audiences' => ['customer', 'admin'],
            ],

            // ── Refunds ────────────────────────────────────────────────
            'refund.requested' => [
                'label'     => 'มีคำขอคืนเงินใหม่',
                'group'     => 'คืนเงิน',
                'audiences' => ['customer', 'admin'],
            ],
            'refund.approved' => [
                'label'     => 'คืนเงินอนุมัติ',
                'group'     => 'คืนเงิน',
                'audiences' => ['customer', 'photographer', 'admin'],
            ],
            'refund.rejected' => [
                'label'     => 'คืนเงินถูกปฏิเสธ',
                'group'     => 'คืนเงิน',
                'audiences' => ['customer', 'admin'],
            ],

            // ── Photographer / event lifecycle ─────────────────────────
            'photographer.signup' => [
                'label'     => 'ช่างภาพสมัครใหม่',
                'group'     => 'ช่างภาพ',
                'audiences' => ['photographer', 'admin'],
            ],
            'photographer.approved' => [
                'label'     => 'ช่างภาพได้รับการอนุมัติ',
                'group'     => 'ช่างภาพ',
                'audiences' => ['photographer', 'admin'],
            ],
            'event.published' => [
                'label'     => 'อีเวนต์ถูกเผยแพร่',
                'group'     => 'อีเวนต์',
                'audiences' => ['photographer', 'admin'],
            ],
            'photo.uploaded' => [
                'label'     => 'อัปโหลดภาพใหม่',
                'group'     => 'อีเวนต์',
                'audiences' => ['photographer', 'admin'],
            ],

            // ── Payouts ────────────────────────────────────────────────
            'payout.scheduled' => [
                'label'     => 'รายการจ่ายค่าตอบแทนถูกกำหนดวันจ่าย',
                'group'     => 'การจ่ายช่างภาพ',
                'audiences' => ['photographer', 'admin'],
            ],
            'payout.paid' => [
                'label'     => 'จ่ายค่าตอบแทนช่างภาพแล้ว',
                'group'     => 'การจ่ายช่างภาพ',
                'audiences' => ['photographer', 'admin'],
            ],
            'payout.failed' => [
                'label'     => 'การจ่ายค่าตอบแทนล้มเหลว',
                'group'     => 'การจ่ายช่างภาพ',
                'audiences' => ['photographer', 'admin'],
            ],
        ];
    }

    /**
     * Should this (event, audience, channel) trigger a notification?
     *
     * Decision tree:
     *   1. Look up rule row by (event, audience)
     *   2. If row missing → fall back to defaults (in_app=true, others=false)
     *   3. If row exists but is_enabled=false → false for ALL channels
     *   4. Otherwise → return that channel's column value
     */
    public function shouldNotify(string $eventKey, string $audience, string $channel): bool
    {
        if (!in_array($audience, NotificationRoutingRule::AUDIENCES, true)) {
            return false;
        }
        if (!in_array($channel, NotificationRoutingRule::CHANNELS, true)) {
            return false;
        }

        $rule = $this->ruleFor($eventKey, $audience);

        if ($rule === null) {
            // No row → safe defaults: in_app on, everything else off.
            return $channel === 'in_app';
        }

        if (!$rule['is_enabled']) {
            return false;
        }

        return (bool) ($rule[$channel . '_enabled'] ?? false);
    }

    /**
     * Get all channel toggles for (event, audience). Used by the admin
     * UI to render the matrix; also useful for code that wants to know
     * "all channels for this audience" in one shot.
     */
    public function channelsFor(string $eventKey, string $audience): array
    {
        $rule = $this->ruleFor($eventKey, $audience);
        if ($rule === null) {
            return [
                'in_app' => true, 'email' => false, 'line' => false,
                'sms'    => false, 'push'  => false, 'enabled' => true,
            ];
        }
        return [
            'in_app'  => (bool) $rule['in_app_enabled'],
            'email'   => (bool) $rule['email_enabled'],
            'line'    => (bool) $rule['line_enabled'],
            'sms'     => (bool) $rule['sms_enabled'],
            'push'    => (bool) $rule['push_enabled'],
            'enabled' => (bool) $rule['is_enabled'],
        ];
    }

    /**
     * Bust the in-memory + Cache:: copies so a freshly-saved rule takes
     * effect on the very next request without a server restart. Called
     * by the admin controller after every UPDATE.
     */
    public function flush(): void
    {
        Cache::forget('notification_routing_rules:all');
    }

    /**
     * Pull all rules ONCE per request, normalised into a [event][audience]
     * map. Cached for 5 min in case multiple workers fire at once.
     */
    private function rulesMap(): array
    {
        return Cache::remember('notification_routing_rules:all', 300, function () {
            try {
                $rows = NotificationRoutingRule::all()->toArray();
            } catch (\Throwable $e) {
                // DB not migrated yet (fresh install) — caller falls back
                // to defaults via ruleFor() returning null.
                return [];
            }
            $map = [];
            foreach ($rows as $r) {
                $map[$r['event_key']][$r['audience']] = $r;
            }
            return $map;
        });
    }

    private function ruleFor(string $eventKey, string $audience): ?array
    {
        return $this->rulesMap()[$eventKey][$audience] ?? null;
    }
}

<?php

namespace App\Services;

use App\Models\Event;
use App\Models\AdminNotification;
use App\Models\UserNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Notifications for the event sales-close lifecycle.
 *
 * One service so manual close + auto-cron-close + reopen all push the
 * SAME message format on every channel — without this, photographers
 * would compose ad-hoc messages in each call site and we'd drift across
 * LINE / in-app / email wording (the same kind of drift that motivated
 * Notifications/PayoutMessageFormatter).
 *
 * Three audiences per close event:
 *
 *   1. Buyers who already paid for this event
 *      → "ปิดการขายแล้ว แต่คุณดาวน์โหลดได้ตลอด"
 *      Reassures them their purchase is safe; deflects support tickets.
 *
 *   2. The photographer themselves
 *      → "ปิดการขายอีเวนต์ X เรียบร้อย — N ออเดอร์ ฿M รวม"
 *      In-app bell summary so they have a paper trail.
 *
 *   3. Admin (audit / oversight)
 *      → AdminNotification "ช่างภาพปิดการขายอีเวนต์"
 *      Lets ops spot unusual patterns (e.g. mass close before refunds).
 *
 * All channels are best-effort — a failure on one MUST NOT block the
 * status flip. The status flip is the source of truth; notifications
 * are advisory.
 */
class EventLifecycleNotifier
{
    public function __construct(private LineNotifyService $line) {}

    /**
     * Fire when an event transitions active|published → closed (manual or cron).
     */
    public function notifySaleClosed(Event $event): void
    {
        // ── 1) Notify recent buyers ──────────────────────────────────
        // Pull distinct user_ids who have a paid order for this event.
        // Limit to 200 most recent to cap LINE API spend on huge events.
        $buyerIds = DB::table('orders')
            ->where('event_id', $event->id)
            ->whereIn('status', ['paid', 'completed', 'delivered'])
            ->orderByDesc('id')
            ->limit(200)
            ->pluck('user_id')
            ->unique()
            ->filter()
            ->values();

        foreach ($buyerIds as $userId) {
            // In-app bell — always cheap, always fires.
            try {
                UserNotification::notify(
                    (int) $userId,
                    'event_closed',
                    '🎬 อีเวนต์ "' . $event->name . '" ปิดการขายแล้ว',
                    'รูปที่คุณซื้อไว้ยังดาวน์โหลดได้ตลอด — เข้าหน้า "ดาวน์โหลดของฉัน"',
                    'my-purchases',
                    (string) $event->id
                );
            } catch (\Throwable $e) {
                Log::debug('event_close.user_inapp_failed', [
                    'event_id' => $event->id, 'user_id' => $userId,
                    'error' => $e->getMessage(),
                ]);
            }

            // LINE push — best-effort. Skips silently if user has no
            // LINE binding or the umbrella line_notify feature is off.
            try {
                $this->line->pushText(
                    (int) $userId,
                    "🎬 อีเวนต์ \"{$event->name}\" ปิดการขายแล้ว\n"
                    . "รูปที่คุณซื้อไว้ยังดาวน์โหลดได้ตลอด — เข้าหน้า \"ดาวน์โหลดของฉัน\""
                );
            } catch (\Throwable $e) {
                Log::debug('event_close.user_line_failed', [
                    'event_id' => $event->id, 'user_id' => $userId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // ── 2) Notify the photographer (in-app receipt) ──────────────
        try {
            $stats = DB::table('orders')
                ->where('event_id', $event->id)
                ->whereIn('status', ['paid', 'completed', 'delivered'])
                ->selectRaw('COUNT(*) AS cnt, COALESCE(SUM(total), 0) AS rev')
                ->first();

            $orderCount = (int) ($stats->cnt ?? 0);
            $revenue    = (float) ($stats->rev ?? 0);

            UserNotification::notify(
                (int) $event->photographer_id,
                'event_closed_self',
                '✓ ปิดการขายอีเวนต์ "' . $event->name . '" เรียบร้อย',
                $orderCount > 0
                    ? "สรุป: {$orderCount} ออเดอร์ · ฿" . number_format($revenue, 2)
                    : 'อีเวนต์นี้ไม่มียอดขาย',
                'photographer/events',
                (string) $event->id
            );
        } catch (\Throwable $e) {
            Log::debug('event_close.photographer_inapp_failed', [
                'event_id' => $event->id, 'error' => $e->getMessage(),
            ]);
        }

        // ── 3) Admin bell (oversight) ────────────────────────────────
        try {
            AdminNotification::notify(
                'event.closed',
                '🎬 ช่างภาพปิดการขายอีเวนต์',
                "Event #{$event->id} \"{$event->name}\" — ผู้ซื้อ "
                    . count($buyerIds) . ' ราย ได้รับแจ้งเตือนแล้ว',
                "admin/events/{$event->id}",
                (string) $event->id
            );
        } catch (\Throwable $e) {
            Log::debug('event_close.admin_inapp_failed', [
                'event_id' => $event->id, 'error' => $e->getMessage(),
            ]);
        }

        Log::info('event.sale_closed_notified', [
            'event_id'   => $event->id,
            'buyer_count'=> count($buyerIds),
        ]);
    }

    /**
     * Fire when an event approaches its scheduled sales_ends_at — the
     * AutoCloseEventsCommand cron pings this 24h before so photographers
     * can extend or cancel the close if they change their mind.
     */
    public function notifyAutoCloseImminent(Event $event): void
    {
        if (!$event->sales_ends_at) return;

        try {
            UserNotification::notify(
                (int) $event->photographer_id,
                'event_closing_soon',
                '⏰ อีเวนต์ "' . $event->name . '" จะปิดการขายเร็ว ๆ นี้',
                'ตั้งเวลาไว้ ' . $event->sales_ends_at->format('d/m/Y H:i')
                    . ' — แก้ไขได้ที่หน้าจัดการอีเวนต์',
                'photographer/events/' . $event->id . '/edit',
                (string) $event->id
            );
        } catch (\Throwable $e) {
            Log::debug('event_close.imminent_notify_failed', [
                'event_id' => $event->id, 'error' => $e->getMessage(),
            ]);
        }
    }
}

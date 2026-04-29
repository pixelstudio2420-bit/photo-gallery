<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserNotification extends Model
{
    protected $table = 'user_notifications';
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'type', 'title', 'message', 'is_read',
        'action_url', 'read_at', 'ref_id',
    ];

    protected $casts = [
        'is_read'    => 'boolean',
        'created_at' => 'datetime',
        'read_at'    => 'datetime',
    ];

    /**
     * Always surface the normalized action_href in JSON responses so the
     * navbar / notifications UI can render safely without per-client guards.
     */
    protected $appends = ['action_href'];

    // ─── Relations ───
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Normalize action_url for rendering in a browser.
     *
     * Historically `action_url` was stored inconsistently:
     *   - as a relative path like "orders/5" (current convention)
     *   - as a full URL like "http://host/orders/5" (legacy bug — the
     *     Admin\PaymentController used to call route() which returns absolute
     *     URLs, causing "/http://host/orders/5" → 404 when prefixed by "/" in
     *     the navbar template).
     *
     * This accessor normalises to a relative path. Absolute URLs (and any
     * other dangerous scheme like javascript: / data:) are STRIPPED — we
     * never trust an arbitrary URL stored in the database. This mirrors
     * the admin-side `AdminNotification::sanitiseLink` defence.
     *
     * Returns '#' for empty / unsafe so href="#" is always valid.
     */
    public function getActionHrefAttribute(): string
    {
        $url = trim((string) ($this->action_url ?? ''));
        if ($url === '') return '#';

        $clean = static::sanitiseActionUrl($url);
        if ($clean === null) return '#';

        // Render as absolute path so the browser doesn't resolve relative
        // to whatever page the bell is currently on.
        return '/' . ltrim($clean, '/');
    }

    /**
     * Strip absolute URLs and dangerous schemes from a notification
     * action URL. Mirrors AdminNotification::sanitiseLink — see
     * `app/Models/AdminNotification.php` for rationale.
     *
     * Blocks:
     *   - http://...   https://...    (open-redirect surface)
     *   - //evil.com   (protocol-relative URLs)
     *   - javascript:  data:  vbscript:  file:  etc. (any scheme)
     *
     * Accepts:
     *   - orders/5
     *   - /orders/5            (leading slash stripped)
     *   - photographer/earnings?tab=foo
     */
    public static function sanitiseActionUrl(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }
        $url = trim($url);
        if (preg_match('#^(https?:)?//#i', $url)) {
            return null;
        }
        if (preg_match('#^[a-z][a-z0-9+.\-]*:#i', $url)) {
            return null;
        }
        return ltrim($url, '/');
    }

    // ─── Scopes ───
    public function scopeUnread($q)
    {
        return $q->where('is_read', false);
    }

    public function scopeForUser($q, int $userId)
    {
        return $q->where('user_id', $userId);
    }

    public function scopeByType($q, string $type)
    {
        return $q->where('type', $type);
    }

    public function scopeRecent($q, int $days = 30)
    {
        return $q->where('created_at', '>=', now()->subDays($days));
    }

    /* ═════════════════════════════════════════════════════════════════
     *  STATIC HELPERS — Push notifications for common events
     * ═════════════════════════════════════════════════════════════════ */

    /**
     * Generic notification helper — respects user preferences if set.
     *
     * Now sanitises `actionUrl` and accepts an optional `refId` so
     * batch-dismiss patterns (markReadByRef) work after admin actions.
     */
    public static function notify(
        int $userId,
        string $type,
        string $title,
        string $message = '',
        ?string $actionUrl = null,
        ?string $refId = null
    ): self {
        // Respect user preferences if preference system exists
        if (!self::isTypeEnabled($userId, $type)) {
            // Still create the record for history, but user opted out — no badge/email triggers
            // For now, skip creation entirely to keep the DB clean
            return new self([
                'user_id' => $userId, 'type' => $type, 'title' => $title, 'message' => $message,
            ]);
        }

        return static::create([
            'user_id'    => $userId,
            'type'       => $type,
            'title'      => $title,
            'message'    => $message,
            'action_url' => static::sanitiseActionUrl($actionUrl),
            'ref_id'     => $refId,
            'is_read'    => false,
            'created_at' => now(),
        ]);
    }

    /**
     * Idempotent variant of notify() — won't create a duplicate row if
     * a notification of the same (user_id, type, ref_id) already exists.
     *
     * Use this from any code path that can be retried (webhooks, queue
     * jobs, observer-style hooks). Returns the existing row when it
     * finds one, or a freshly created row otherwise.
     */
    public static function notifyOnce(
        int $userId,
        string $type,
        string $title,
        string $message = '',
        ?string $actionUrl = null,
        ?string $refId = null
    ): self {
        if ($refId !== null && $refId !== '') {
            $existing = static::where('user_id', $userId)
                ->where('type', $type)
                ->where('ref_id', $refId)
                ->first();
            if ($existing) {
                return $existing;
            }
        }
        return static::notify($userId, $type, $title, $message, $actionUrl, $refId);
    }

    /**
     * Mark a group of a user's notifications as read by type and/or ref_id.
     *
     * Mirrors AdminNotification::markReadByRef. Lets the slip-approval and
     * order-paid flows dismiss the buyer's stale "อัปโหลดสลิปสำเร็จ"
     * notification when the underlying business state changes — without it
     * the bell counter just kept counting forever.
     *
     * Returns the count of rows affected.
     */
    public static function markReadByRef(int $userId, $type, string $refId): int
    {
        $q = static::where('user_id', $userId)
            ->where('ref_id', $refId)
            ->where('is_read', false);
        if (is_array($type)) {
            $q->whereIn('type', $type);
        } else {
            $q->where('type', $type);
        }
        return $q->update(['is_read' => true, 'read_at' => now()]);
    }

    /**
     * Order-related notifications.
     */
    public static function orderCreated(int $userId, $order): self
    {
        $num = $order->order_number ?? "#{$order->id}";
        return static::notifyOnce(
            $userId,
            'order',
            'สร้างคำสั่งซื้อสำเร็จ',
            "คำสั่งซื้อ {$num} ยอด ฿" . number_format($order->total, 0) . " รอการชำระเงิน",
            "payment/checkout/{$order->id}",
            (string) $order->id
        );
    }

    public static function paymentApproved(int $userId, $order): self
    {
        $num = $order->order_number ?? "#{$order->id}";
        return static::notifyOnce(
            $userId,
            'payment_approved',
            'การชำระเงินได้รับการอนุมัติ',
            "คำสั่งซื้อ {$num} ได้รับการยืนยันการชำระเงินแล้ว คุณสามารถดาวน์โหลดรูปภาพได้ทันที",
            "orders/{$order->id}",
            (string) $order->id
        );
    }

    public static function paymentRejected(int $userId, $order, string $reason = ''): self
    {
        $num = $order->order_number ?? "#{$order->id}";
        return static::notify(
            $userId,
            'payment_rejected',
            'การชำระเงินถูกปฏิเสธ',
            "คำสั่งซื้อ {$num} " . ($reason ? "เหตุผล: {$reason} " : '') . "กรุณาอัปโหลดสลิปใหม่",
            "orders/{$order->id}",
            (string) $order->id
        );
    }

    public static function downloadReady(int $userId, $order): self
    {
        $num = $order->order_number ?? "#{$order->id}";
        return static::notifyOnce(
            $userId,
            'download_ready',
            '📸 ภาพพร้อมดาวน์โหลดแล้ว',
            "คำสั่งซื้อ {$num} พร้อมให้ดาวน์โหลดภาพได้ทันที",
            "orders/{$order->id}",
            (string) $order->id
        );
    }

    public static function refundProcessed(int $userId, $order, float $amount, string $status = 'approved'): self
    {
        $num = $order->order_number ?? "#{$order->id}";
        $title = match ($status) {
            'approved' => '✅ คำขอคืนเงินได้รับอนุมัติ',
            'rejected' => '❌ คำขอคืนเงินถูกปฏิเสธ',
            default    => 'คืนเงินเรียบร้อย',
        };
        return static::notifyOnce(
            $userId,
            'refund',
            $title,
            "คำสั่งซื้อ {$num} จำนวนเงิน ฿" . number_format($amount, 2),
            "orders/{$order->id}",
            (string) $order->id
        );
    }

    /**
     * Photographer-specific notifications.
     */
    public static function photographerApproved(int $userId): self
    {
        // Stored without a leading slash — NotificationRenderer prefixes the
        // app base URL at render time. Must match the canonical route name
        // (photographer.dashboard = /photographer), not a guessed path, or
        // an admin renaming the URL later would silently 404 old notices.
        return static::notify(
            $userId,
            'photographer_approved',
            '🎉 บัญชีช่างภาพได้รับการอนุมัติ',
            'คุณสามารถเริ่มต้นสร้างอีเวนต์และอัปโหลดภาพได้แล้ว',
            ltrim(route('photographer.dashboard', [], false), '/')
        );
    }

    public static function photographerRejected(int $userId, string $reason = ''): self
    {
        return static::notify(
            $userId,
            'photographer_rejected',
            'การสมัครช่างภาพไม่ผ่านการพิจารณา',
            $reason ? "เหตุผล: {$reason}" : 'กรุณาปรับปรุงข้อมูลและลองสมัครใหม่',
            null
        );
    }

    public static function photographerSuspended(int $userId, ?string $reason = null): self
    {
        return static::notify(
            $userId,
            'photographer_suspended',
            '🛑 บัญชีช่างภาพถูกระงับ',
            $reason ?: 'กรุณาติดต่อทีมงานเพื่อตรวจสอบ',
            null
        );
    }

    public static function newSale(int $photographerUserId, float $commission, $order): self
    {
        $num = $order->order_number ?? "#{$order->id}";
        return static::notifyOnce(
            $photographerUserId,
            'new_sale',
            '💰 ยอดขายใหม่',
            "คำสั่งซื้อ {$num} ได้รับค่าคอมมิชชั่น ฿" . number_format($commission, 2),
            'photographer/earnings',
            (string) $order->id
        );
    }

    public static function payoutProcessed(int $photographerUserId, float $amount, $disbursement = null): self
    {
        $refId = $disbursement?->id ? (string) $disbursement->id : null;
        return static::notifyOnce(
            $photographerUserId,
            'payout',
            '💸 โอนเงินเรียบร้อย',
            "โอนเงินรายได้ ฿" . number_format($amount, 2) . " เข้าบัญชีของคุณเรียบร้อย",
            'photographer/earnings',
            $refId
        );
    }

    public static function newReview(int $photographerUserId, int $rating, ?string $eventName = null): self
    {
        $stars = str_repeat('⭐', min(5, max(1, $rating)));
        return static::notify(
            $photographerUserId,
            'review',
            'รีวิวใหม่ ' . $stars,
            'ลูกค้ารีวิว' . ($eventName ? " อีเวนต์ {$eventName}" : 'ผลงานของคุณ'),
            'photographer/events'
        );
    }

    /* ═════════════════════════════════════════════════════════════════
     *  Phase 5 — New helpers for previously-missing events.
     *  Each is best-effort and respects user preferences (via notify()).
     * ═════════════════════════════════════════════════════════════════ */

    /** Photographer's subscription is N days from auto-renewal/expiry. */
    public static function subscriptionExpiring(int $photographerUserId, int $daysLeft, ?string $planName = null): self
    {
        $plan = $planName ?: 'แผนปัจจุบัน';
        return static::notifyOnce(
            $photographerUserId,
            'subscription_expiring',
            "⏰ {$plan} จะต่ออายุใน {$daysLeft} วัน",
            'ตรวจสอบยอดบัญชี/วิธีชำระเงินเพื่อต่ออายุอัตโนมัติ',
            'photographer/subscription',
            'sub_exp:' . $photographerUserId . ':' . now()->format('Ymd')
        );
    }

    /** Photographer's subscription has expired and they've been downgraded. */
    public static function subscriptionExpired(int $photographerUserId, ?string $planName = null): self
    {
        $plan = $planName ?: 'แผน';
        return static::notify(
            $photographerUserId,
            'subscription_expired',
            "⚠️ {$plan} หมดอายุแล้ว",
            'ระบบดาวน์เกรดเป็นแผน Free — รูปและงานทั้งหมดยังอยู่ครบ',
            'photographer/subscription/plans'
        );
    }

    /** Photographer storage approaching limit. */
    public static function storageQuotaWarning(int $photographerUserId, int $usedBytes, int $quotaBytes): ?self
    {
        if ($quotaBytes <= 0) return null;
        $pct = round(($usedBytes / $quotaBytes) * 100);

        // Throttle one notification per day per photographer.
        return static::notifyOnce(
            $photographerUserId,
            'storage_warning',
            "💾 พื้นที่ใกล้เต็ม ({$pct}%)",
            'พิจารณาลบงานเก่าหรืออัปเกรดแผนเพื่อเพิ่มพื้นที่',
            'photographer/subscription/plans',
            'storage:' . $photographerUserId . ':' . now()->format('Ymd')
        );
    }

    /** AI task (face indexing, preset render, etc.) finished. */
    public static function aiTaskComplete(int $photographerUserId, string $taskName, $task = null): self
    {
        $refId = $task?->id ? (string) $task->id : null;
        return static::notifyOnce(
            $photographerUserId,
            'ai_task_complete',
            "🤖 {$taskName} เสร็จแล้ว",
            'ดูผลลัพธ์ในหน้า AI Tools',
            'photographer/ai',
            $refId
        );
    }

    /** Slip auto-rejected by SlipOK / Omise scoring. */
    public static function slipAutoRejected(int $userId, $order, string $reason = ''): self
    {
        $num = $order->order_number ?? "#{$order->id}";
        return static::notify(
            $userId,
            'slip_auto_rejected',
            "❌ สลิปไม่ผ่านการตรวจอัตโนมัติ — {$num}",
            ($reason ?: 'ระบบไม่สามารถยืนยันสลิปได้') . ' กรุณาอัปโหลดสลิปใหม่',
            "payment/upload-slip/{$order->id}",
            (string) $order->id
        );
    }

    /** Photo on user's wishlist had its event price reduced. */
    public static function priceDrop(int $userId, $event, float $oldPrice, float $newPrice): self
    {
        return static::notifyOnce(
            $userId,
            'price_drop',
            '🔻 ราคาลดในรายการที่ติดตาม',
            "อีเวนต์ {$event->name} ลดจาก ฿" . number_format($oldPrice, 0) . " เหลือ ฿" . number_format($newPrice, 0),
            "events/{$event->id}",
            'price:' . $event->id
        );
    }

    /**
     * Generic notifications.
     */
    public static function welcome(int $userId, string $name): self
    {
        return static::notifyOnce(
            $userId,
            'welcome',
            "ยินดีต้อนรับ {$name} 🎉",
            'ขอบคุณที่ร่วมเป็นสมาชิก เริ่มต้นค้นหาภาพจากอีเวนต์ได้เลย',
            'events',
            'welcome:' . $userId
        );
    }

    public static function contactReply(int $userId, string $subject): self
    {
        return static::notify(
            $userId,
            'contact',
            'ทีมงานตอบกลับแล้ว',
            "หัวข้อ: {$subject}",
            'notifications'
        );
    }

    public static function systemAnnouncement(int $userId, string $title, string $message, ?string $url = null): self
    {
        return static::notify($userId, 'system', $title, $message, $url);
    }

    /* ═════════════════════════════════════════════════════════════════
     *  CLEANUP
     * ═════════════════════════════════════════════════════════════════ */

    /**
     * Clean up old user notifications (for scheduled cleanup).
     *
     * Two-tier policy mirroring AdminNotification:
     *   1. READ rows older than $daysOld → deleted (default 90 days).
     *   2. UNREAD rows older than 1 year → deleted (a forgotten
     *      notification from years ago should not keep the bell
     *      counter ticking forever).
     *
     * Returns total deleted across both tiers.
     */
    public static function cleanup(int $daysOld = 90): int
    {
        $deletedRead = static::where('is_read', true)
            ->where('read_at', '<', now()->subDays($daysOld))
            ->delete();

        $deletedStaleUnread = static::where('is_read', false)
            ->where('created_at', '<', now()->subYear())
            ->delete();

        return (int) $deletedRead + (int) $deletedStaleUnread;
    }

    /**
     * Check if user has a specific notification type enabled.
     * Falls back to "enabled" if preferences don't exist yet.
     */
    protected static function isTypeEnabled(int $userId, string $type): bool
    {
        try {
            if (!\Schema::hasTable('notification_preferences')) {
                return true;
            }

            $pref = \DB::table('notification_preferences')
                ->where('user_id', $userId)
                ->where('type', $type)
                ->first();

            if (!$pref) return true; // Default to enabled if no preference set

            return (bool) $pref->in_app_enabled;
        } catch (\Throwable $e) {
            return true; // On error, default to enabled
        }
    }
}

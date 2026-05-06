<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminNotification extends Model
{
    protected $table = 'admin_notifications';
    public $timestamps = false;

    protected $fillable = [
        'type', 'title', 'message', 'link', 'ref_id', 'is_read', 'read_at', 'created_at',
    ];

    protected $casts = [
        'is_read'    => 'boolean',
        'read_at'    => 'datetime',
        'created_at' => 'datetime',
    ];

    // ─── Scopes ───
    public function scopeUnread($q)
    {
        return $q->where('is_read', false);
    }

    // ─── Static helpers ───

    /**
     * Push a new admin notification.
     *
     * `link` is normalised to a relative path inside admin/ — we never
     * accept an absolute URL or a protocol-relative `//host/...` form,
     * because the bell-icon JS does `location.href = baseUrl + '/' +
     * link`. If we let arbitrary URLs through, a future code path that
     * accidentally stores user-controllable text could redirect admins
     * off-site. Belt-and-braces: server-side here + JS-side check too.
     */
    public static function notify(string $type, string $title, string $message = '', ?string $link = null, ?string $refId = null): self
    {
        return static::create([
            'type'       => $type,
            'title'      => $title,
            'message'    => $message,
            'link'       => static::sanitiseLink($link),
            'ref_id'     => $refId,
            'created_at' => now(),
        ]);
    }

    /**
     * Strip absolute URLs and protocol-relative paths from notification
     * links so they always resolve against the current host. Returns
     * null for empty / invalid input.
     *
     * Blocks:
     *   - http://...   https://...    (absolute URLs)
     *   - //evil.com   (protocol-relative URLs)
     *   - javascript:  data:  vbscript:  file:  etc. (any scheme)
     *
     * Accepts:
     *   - admin/orders/5
     *   - /admin/orders/5      (leading slash stripped)
     *   - dashboard?tab=foo
     */
    public static function sanitiseLink(?string $link): ?string
    {
        if ($link === null || $link === '') {
            return null;
        }
        $link = trim($link);
        // Reject absolute URLs and protocol-relative forms.
        if (preg_match('#^(https?:)?//#i', $link)) {
            return null;
        }
        // Reject ANY scheme-prefixed URI (`javascript:`, `data:`,
        // `vbscript:`, etc.) — RFC 3986 scheme rule is
        // ALPHA *( ALPHA / DIGIT / "+" / "-" / "." ).
        if (preg_match('#^[a-z][a-z0-9+.\-]*:#i', $link)) {
            return null;
        }
        // Strip any leading slash so `url($link)` builds clean.
        return ltrim($link, '/');
    }

    /**
     * Push notification for a new order. Recognises subscription orders
     * (order_type=subscription) and surfaces the plan name + photographer
     * in the notification — without this, admins just saw a generic
     * "ออเดอร์ใหม่ SUB-..." with no context about which plan.
     */
    public static function newOrder($order): self
    {
        $num   = $order->order_number ?? "#{$order->id}";
        $isSub = ($order->order_type ?? null) === 'subscription'
              || str_starts_with((string) $num, 'SUB-');

        if ($isSub) {
            $invoice = method_exists($order, 'subscriptionInvoice') ? $order->subscriptionInvoice : null;
            $sub     = $invoice?->subscription;
            $plan    = $sub?->plan;
            $shooter = $sub?->photographer;
            $byName  = $shooter
                ? trim(($shooter->first_name ?? '') . ' ' . ($shooter->last_name ?? ''))
                : '';
            $byName  = $byName !== '' ? $byName : ($shooter->email ?? "user#{$order->user_id}");

            return static::notify(
                'order',
                "สมัครแผน {$plan?->name} รอชำระ",
                sprintf('โดย %s · ยอด ฿%s', $byName, number_format($order->total, 0)),
                $sub ? "admin/subscriptions/{$sub->id}" : "admin/orders/{$order->id}",
                (string) $order->id
            );
        }

        return static::notify(
            'order',
            "ออเดอร์ใหม่ {$num}",
            "ยอด ฿" . number_format($order->total, 0) . " รอชำระเงิน",
            "admin/orders/{$order->id}",
            (string) $order->id
        );
    }

    /**
     * Push notification for a new user registration.
     */
    public static function newUser($user): self
    {
        $name = $user->name ?? $user->email ?? 'Unknown';
        return static::notify(
            'user',
            'สมาชิกใหม่',
            $name . ' สมัครสมาชิกเรียบร้อย',
            'admin/users',
            (string) $user->id
        );
    }

    /**
     * Push notification for a new payment slip upload.
     *
     * NOTE on ref_id: we ALWAYS store $order->id (not $slip->id) so the
     * `markReadByRef(['slip','order','payment'], $orderId)` calls in
     * Admin/PaymentController correctly dismiss every notification tied
     * to one order in a single sweep when the admin verifies the slip.
     * Previously this read `$slip->id ?? $order->id`, which caused
     * observer-created rows (with slip id) to stay highlighted forever
     * after admin approval — the bell counter wouldn't decrement.
     */
    public static function newSlip($order, $slip = null): self
    {
        $num = $order->order_number ?? "#{$order->id}";
        return static::notify(
            'slip',
            "สลิปใหม่รอตรวจ {$num}",
            "ยอด ฿" . number_format($order->total, 0),
            "admin/payments/slips",
            (string) $order->id
        );
    }

    /**
     * Push notification for a successful payment.
     *
     * Idempotent: if a `payment` notification already exists for this
     * order, return the existing row instead of creating a duplicate.
     * This is important because:
     *   - Payment-gateway webhooks are retried by the provider on
     *     transient failures (Stripe/Omise/PayPal all do this).
     *   - The observer fires whenever Order::status flips to 'paid',
     *     so a manual admin approval followed by a webhook arriving
     *     late would otherwise create two rows.
     * The "ref_id index" added in the migration makes the lookup
     * effectively free.
     */
    public static function paymentSuccess($order): self
    {
        $num    = $order->order_number ?? "#{$order->id}";
        $refId  = (string) $order->id;

        $existing = static::where('type', 'payment')
            ->where('ref_id', $refId)
            ->first();
        if ($existing) {
            return $existing;
        }

        // Subscription orders deserve a specific label so the bell shows
        // "แผน Pro เปิดใช้งาน" instead of a generic "ชำระเงินสำเร็จ" —
        // admins glancing at the bell can immediately tell a subscription
        // activated (revenue event) vs a photo-order paid (delivery event).
        $isSub = ($order->order_type ?? null) === 'subscription'
              || str_starts_with((string) $num, 'SUB-');

        if ($isSub) {
            $invoice = method_exists($order, 'subscriptionInvoice') ? $order->subscriptionInvoice : null;
            $sub     = $invoice?->subscription;
            $plan    = $sub?->plan;
            $shooter = $sub?->photographer;
            $byName  = $shooter
                ? trim(($shooter->first_name ?? '') . ' ' . ($shooter->last_name ?? ''))
                : '';
            $byName  = $byName !== '' ? $byName : ($shooter->email ?? "user#{$order->user_id}");

            return static::notify(
                'payment',
                "แผน {$plan?->name} เปิดใช้งาน 🎉",
                sprintf('โดย %s · ยอด ฿%s', $byName, number_format($order->total, 0)),
                $sub ? "admin/subscriptions/{$sub->id}" : "admin/orders/{$order->id}",
                $refId
            );
        }

        return static::notify(
            'payment',
            "ชำระเงินสำเร็จ {$num}",
            "฿" . number_format($order->total, 0) . " ยืนยันแล้ว",
            "admin/orders/{$order->id}",
            $refId
        );
    }

    /**
     * Push notification when a photographer cancels their subscription
     * (soft-cancel — the sub stays active until period_end). Useful for
     * admin to track churn and reach out for retention if they want.
     */
    public static function subscriptionCancelled($sub): self
    {
        $plan    = $sub->plan ?? null;
        $shooter = $sub->photographer ?? null;
        $name    = $shooter
            ? trim(($shooter->first_name ?? '') . ' ' . ($shooter->last_name ?? ''))
            : '';
        $name    = $name !== '' ? $name : ($shooter->email ?? "user#{$sub->photographer_id}");
        $endsAt  = $sub->current_period_end?->format('d M Y') ?? '—';

        return static::notify(
            'subscription',
            'ช่างภาพยกเลิกแผน',
            sprintf('%s ยกเลิก %s — ใช้ได้ถึง %s', $name, $plan?->name ?? 'plan', $endsAt),
            "admin/subscriptions/{$sub->id}",
            (string) $sub->id
        );
    }

    /**
     * Push notification when a subscription enters grace period (renewal
     * payment failed). Admin should see this to follow up before the
     * grace window closes and the photographer drops to free.
     */
    public static function subscriptionGraceEntered($sub): self
    {
        $plan    = $sub->plan ?? null;
        $shooter = $sub->photographer ?? null;
        $name    = $shooter
            ? trim(($shooter->first_name ?? '') . ' ' . ($shooter->last_name ?? ''))
            : '';
        $name    = $name !== '' ? $name : ($shooter->email ?? "user#{$sub->photographer_id}");
        $graceEnds = $sub->grace_ends_at?->format('d M Y') ?? '—';

        return static::notify(
            'subscription',
            'แผนเข้าช่วงผ่อนผัน — รอชำระ',
            sprintf('%s แผน %s ตัดบัตรไม่ผ่าน · ผ่อนผันถึง %s', $name, $plan?->name ?? '', $graceEnds),
            "admin/subscriptions/{$sub->id}",
            (string) $sub->id
        );
    }

    /**
     * Push notification for a new photographer registration.
     */
    public static function newPhotographer($photographer): self
    {
        $name = $photographer->display_name ?? $photographer->user->name ?? 'Unknown';
        return static::notify(
            'photographer',
            'ช่างภาพสมัครใหม่',
            "{$name} รอการอนุมัติ",
            'admin/photographers',
            (string) $photographer->id
        );
    }

    /**
     * Push notification for a new contact message.
     */
    public static function newContact($contact): self
    {
        $name = $contact->name ?? 'ผู้ใช้';
        return static::notify(
            'contact',
            "ข้อความใหม่จาก {$name}",
            mb_substr($contact->subject ?? $contact->message ?? '', 0, 80),
            'admin/messages/' . $contact->id,
            (string) $contact->id
        );
    }

    /**
     * Push notification for a new review.
     */
    public static function newReview($review): self
    {
        $stars = str_repeat('⭐', min(5, max(1, (int)($review->rating ?? 5))));
        return static::notify(
            'review',
            "รีวิวใหม่ {$stars}",
            mb_substr($review->comment ?? '', 0, 80) ?: 'รีวิว ' . $review->rating . '/5',
            'admin/reviews',
            (string) $review->id
        );
    }

    /**
     * Push notification for a refund request.
     */
    public static function refundRequest($order, float $amount): self
    {
        $num = $order->order_number ?? "#{$order->id}";
        return static::notify(
            'refund',
            "คำขอคืนเงิน {$num}",
            "ยอด ฿" . number_format($amount, 2),
            'admin/finance/refunds',
            (string) $order->id
        );
    }

    /**
     * Push notification for a security alert.
     */
    public static function securityAlert(string $title, string $message, ?string $severity = 'medium'): self
    {
        $icon = match ($severity) {
            'critical' => '🚨',
            'high'     => '⚠️',
            default    => 'ℹ️',
        };

        return static::notify(
            'security',
            "{$icon} {$title}",
            $message,
            'admin/security/dashboard'
        );
    }

    /**
     * Push notification for low stock / inventory warning.
     */
    public static function systemAlert(string $title, string $message, ?string $link = null): self
    {
        return static::notify('system', "⚙️ {$title}", $message, $link);
    }

    // ─── New helpers (coverage gaps from audit 2026-05-18) ───

    /**
     * Push a notification when a payment-gateway webhook fails (signature
     * mismatch, malformed payload, repeated retry, etc.).
     *
     * Idempotent on (provider, ref) — repeated webhook retries from the
     * same provider for the same order won't spam the bell. Useful when
     * Stripe/Omise/PayPal hammer the endpoint after a transient error.
     */
    public static function webhookFailure(string $provider, string $reason, ?string $ref = null): ?self
    {
        $refId = $provider . ($ref ? ':' . $ref : '');

        // Throttle: one notification per (provider, ref) per hour.
        $existing = static::where('type', 'webhook_failure')
            ->where('ref_id', $refId)
            ->where('created_at', '>=', now()->subHour())
            ->first();
        if ($existing) {
            return $existing;
        }

        return static::notify(
            'webhook_failure',
            "⚠️ Webhook ล้มเหลว: {$provider}",
            mb_substr($reason, 0, 240),
            // Link to the security dashboard — webhook signature
            // failures are a security concern (potentially forged
            // requests). The previously-used `admin/payments/audit-log`
            // route doesn't exist in this app; using `admin/security`
            // which is the catch-all security event surface.
            'admin/security',
            $refId
        );
    }

    /**
     * Push a notification when admin login bruteforce is detected
     * (multiple failed logins from same IP within short window).
     */
    public static function loginAbuse(string $ipAddress, int $failureCount, ?string $email = null): ?self
    {
        $refId = 'ip:' . $ipAddress;

        // Throttle: one notification per IP per hour.
        $existing = static::where('type', 'security')
            ->where('ref_id', $refId)
            ->where('created_at', '>=', now()->subHour())
            ->first();
        if ($existing) {
            return $existing;
        }

        $emailHint = $email ? " (target: {$email})" : '';
        return static::notify(
            'security',
            "🚨 Login ผิดพลาดซ้ำจาก {$ipAddress}",
            "พยายามเข้าระบบล้มเหลว {$failureCount} ครั้ง{$emailHint}",
            'admin/security/dashboard',
            $refId
        );
    }

    /**
     * Push a notification when a photographer disbursement (payout)
     * succeeds. Helpful for monitoring large cash-out events.
     */
    public static function disbursementSuccess($disbursement): self
    {
        $amount = (float) ($disbursement->amount_thb ?? 0);
        return static::notify(
            'payout',
            "✅ จ่ายเงินช่างภาพสำเร็จ ฿" . number_format($amount, 2),
            "Disbursement #{$disbursement->id} โอนผ่าน {$disbursement->provider}",
            "admin/finance/disbursements/{$disbursement->id}",
            (string) $disbursement->id
        );
    }

    /**
     * Push a notification when an admin suspends or reactivates a
     * photographer (audit trail in the bell).
     */
    public static function photographerStatusChange($photographer, string $action, ?int $byAdminId = null): self
    {
        $name = $photographer->display_name ?? 'Unknown';
        $verb = match ($action) {
            'suspend'    => '🛑 ระงับ',
            'reactivate' => '✅ เปิดใช้งาน',
            'reject'     => '❌ ปฏิเสธ',
            default      => 'อัปเดต',
        };
        $by = $byAdminId ? " (admin #{$byAdminId})" : '';
        return static::notify(
            'photographer.action',
            "{$verb}ช่างภาพ {$name}",
            "การกระทำของแอดมิน{$by}",
            "admin/photographers/{$photographer->id}",
            (string) $photographer->id
        );
    }

    /**
     * Push a notification when a photographer's storage is near-full
     * (e.g. 90%+) — gives admins a chance to follow up.
     */
    public static function storageAlert($photographer, int $usedBytes, int $quotaBytes): ?self
    {
        if ($quotaBytes <= 0) return null;
        $pct = round(($usedBytes / $quotaBytes) * 100, 1);

        // Throttle: one notification per photographer per day.
        $refId = 'storage:' . ($photographer->id ?? 0);
        $existing = static::where('type', 'storage_alert')
            ->where('ref_id', $refId)
            ->where('created_at', '>=', now()->subDay())
            ->first();
        if ($existing) {
            return $existing;
        }

        $name = $photographer->display_name ?? 'Unknown';
        $usedGb = round($usedBytes / (1024 ** 3), 2);
        $quotaGb = round($quotaBytes / (1024 ** 3), 2);
        return static::notify(
            'storage_alert',
            "💾 พื้นที่ช่างภาพ {$name} ใกล้เต็ม ({$pct}%)",
            "ใช้ไป {$usedGb} GB จากโควตา {$quotaGb} GB",
            "admin/photographers/{$photographer->id}",
            $refId
        );
    }

    /**
     * Push a notification when an admin notification email itself
     * bounces — we can't trust the email channel, so we tell the
     * admin in-app instead.
     */
    public static function emailBounce(string $recipientEmail, string $reason): self
    {
        return static::notify(
            'email_bounce',
            "📧 อีเมลไม่ส่ง: {$recipientEmail}",
            mb_substr($reason, 0, 240),
            'admin/notifications',
            'bounce:' . $recipientEmail
        );
    }

    /**
     * Mark a group of notifications as read by type or ref_id.
     */
    public static function markReadByRef($type, string $refId): int
    {
        $q = static::where('ref_id', $refId)->where('is_read', false);
        if (is_array($type)) {
            $q->whereIn('type', $type);
        } else {
            $q->where('type', $type);
        }
        return $q->update(['is_read' => true, 'read_at' => now()]);
    }

    /**
     * Clean up old notifications (for scheduled cleanup).
     *
     * Two-tier policy:
     *   1. READ notifications older than $daysOld get deleted (default
     *      90 days — long enough for monthly audit, short enough to
     *      keep the table small).
     *   2. UNREAD notifications older than 1 year get deleted too.
     *      This second tier wasn't here before — meant a forgotten
     *      unread row from years ago kept the bell counter ticking
     *      forever. If admins haven't noticed it in 365 days, it's
     *      not actionable.
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
}

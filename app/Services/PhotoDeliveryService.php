<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\DownloadToken;
use App\Models\Order;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * PhotoDeliveryService — single entry point for "deliver the photos to the
 * buyer" after a paid order.
 *
 * Delivery channels
 * ─────────────────
 *   web   — download tokens on the site (classic)
 *   line  — push a download link via LINE Messaging API (requires OAuth)
 *   email — email with a signed download link (best for large orders)
 *   auto  — let the service pick: LINE if linked + few photos, email if many,
 *           else web.
 *
 * The service is idempotent — calling `deliver()` twice on the same order is
 * safe (tokens aren't duplicated, the second email/push just re-sends the
 * same link). Admins can trigger a resend via the admin UI without worrying
 * about side effects.
 *
 * All dispatch errors are swallowed + logged. A delivery failure should never
 * block payment approval — the buyer can always download via the web fallback.
 */
class PhotoDeliveryService
{
    public function __construct(
        private MailService $mail,
        private LineNotifyService $line,
    ) {}

    /**
     * Deliver photos for a paid order. Main entry point.
     *
     * @return array{method:string, status:string, message:string, url:?string}
     */
    public function deliver(Order $order): array
    {
        $order->loadMissing(['user', 'items', 'event']);
        $method = $this->resolveMethod($order);

        // Always ensure web tokens exist — even if primary delivery is LINE/email,
        // the link we send points to the web download page.
        $this->ensureDownloadTokens($order);

        $result = match ($method) {
            'line'  => $this->deliverViaLine($order),
            'email' => $this->deliverViaEmail($order),
            default => $this->deliverViaWeb($order),
        };

        // Persist the outcome on the order for admin visibility + resend.
        try {
            $order->update([
                'delivery_method' => $method,
                'delivery_status' => $result['status'],
                'delivered_at'    => $result['status'] === 'delivered' ? now() : $order->delivered_at,
                'delivery_meta'   => array_merge((array) ($order->delivery_meta ?? []), [
                    'last_result'   => $result,
                    'last_tried_at' => now()->toDateTimeString(),
                    'photo_count'   => $order->items->count(),
                ]),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to persist delivery state for order ' . $order->id . ': ' . $e->getMessage());
        }

        return $result + ['method' => $method];
    }

    /**
     * Decide which delivery channel to use based on:
     *   1. Explicit buyer choice (order->delivery_method)
     *   2. Admin-enabled methods (some sites may disable LINE entirely)
     *   3. Auto-switch rules: too many photos → email, LINE linked → LINE, else web
     */
    public function resolveMethod(Order $order): string
    {
        $enabled = $this->enabledMethods();
        $chosen  = $order->delivery_method ?: 'auto';

        // If buyer picked something explicit AND admin allows it, honor it.
        if (in_array($chosen, ['web', 'line', 'email'], true) && in_array($chosen, $enabled, true)) {
            // But: LINE without a linked account always downgrades to web.
            if ($chosen === 'line' && !$this->userHasLine($order->user_id)) {
                return in_array('email', $enabled, true) ? 'email' : 'web';
            }
            return $chosen;
        }

        // ── Auto mode ───────────────────────────────────────────────────────
        $autoSwitch    = AppSetting::get('delivery_auto_switch', '1') === '1';
        $emailThresh   = (int) AppSetting::get('delivery_email_threshold', '30');
        $photoCount    = $order->items->count();

        if ($autoSwitch && $photoCount >= $emailThresh && in_array('email', $enabled, true)) {
            return 'email';
        }

        if (in_array('line', $enabled, true) && $this->userHasLine($order->user_id)) {
            return 'line';
        }

        if (in_array('email', $enabled, true) && $this->userHasEmail($order->user_id)) {
            return 'email';
        }

        return 'web';
    }

    /*----------------------------------------------------------------------
    | Channel: Web (fallback — always available)
    |----------------------------------------------------------------------*/

    private function deliverViaWeb(Order $order): array
    {
        $url = route('orders.show', $order->id);
        return [
            'status'  => 'delivered',
            'message' => 'พร้อมให้ดาวน์โหลดจากหน้าคำสั่งซื้อแล้ว',
            'url'     => $url,
        ];
    }

    /*----------------------------------------------------------------------
    | Channel: LINE
    |----------------------------------------------------------------------*/

    private function deliverViaLine(Order $order): array
    {
        if (!$order->user_id || !$this->userHasLine($order->user_id)) {
            return $this->fallback($order, 'LINE ไม่ได้เชื่อมต่อ');
        }

        // Dispatch the actual delivery to a job. The webhook flow that
        // calls deliver() (post-payment + admin-approval paths) needs to
        // ack within seconds — running the LINE pushes inline can pin
        // a worker for ~30s when LINE is congested. The job emits its
        // pushes through SendLinePushJob, so each one is retried on
        // 5xx and audited in line_deliveries.
        \App\Jobs\Line\DeliverOrderViaLineJob::dispatch($order->id);

        $url = route('orders.show', $order->id);
        // delivery_status enum is ['pending','sent','delivered','failed','partial'].
        // 'sent' = we handed it off; SendLinePushJob's audit trail
        // (line_deliveries) is the source of truth for actual delivery.
        return [
            'status'  => 'sent',
            'message' => 'จัดคิวการแจ้งเตือนทาง LINE แล้ว',
            'url'     => $url,
        ];
    }

    /*----------------------------------------------------------------------
    | Channel: Email
    |----------------------------------------------------------------------*/

    private function deliverViaEmail(Order $order): array
    {
        $user = $order->user;
        if (!$user || empty($user->email)) {
            return $this->fallback($order, 'ไม่มีอีเมลผู้รับ');
        }

        $url         = route('orders.show', $order->id);
        $photoCount  = $order->items->count();
        $expiresAt   = $order->downloadTokens()->max('expires_at');

        $ok = false;
        try {
            $ok = $this->mail->downloadReady(
                [
                    'id'           => $order->id,
                    'order_number' => $order->order_number,
                    'email'        => $user->email,
                    'name'         => $user->first_name ?? $user->name ?? 'ลูกค้า',
                ],
                $url,
                [
                    'photo_count' => $photoCount,
                    'expires_at'  => $expiresAt ? \Carbon\Carbon::parse($expiresAt)->format('d/m/Y H:i') : now()->addDays(30)->format('d/m/Y H:i'),
                ]
            );
        } catch (\Throwable $e) {
            Log::warning('Email delivery error order ' . $order->id . ': ' . $e->getMessage());
        }

        if (!$ok) {
            return $this->fallback($order, 'ส่งอีเมลไม่สำเร็จ');
        }

        return [
            'status'  => 'delivered',
            'message' => 'ส่งลิงก์ดาวน์โหลดทางอีเมลแล้ว',
            'url'     => $url,
        ];
    }

    /*----------------------------------------------------------------------
    | Helpers
    |----------------------------------------------------------------------*/

    /**
     * Which methods did the admin enable? Controls which appear in the
     * checkout picker AND restricts auto-switch candidates.
     */
    private function enabledMethods(): array
    {
        $raw = AppSetting::get('delivery_methods_enabled', '["web","line","email"]');
        $decoded = json_decode((string) $raw, true);
        $methods = is_array($decoded) ? $decoded : ['web', 'line', 'email'];

        // Web is always available as a safety net regardless of admin config.
        if (!in_array('web', $methods, true)) {
            $methods[] = 'web';
        }
        return $methods;
    }

    private function userHasLine(?int $userId): bool
    {
        if (!$userId) return false;
        try {
            if (!Schema::hasTable('auth_social_logins')) return false;
            return \DB::table('auth_social_logins')
                ->where('user_id', $userId)
                ->where('provider', 'line')
                ->exists();
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function userHasEmail(?int $userId): bool
    {
        if (!$userId) return false;
        try {
            $email = \DB::table('auth_users')->where('id', $userId)->value('email');
            return !empty($email);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Ensure the order has download tokens so the eventual link works.
     *
     * Deliberately mirrors the existing Admin\PaymentController::createDownloadTokens
     * behavior (30-day expiry, one "all photos" token + per-photo tokens). If the
     * order already has tokens, do nothing — avoids duplication on resend.
     */
    private function ensureDownloadTokens(Order $order): void
    {
        try {
            if (!Schema::hasTable('download_tokens')) return;
            if ($order->downloadTokens()->exists()) return;

            $expiresAt = now()->addDays(30);
            $itemCount = $order->items->count();

            DownloadToken::create([
                'token'          => bin2hex(random_bytes(32)),
                'order_id'       => $order->id,
                'user_id'        => $order->user_id,
                'photo_id'       => null,
                'expires_at'     => $expiresAt,
                'max_downloads'  => max($itemCount * 2, 10),
                'download_count' => 0,
            ]);

            foreach ($order->items as $item) {
                DownloadToken::create([
                    'token'          => bin2hex(random_bytes(32)),
                    'order_id'       => $order->id,
                    'user_id'        => $order->user_id,
                    'photo_id'       => $item->photo_id,
                    'expires_at'     => $expiresAt,
                    'max_downloads'  => 5,
                    'download_count' => 0,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('ensureDownloadTokens failed order ' . $order->id . ': ' . $e->getMessage());
        }
    }

    /**
     * When the chosen channel fails, fall back to web — the buyer always has
     * a download option. Records the attempt so admin sees why it downgraded.
     */
    private function fallback(Order $order, string $reason): array
    {
        $url = route('orders.show', $order->id);
        return [
            'status'  => 'partial',
            'message' => 'ดาวน์โหลดจากหน้าคำสั่งซื้อได้เลย (fallback: ' . $reason . ')',
            'url'     => $url,
        ];
    }

    /**
     * Build the [original_url, preview_url] list for every purchased
     * photo on the order, suitable for `LineNotifyService::pushPhotos`.
     *
     * LINE requires HTTPS URLs only. We expose the watermarked-preview
     * (1600px) as `originalContentUrl` and the thumbnail (400px) as
     * `previewImageUrl`. The full-resolution unwatermarked download
     * still requires the web tokens — those go in the Flex bubble that
     * follows.
     *
     * Photos that don't have publicly-resolvable HTTPS URLs (e.g. local
     * disk in dev) are skipped silently; the caller falls back to the
     * web download link.
     */
    private function collectPhotoUrls(Order $order): array
    {
        $urls = [];
        $order->loadMissing('items');
        $photoIds = $order->items->pluck('photo_id')->filter()->unique()->all();
        if (empty($photoIds)) return [];

        $photos = \App\Models\EventPhoto::whereIn('id', $photoIds)->get();
        foreach ($photos as $photo) {
            try {
                $disk = $photo->storage_disk ?? 'public';
                $store = \Illuminate\Support\Facades\Storage::disk($disk);

                // Prefer watermarked preview (so the un-paid behavior
                // matches the buyer's expectation: they see what they
                // bought watermark-free elsewhere). If preview missing,
                // fall back to the thumbnail.
                $previewPath = $photo->preview_path ?: $photo->thumbnail_path;
                $thumbPath   = $photo->thumbnail_path ?: $previewPath;

                $original = $previewPath ? $store->url($previewPath) : null;
                $preview  = $thumbPath   ? $store->url($thumbPath)   : null;

                if ($original && $preview && str_starts_with($original, 'https://') && str_starts_with($preview, 'https://')) {
                    $urls[] = ['original_url' => $original, 'preview_url' => $preview];
                }
            } catch (\Throwable $e) {
                // Skip photos whose URLs we can't resolve; LINE will
                // simply receive fewer image messages.
                continue;
            }
        }
        return $urls;
    }
}

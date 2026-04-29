<?php

namespace App\Jobs\Line;

use App\Models\Order;
use App\Services\LineNotifyService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Runs the LINE delivery flow for a paid order asynchronously.
 *
 * Why this is a job
 * -----------------
 * Customer photo deliveries can fire 5–25 LINE pushes per order
 * (caption + N image chunks + flex download link). Doing this inline
 * in the payment webhook pins a PHP-FPM worker for ~30 seconds and,
 * if LINE's API is congested, can blow the 30-second webhook budget.
 *
 * Moving to a queued job:
 *   • frees the webhook to ack the gateway in <100 ms,
 *   • lets the SendLinePushJob retry path absorb LINE 5xx without
 *     spilling failures back to the user-facing flow,
 *   • makes "did the LINE notification go out?" observable — every
 *     push the job triggers writes a line_deliveries row.
 *
 * Idempotency
 * -----------
 * The job is keyed by order_id. Each push emitted internally carries an
 * idempotency_key of the form `order.{id}.line.{slot}` so a job rerun
 * (after a queue worker crash) won't double-spam the customer — the
 * line_deliveries unique index on (line_user_id, idempotency_key)
 * collapses retries to a single delivery.
 *
 * Failure
 * -------
 * The wrapped LineNotifyService methods all return bool and never throw
 * for ordinary "user unreachable" cases — so we don't need re-throws here
 * for retry. We DO try/catch the whole flow defensively because PHP can
 * still raise unrelated errors (DB connection lost mid-job, etc).
 */
class DeliverOrderViaLineJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 120;
    public int $backoff = 60;

    public function __construct(public readonly int $orderId)
    {
        $this->onQueue('notifications');
    }

    public function handle(LineNotifyService $line): void
    {
        $order = Order::with(['items', 'event', 'downloadTokens'])->find($this->orderId);
        if (!$order) {
            Log::warning('DeliverOrderViaLineJob: order not found', ['order_id' => $this->orderId]);
            return;
        }
        if (!$order->user_id) {
            return;
        }

        $url         = route('orders.show', $order->id);
        $orderNumber = $order->order_number ?: ('#' . $order->id);
        $photoCount  = $order->items->count();
        $eventName   = $order->event?->name ?? 'ภาพถ่าย';
        $expiresAt   = $order->downloadTokens()->max('expires_at');

        // ── Push photos (optional) ───────────────────────────────────
        if (\App\Models\AppSetting::get('delivery_line_send_photos', '0') === '1'
            && $photoCount > 0 && $photoCount <= 30) {
            try {
                $images = $this->collectPhotoUrls($order);
                if (count($images) > 0) {
                    $caption = "📸 รูปจาก {$eventName}\n"
                             . "คำสั่งซื้อ {$orderNumber} — {$photoCount} รูป\n"
                             . "รูป full-resolution: ดาวน์โหลดที่ลิงก์ด้านล่าง 👇";
                    // queuePushPhotos enqueues SendLinePushJob per chunk
                    // with idempotency keys, so a job rerun is safe.
                    $line->queuePushPhotos(
                        $order->user_id,
                        $images,
                        $caption,
                        idempotencyPrefix: "order.{$order->id}.line.photos",
                    );
                }
            } catch (\Throwable $e) {
                Log::warning('DeliverOrderViaLineJob: pushPhotos failed', [
                    'order_id' => $order->id,
                    'error'    => $e->getMessage(),
                ]);
            }
        }

        // ── Push download link (Flex bubble + plain-text fallback) ───
        $linkOk = false;
        try {
            $linkOk = $line->queuePushDownloadLink(
                $order->user_id,
                [
                    'id'           => $order->id,
                    'order_number' => $orderNumber,
                ],
                $url,
                [
                    'event_name'  => $eventName,
                    'photo_count' => $photoCount,
                    'expires_at'  => $expiresAt
                        ? \Carbon\Carbon::parse($expiresAt)->format('d/m/Y H:i')
                        : null,
                ],
                idempotencyKey: "order.{$order->id}.line.download",
            );
        } catch (\Throwable $e) {
            Log::warning('DeliverOrderViaLineJob: pushDownloadLink failed', [
                'order_id' => $order->id,
                'error'    => $e->getMessage(),
            ]);
        }

        // Mark the order as delivered once we've successfully queued the
        // download-link push. The wire-level LINE delivery happens in
        // SendLinePushJob; if THAT job ultimately fails after retries,
        // the line_deliveries audit row will say so — but for the
        // ORDER record the user-facing event is "we sent the
        // notification", which is the dispatch.
        //
        // Without this update, orders delivered via LINE would forever
        // have delivered_at=null (the queued path returns 'sent', not
        // 'delivered', so PhotoDeliveryService doesn't fill the column
        // itself). That broke the admin "delivered orders this week"
        // query — caught during the post-implementation regression
        // audit. Fix: stamp delivered_at when dispatch succeeds.
        if ($linkOk && empty($order->delivered_at)) {
            try {
                $order->update([
                    'delivery_status' => 'delivered',
                    'delivered_at'    => now(),
                ]);
            } catch (\Throwable $e) {
                Log::warning('DeliverOrderViaLineJob: order delivered_at update failed', [
                    'order_id' => $order->id,
                    'error'    => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Build [original_url, preview_url] pairs for the order's photos.
     *
     * URL TTL — important for LINE. The default presigned-URL TTL on R2
     * is 60 minutes; a customer who taps a saved chat 24 h later would
     * see broken images. We deliberately request a long TTL here
     * (`media.line_image_ttl_minutes`, default 30 d) so the chat stays
     * visually intact for the full retention window.
     */
    private function collectPhotoUrls(Order $order): array
    {
        $order->loadMissing('items');
        $photoIds = $order->items->pluck('photo_id')->filter()->unique()->all();
        if (empty($photoIds)) return [];

        $r2 = app(\App\Services\Media\R2MediaService::class);
        $ttlMin = (int) config('media.line_image_ttl_minutes', 30 * 24 * 60);

        $urls = [];
        $photos = \App\Models\EventPhoto::whereIn('id', $photoIds)->get();
        foreach ($photos as $photo) {
            try {
                $previewPath = $photo->watermarked_path ?: $photo->thumbnail_path;
                $thumbPath   = $photo->thumbnail_path  ?: $previewPath;

                $original = $previewPath ? $r2->signedReadUrl($previewPath, $ttlMin) : null;
                $preview  = $thumbPath   ? $r2->signedReadUrl($thumbPath,  $ttlMin) : null;

                if ($original && $preview
                    && str_starts_with($original, 'https://')
                    && str_starts_with($preview, 'https://')) {
                    $urls[] = ['original_url' => $original, 'preview_url' => $preview];
                }
            } catch (\Throwable) {
                // Skip photos whose URL signing fails (e.g. R2 not
                // configured locally) — caller falls back to the web
                // download link, which is always available.
                continue;
            }
        }
        return $urls;
    }
}

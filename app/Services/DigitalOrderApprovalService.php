<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\DigitalOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * DigitalOrderApprovalService — single source of truth for "this digital
 * order has been paid and the customer can download now".
 *
 * Two callers:
 *   1. Admin\DigitalOrderController::approve  (manual button click)
 *   2. Public\ProductController::uploadSlip   (auto via SlipOK when
 *                                              score passes threshold)
 *
 * Both must do the same things atomically:
 *   - Generate download token + expiry
 *   - Set status='paid', paid_at=now()
 *   - Insert digital_download_tokens row
 *   - Increment product.total_sales + .total_revenue
 *   - Send user notification with deep-link to the order page
 *   - Push LINE message to friend customers
 *
 * Returns true on success, false on any failure (already-paid, missing
 * order, transactional failure). Caller decides what to surface to the
 * actor — admin sees a flash message, customer gets a redirect to the
 * download page.
 */
class DigitalOrderApprovalService
{
    public function __construct(
        private readonly LineNotifyService $line,
    ) {}

    /**
     * Approve a digital order. Idempotent — if already paid, returns
     * the existing token. Wraps in transaction so a partial failure
     * doesn't leave the order in a half-approved state.
     */
    public function approve(int $orderId, ?int $approvedByAdminId = null, string $source = 'manual'): bool
    {
        $order = DB::table('digital_orders as do')
            ->join('digital_products as dp', 'dp.id', '=', 'do.product_id')
            ->select('do.*', 'dp.name as product_name', 'dp.download_limit', 'dp.download_expiry_days')
            ->where('do.id', $orderId)
            ->first();

        if (!$order) {
            return false;
        }

        // Idempotency — if already paid, don't double-approve. Just
        // re-send the notification so the customer gets another reminder.
        if ($order->status === 'paid') {
            $this->sendApprovalNotification($order);
            return true;
        }

        if (!in_array($order->status, ['pending_review', 'pending_payment'], true)) {
            return false;  // archived, cancelled, etc.
        }

        try {
            DB::transaction(function () use ($order, $approvedByAdminId, $source) {
                $downloadLimit = (int) ($order->download_limit ?? 5);
                $expiryDays    = (int) ($order->download_expiry_days ?? 30);
                $token         = Str::uuid()->toString();
                $expiresAt     = now()->addDays($expiryDays);

                DB::table('digital_orders')->where('id', $order->id)->update([
                    'status'              => 'paid',
                    'paid_at'             => now(),
                    'download_token'      => $token,
                    'downloads_remaining' => $downloadLimit,
                    'expires_at'          => $expiresAt,
                    'updated_at'          => now(),
                ]);

                if (Schema::hasTable('digital_download_tokens')) {
                    DB::table('digital_download_tokens')->insert([
                        'token'          => $token,
                        'order_id'       => $order->id,
                        'user_id'        => $order->user_id,
                        'product_id'     => $order->product_id,
                        'max_downloads'  => $downloadLimit,
                        'download_count' => 0,
                        'expires_at'     => $expiresAt,
                        'created_at'     => now(),
                    ]);
                }

                $product = DB::table('digital_products')->where('id', $order->product_id);
                $product->increment('total_sales', 1);
                $product->increment('total_revenue', (float) $order->amount);

                // Refresh the row so notifications have the new token
                $order->status              = 'paid';
                $order->download_token      = $token;
                $order->downloads_remaining = $downloadLimit;
                $order->expires_at          = $expiresAt;
            });

            $this->sendApprovalNotification($order);
            $this->pushLineMessage($order);

            Log::info('digital_order.approved', [
                'order_id'   => $order->id,
                'source'     => $source,
                'admin_id'   => $approvedByAdminId,
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::error('DigitalOrderApprovalService::approve failed', [
                'order_id' => $order->id,
                'error'    => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Notification with action_url so customer can click straight
     * through to the order page (which has the download button).
     */
    private function sendApprovalNotification(object $order): void
    {
        $downloadUrl = url('/products/order/' . $order->id);
        $table = Schema::hasTable('user_notifications') ? 'user_notifications' : 'notifications';

        // Free claims (amount=0, source='free_line_claim') get a
        // delight-flavoured copy, paid orders get the neutral
        // "approved" message. Same action_url either way.
        $isFree = (float) $order->amount <= 0;
        $title  = $isFree ? '🎁 รับฟรีสำเร็จ!' : '🎉 พร้อมดาวน์โหลดแล้ว!';
        $msg    = $isFree
            ? "ขอบคุณที่เพิ่มเพื่อน LINE — สิทธิ์ดาวน์โหลด {$order->product_name} พร้อมใช้งาน"
            : "คำสั่งซื้อ #{$order->order_number} ({$order->product_name}) ได้รับการอนุมัติ — กดเพื่อดาวน์โหลด";

        $row = [
            'user_id'    => $order->user_id,
            'type'       => 'digital_order_approved',
            'title'      => $title,
            'message'    => $msg,
            'action_url' => $downloadUrl,
            'is_read'    => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        // notifications schema varies — try with action_url then fall
        // back without if column missing.
        try {
            DB::table($table)->insert($row);
        } catch (\Throwable $e) {
            unset($row['action_url']);
            try { DB::table($table)->insert($row); }
            catch (\Throwable $e2) {
                Log::warning('Digital approval notif insert failed: ' . $e2->getMessage());
            }
        }
    }

    /**
     * LINE OA push for friend customers. Sends the download link
     * directly so customer can tap and start downloading.
     */
    private function pushLineMessage(object $order): void
    {
        $user = DB::table('auth_users')
            ->select('id', 'first_name', 'line_user_id', 'line_is_friend')
            ->where('id', $order->user_id)
            ->first();
        if (!$user || !$user->line_is_friend || empty($user->line_user_id)) {
            return;
        }

        $orderUrl = url('/products/order/' . $order->id);
        $isFree   = (float) $order->amount <= 0;

        // Free claims get a celebratory header that thanks them for
        // adding the friend (the value swap they made), paid orders
        // get a transactional confirmation. Both share the same
        // download URL + usage info footer.
        $header = $isFree
            ? "🎁 รับฟรีสำเร็จ — ขอบคุณที่เพิ่มเพื่อน!\n\n"
              . "📦 {$order->product_name}\n"
              . "🔢 #{$order->order_number}\n"
              . "💰 ฟรี\n\n"
            : "🎉 ออเดอร์พร้อมดาวน์โหลด!\n\n"
              . "📦 {$order->product_name}\n"
              . "🔢 #{$order->order_number}\n"
              . "💰 ฿" . number_format($order->amount, 2) . "\n\n";

        $msg = $header
             . "ดาวน์โหลดเลย: {$orderUrl}\n\n"
             . "✨ ดาวน์โหลดได้ {$order->downloads_remaining} ครั้ง · "
             . "หมดอายุ " . \Carbon\Carbon::parse($order->expires_at)->format('d M Y');

        try {
            $this->line->pushToUser($user->line_user_id, $msg);
        } catch (\Throwable $e) {
            Log::warning('Digital approval LINE push failed: ' . $e->getMessage());
        }
    }
}

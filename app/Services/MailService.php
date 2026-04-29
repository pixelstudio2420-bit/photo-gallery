<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\EmailLog;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;

/**
 * MailService — Production-grade email service for Photo Gallery
 *
 * Uses Blade templates under resources/views/emails/ for maintainable HTML.
 * Dynamically loads SMTP config from AppSetting (DB) at runtime.
 * Logs every send attempt to email_logs table.
 */
class MailService
{
    /* ═════════════════════════════════════════════════════════════════
     *  CORE SEND METHODS
     * ═════════════════════════════════════════════════════════════════ */

    /**
     * Render a Blade template and send.
     */
    public function sendTemplate(string $to, string $subject, string $template, array $data = [], string $type = 'general'): bool
    {
        $data = array_merge($this->defaultViewData(), $data);

        try {
            $htmlBody = View::make($template, $data)->render();
        } catch (\Throwable $e) {
            Log::error("MailService: Failed to render template [{$template}]: " . $e->getMessage());
            $this->log($to, $subject, $type, 'failed', 'Template render error: ' . $e->getMessage(), null);
            return false;
        }

        return $this->send($to, $subject, $htmlBody, $type);
    }

    /**
     * Send raw HTML email.
     */
    public function send(string $to, string $subject, string $htmlBody, string $type = 'general'): bool
    {
        $enabled = AppSetting::get('mail_enabled', '0');
        $driver  = AppSetting::get('mail_driver', config('mail.default', 'log'));

        if ($enabled !== '1') {
            $this->log($to, $subject, $type, 'skipped', null, $driver);
            return false;
        }

        // Dynamically override Laravel mail config from DB settings
        $this->applyMailConfig($driver);

        try {
            $fromEmail = AppSetting::get('mail_from_email', config('mail.from.address', 'noreply@example.com'));
            $fromName  = AppSetting::get('mail_from_name', config('mail.from.name', config('app.name')));

            Mail::html($htmlBody, function ($message) use ($to, $subject, $fromEmail, $fromName) {
                $message->to($to)
                        ->subject($subject)
                        ->from($fromEmail, $fromName);
            });

            $this->log($to, $subject, $type, 'sent', null, $driver);
            return true;
        } catch (\Throwable $e) {
            $this->log($to, $subject, $type, 'failed', $e->getMessage(), $driver);
            Log::error("MailService: Send failed to [{$to}]: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Queue a mail-sending call onto the `mail` queue.
     *
     *     MailService::queue('welcome', ['user@example.com', 'John']);
     *
     * Call this from hot request paths instead of the synchronous methods —
     * the SMTP handshake (100-500ms) then runs on a queue worker instead of
     * inside the web request.
     */
    public static function queue(string $method, array $arguments = []): void
    {
        try {
            \App\Jobs\SendMailJob::dispatch($method, $arguments);
        } catch (\Throwable $e) {
            // Queue unavailable → fall back to synchronous so the user still
            // gets their email (slower, but never silently dropped).
            Log::warning('MailService::queue fallback to sync: ' . $e->getMessage());
            try {
                app(self::class)->{$method}(...$arguments);
            } catch (\Throwable $e2) {
                Log::error("MailService sync fallback failed for {$method}: " . $e2->getMessage());
            }
        }
    }

    /* ═════════════════════════════════════════════════════════════════
     *  CUSTOMER EMAILS
     * ═════════════════════════════════════════════════════════════════ */

    public function welcome(string $email, string $name): bool
    {
        return $this->sendTemplate(
            $email,
            'ยินดีต้อนรับสู่ ' . $this->siteName(),
            'emails.customer.welcome',
            [
                'name'     => $name,
                'loginUrl' => url('/auth/login'),
            ],
            'welcome'
        );
    }

    public function emailVerification(string $email, string $name, string $verifyUrl): bool
    {
        return $this->sendTemplate(
            $email,
            'ยืนยันอีเมลของคุณ — ' . $this->siteName(),
            'emails.customer.email-verify',
            [
                'name'      => $name,
                'verifyUrl' => $verifyUrl,
            ],
            'email_verify'
        );
    }

    public function passwordReset(string $email, string $name, string $resetUrl): bool
    {
        return $this->sendTemplate(
            $email,
            'รีเซ็ตรหัสผ่าน — ' . $this->siteName(),
            'emails.customer.password-reset',
            [
                'name'     => $name,
                'resetUrl' => $resetUrl,
            ],
            'password_reset'
        );
    }

    public function orderConfirmation(array $order, array $items): bool
    {
        $orderNumber = $order['order_number'] ?? $order['id'] ?? 'N/A';

        return $this->sendTemplate(
            $order['email'] ?? '',
            "ยืนยันคำสั่งซื้อ #{$orderNumber} — " . $this->siteName(),
            'emails.customer.order-confirmation',
            [
                'name'        => $order['name'] ?? 'ลูกค้า',
                'orderId'     => $order['id'] ?? null,
                'orderNumber' => $orderNumber,
                'orderDate'   => $order['created_at'] ?? now()->format('d/m/Y H:i'),
                'items'       => $items,
                'subtotal'    => $order['subtotal'] ?? null,
                'discount'    => $order['discount'] ?? 0,
                'tax'         => $order['tax'] ?? 0,
                'total'       => $order['total_amount'] ?? $order['total'] ?? 0,
                'paymentUrl'  => $order['payment_url'] ?? null,
            ],
            'order_confirmation'
        );
    }

    public function paymentSuccess(array $order): bool
    {
        $orderNumber = $order['order_number'] ?? $order['id'] ?? 'N/A';

        return $this->sendTemplate(
            $order['email'] ?? '',
            "ชำระเงินสำเร็จ #{$orderNumber} — " . $this->siteName(),
            'emails.customer.payment-success',
            [
                'name'          => $order['name'] ?? 'ลูกค้า',
                'orderId'       => $order['id'] ?? null,
                'orderNumber'   => $orderNumber,
                'total'         => $order['total_amount'] ?? $order['total'] ?? 0,
                'paymentMethod' => $order['payment_method'] ?? 'โอนเงิน',
                'paidAt'        => $order['paid_at'] ?? now()->format('d/m/Y H:i'),
                'downloadUrl'   => $order['download_url'] ?? null,
                'orderUrl'      => $order['order_url'] ?? null,
            ],
            'payment_success'
        );
    }

    public function paymentFailed(array $order, string $reason = ''): bool
    {
        $orderNumber = $order['order_number'] ?? $order['id'] ?? 'N/A';

        return $this->sendTemplate(
            $order['email'] ?? '',
            "การชำระเงินไม่สำเร็จ #{$orderNumber}",
            'emails.customer.payment-failed',
            [
                'name'           => $order['name'] ?? 'ลูกค้า',
                'orderId'        => $order['id'] ?? null,
                'orderNumber'    => $orderNumber,
                'total'          => $order['total_amount'] ?? $order['total'] ?? 0,
                'paymentMethod'  => $order['payment_method'] ?? 'ไม่ระบุ',
                'failureReason'  => $reason,
                'retryUrl'       => $order['retry_url'] ?? url('/payment/retry/' . ($order['id'] ?? '')),
            ],
            'payment_failed'
        );
    }

    public function slipApproved(array $order): bool
    {
        $orderNumber = $order['order_number'] ?? $order['id'] ?? 'N/A';

        return $this->sendTemplate(
            $order['email'] ?? '',
            "สลิปโอนเงินได้รับการอนุมัติ #{$orderNumber}",
            'emails.customer.slip-approved',
            [
                'name'        => $order['name'] ?? 'ลูกค้า',
                'orderId'     => $order['id'] ?? null,
                'orderNumber' => $orderNumber,
                'total'       => $order['total_amount'] ?? $order['total'] ?? 0,
                'approvedAt'  => now()->format('d/m/Y H:i'),
                'downloadUrl' => $order['download_url'] ?? null,
            ],
            'slip_approved'
        );
    }

    public function slipRejected(array $order, string $reason): bool
    {
        $orderNumber = $order['order_number'] ?? $order['id'] ?? 'N/A';

        return $this->sendTemplate(
            $order['email'] ?? '',
            "สลิปโอนเงินไม่ผ่าน #{$orderNumber}",
            'emails.customer.slip-rejected',
            [
                'name'        => $order['name'] ?? 'ลูกค้า',
                'orderId'     => $order['id'] ?? null,
                'orderNumber' => $orderNumber,
                'reason'      => $reason,
                'retryUrl'    => $order['retry_url'] ?? url('/orders/' . ($order['id'] ?? '')),
            ],
            'slip_rejected'
        );
    }

    public function downloadReady(array $order, string $downloadUrl, array $extra = []): bool
    {
        $orderNumber = $order['order_number'] ?? $order['id'] ?? 'N/A';

        return $this->sendTemplate(
            $order['email'] ?? '',
            "ภาพพร้อมดาวน์โหลด #{$orderNumber} — " . $this->siteName(),
            'emails.customer.download-ready',
            [
                'name'        => $order['name'] ?? 'ลูกค้า',
                'orderId'     => $order['id'] ?? null,
                'orderNumber' => $orderNumber,
                'downloadUrl' => $downloadUrl,
                'eventName'   => $extra['event_name'] ?? null,
                'photoCount'  => $extra['photo_count'] ?? 0,
                'expiresAt'   => $extra['expires_at'] ?? now()->addDays(7)->format('d/m/Y H:i'),
            ],
            'download_ready'
        );
    }

    public function invoice(array $order, array $items, ?string $invoicePdfUrl = null): bool
    {
        $orderNumber   = $order['order_number'] ?? $order['id'] ?? 'N/A';
        $invoiceNumber = $order['invoice_number'] ?? $orderNumber;

        return $this->sendTemplate(
            $order['email'] ?? '',
            "ใบเสร็จอิเล็กทรอนิกส์ INV-{$invoiceNumber}",
            'emails.customer.invoice',
            [
                'name'           => $order['name'] ?? 'ลูกค้า',
                'orderId'        => $order['id'] ?? null,
                'orderNumber'    => $orderNumber,
                'invoiceNumber'  => $invoiceNumber,
                'invoiceDate'    => $order['invoice_date'] ?? now()->format('d/m/Y'),
                'items'          => $items,
                'subtotal'       => $order['subtotal'] ?? null,
                'discount'       => $order['discount'] ?? 0,
                'tax'            => $order['tax'] ?? 0,
                'total'          => $order['total_amount'] ?? $order['total'] ?? 0,
                'companyName'    => $order['company_name'] ?? null,
                'taxId'          => $order['tax_id'] ?? null,
                'invoicePdfUrl'  => $invoicePdfUrl,
            ],
            'invoice'
        );
    }

    public function refundProcessed(array $order, float $amount, string $reason = '', string $refundMethod = 'บัญชีธนาคาร'): bool
    {
        $orderNumber = $order['order_number'] ?? $order['id'] ?? 'N/A';

        return $this->sendTemplate(
            $order['email'] ?? '',
            "การคืนเงิน ฿" . number_format($amount, 2) . " #{$orderNumber}",
            'emails.customer.refund-processed',
            [
                'name'         => $order['name'] ?? 'ลูกค้า',
                'orderId'      => $order['id'] ?? null,
                'orderNumber'  => $orderNumber,
                'amount'       => $amount,
                'refundMethod' => $refundMethod,
                'reason'       => $reason,
                'processedAt'  => now()->format('d/m/Y H:i'),
            ],
            'refund_processed'
        );
    }

    public function reviewReminder(string $email, string $name, array $order, string $reviewUrl): bool
    {
        $orderNumber = $order['order_number'] ?? $order['id'] ?? 'N/A';

        return $this->sendTemplate(
            $email,
            "ช่วยรีวิวประสบการณ์ของคุณ #{$orderNumber}",
            'emails.customer.review-reminder',
            [
                'name'             => $name,
                'orderId'          => $order['id'] ?? null,
                'orderNumber'      => $orderNumber,
                'eventName'        => $order['event_name'] ?? null,
                'photographerName' => $order['photographer_name'] ?? null,
                'reviewUrl'        => $reviewUrl,
            ],
            'review_reminder'
        );
    }

    public function contactReply(string $email, string $name, string $subject, string $replyMessage, array $extra = []): bool
    {
        return $this->sendTemplate(
            $email,
            "Re: {$subject}",
            'emails.customer.contact-reply',
            [
                'name'            => $name,
                'subject'         => $subject,
                'replyMessage'    => $replyMessage,
                'ticketId'        => $extra['ticket_id'] ?? null,
                'repliedBy'       => $extra['replied_by'] ?? 'ทีมงาน',
                'repliedAt'       => $extra['replied_at'] ?? now()->format('d/m/Y H:i'),
                'originalMessage' => $extra['original_message'] ?? null,
                'contactUrl'      => url('/contact'),
            ],
            'contact_reply'
        );
    }

    /**
     * Email the customer a summary of photos matched by face-search.
     *
     * IMPORTANT — PDPA note: email delivery is a SEPARATE opt-in from the
     * biometric consent for face-search itself. The caller must verify the
     * user ticked the "email me these photos" box before calling this.
     *
     * @param array $matches Array of ['photo_id', 'thumbnail_url', 'view_url', 'similarity']
     */
    public function faceSearchResults(string $email, string $name, array $event, array $matches): bool
    {
        $eventName  = $event['name']  ?? 'อีเวนต์';
        $eventSlug  = $event['slug']  ?? '';
        $photoCount = count($matches);

        return $this->sendTemplate(
            $email,
            "พบภาพของคุณ {$photoCount} ภาพ — {$eventName}",
            'emails.customer.face-search-results',
            [
                'name'       => $name,
                'eventName'  => $eventName,
                'eventSlug'  => $eventSlug,
                'eventUrl'   => $eventSlug ? url("/events/{$eventSlug}") : null,
                'searchUrl'  => $eventSlug ? url("/events/{$eventSlug}/face-search") : null,
                'matches'    => $matches,
                'photoCount' => $photoCount,
                'searchedAt' => now()->format('d/m/Y H:i'),
            ],
            'face_search_results'
        );
    }

    /* ═════════════════════════════════════════════════════════════════
     *  ABANDONED CART RECOVERY
     * ═════════════════════════════════════════════════════════════════ */

    /**
     * 1st abandoned-cart reminder (sent ~1h after abandonment, no discount).
     *
     * Expected $data keys: itemCount, total, items, recoveryUrl
     */
    public function abandonedCartReminder1(string $email, string $name, array $data): bool
    {
        return $this->sendTemplate(
            $email,
            "คุณยังมีสินค้าในตะกร้า — " . $this->siteName(),
            'emails.customer.abandoned-cart-reminder',
            [
                'name'        => $name,
                'itemCount'   => (int) ($data['itemCount'] ?? 0),
                'total'       => (float) ($data['total'] ?? 0),
                'items'       => $data['items'] ?? [],
                'recoveryUrl' => $data['recoveryUrl'] ?? url('/cart'),
            ],
            'abandoned_cart_reminder_1'
        );
    }

    /**
     * 2nd abandoned-cart reminder (sent ~24h after 1st, includes discount code).
     *
     * Expected $data keys: itemCount, total, items, recoveryUrl, discountCode, discountPct
     */
    public function abandonedCartReminder2(string $email, string $name, array $data): bool
    {
        $discountPct = (int) ($data['discountPct'] ?? 10);

        return $this->sendTemplate(
            $email,
            "🎁 รับส่วนลด {$discountPct}% — กลับมาชอปกันเถอะ!",
            'emails.customer.abandoned-cart-reminder-2',
            [
                'name'         => $name,
                'itemCount'    => (int) ($data['itemCount'] ?? 0),
                'total'        => (float) ($data['total'] ?? 0),
                'items'        => $data['items'] ?? [],
                'recoveryUrl'  => $data['recoveryUrl'] ?? url('/cart'),
                'discountCode' => $data['discountCode'] ?? 'COMEBACK10',
                'discountPct'  => $discountPct,
            ],
            'abandoned_cart_reminder_2'
        );
    }

    /* ═════════════════════════════════════════════════════════════════
     *  REFUND WORKFLOW
     * ═════════════════════════════════════════════════════════════════ */

    /**
     * Confirmation email when a customer submits a refund request.
     *
     * Expected $request keys: request_number, order_number, requested_amount,
     *                          reason, description, created_at
     */
    public function refundRequestReceived(string $email, string $name, array $request): bool
    {
        $orderNumber   = $request['order_number']  ?? 'N/A';
        $requestNumber = $request['request_number'] ?? 'N/A';

        return $this->sendTemplate(
            $email,
            "ได้รับคำขอคืนเงิน {$requestNumber} #{$orderNumber}",
            'emails.customer.refund-request-received',
            [
                'name'             => $name,
                'requestNumber'    => $requestNumber,
                'orderNumber'      => $orderNumber,
                'requestedAmount'  => (float) ($request['requested_amount'] ?? 0),
                'reason'           => $request['reason']      ?? '-',
                'description'      => $request['description'] ?? null,
                'createdAt'        => $request['created_at']  ?? now()->format('d/m/Y H:i'),
            ],
            'refund_request_received'
        );
    }

    /**
     * Notify customer that their refund request was approved.
     *
     * Expected $request keys: request_number, order_number, approved_amount,
     *                          admin_note, approved_at
     */
    public function refundApproved(string $email, string $name, array $request): bool
    {
        $orderNumber   = $request['order_number']  ?? 'N/A';
        $requestNumber = $request['request_number'] ?? 'N/A';
        $amount        = (float) ($request['approved_amount'] ?? 0);

        return $this->sendTemplate(
            $email,
            "✅ คำขอคืนเงินได้รับการอนุมัติ ฿" . number_format($amount, 2) . " #{$orderNumber}",
            'emails.customer.refund-approved',
            [
                'name'            => $name,
                'requestNumber'   => $requestNumber,
                'orderNumber'     => $orderNumber,
                'approvedAmount'  => $amount,
                'adminNote'       => $request['admin_note']  ?? null,
                'approvedAt'      => $request['approved_at'] ?? now()->format('d/m/Y H:i'),
            ],
            'refund_approved'
        );
    }

    /**
     * Notify customer that their refund request was rejected.
     *
     * Expected $request keys: request_number, order_number, requested_amount, rejected_at
     */
    public function refundRejected(string $email, string $name, array $request, string $reason): bool
    {
        $orderNumber   = $request['order_number']  ?? 'N/A';
        $requestNumber = $request['request_number'] ?? 'N/A';

        return $this->sendTemplate(
            $email,
            "คำขอคืนเงินไม่ได้รับการอนุมัติ #{$orderNumber}",
            'emails.customer.refund-rejected',
            [
                'name'             => $name,
                'requestNumber'    => $requestNumber,
                'orderNumber'      => $orderNumber,
                'requestedAmount'  => (float) ($request['requested_amount'] ?? 0),
                'rejectedAt'       => $request['rejected_at'] ?? now()->format('d/m/Y H:i'),
                'reason'           => $reason,
            ],
            'refund_rejected'
        );
    }

    /* ═════════════════════════════════════════════════════════════════
     *  PHOTOGRAPHER EMAILS
     * ═════════════════════════════════════════════════════════════════ */

    public function photographerWelcome(string $email, string $name, array $extra = []): bool
    {
        return $this->sendTemplate(
            $email,
            "ยินดีต้อนรับช่างภาพ — " . $this->siteName(),
            'emails.photographer.welcome',
            [
                'name'           => $name,
                'dashboardUrl'   => url('/photographer'),
                'commissionRate' => $extra['commission_rate'] ?? 70,
            ],
            'photographer_welcome'
        );
    }

    public function photographerApproved(string $email, string $name): bool
    {
        return $this->sendTemplate(
            $email,
            "บัญชีช่างภาพได้รับการอนุมัติแล้ว! — " . $this->siteName(),
            'emails.photographer.approved',
            [
                'name'         => $name,
                'dashboardUrl' => url('/photographer'),
            ],
            'photographer_approved'
        );
    }

    /**
     * "Your photos are about to be auto-deleted" warning.
     *
     * $events is an array of:
     *   ['name' => string, 'delete_at' => string, 'photo_count' => int]
     *
     * $hoursLeft is used by the template to render the urgency banner.
     */
    public function photographerCleanupWarning(string $email, string $name, array $events, int $hoursLeft = 24): bool
    {
        $firstEventName = $events[0]['name'] ?? 'event';
        $suffix = count($events) > 1 ? " และอีเวนต์อื่น ๆ อีก " . (count($events) - 1) . " อีเวนต์" : '';

        return $this->sendTemplate(
            $email,
            "⏰ แจ้งเตือน: รูปใน '{$firstEventName}'{$suffix} จะถูกลบใน {$hoursLeft} ชั่วโมง",
            'emails.photographer.cleanup-warning',
            [
                'name'         => $name,
                'events'       => $events,
                'hoursLeft'    => $hoursLeft,
                'dashboardUrl' => url('/photographer'),
                'upgradeUrl'   => url('/photographer/upgrade'),
            ],
            'photographer_cleanup_warning'
        );
    }

    public function photographerRejected(string $email, string $name, string $reason = ''): bool
    {
        return $this->sendTemplate(
            $email,
            "การสมัครช่างภาพไม่ได้รับการอนุมัติ",
            'emails.photographer.rejected',
            [
                'name'       => $name,
                'reason'     => $reason,
                'contactUrl' => url('/contact'),
            ],
            'photographer_rejected'
        );
    }

    public function photographerNewSale(string $email, string $name, array $sale): bool
    {
        return $this->sendTemplate(
            $email,
            "💰 ยอดขายใหม่ ฿" . number_format((float)($sale['commission'] ?? 0), 2),
            'emails.photographer.new-sale',
            [
                'name'           => $name,
                'saleAmount'     => $sale['sale_amount'] ?? 0,
                'commission'     => $sale['commission'] ?? 0,
                'commissionRate' => $sale['commission_rate'] ?? 70,
                'eventName'      => $sale['event_name'] ?? null,
                'photoCount'     => $sale['photo_count'] ?? 1,
                'customerName'   => $sale['customer_name'] ?? 'ลูกค้า',
                'saleDate'       => $sale['sale_date'] ?? now()->format('d/m/Y H:i'),
                'stats'          => $sale['stats'] ?? [],
                'dashboardUrl'   => url('/photographer/earnings'),
            ],
            'photographer_sale'
        );
    }

    public function photographerPayoutSent(string $email, string $name, array $payout): bool
    {
        return $this->sendTemplate(
            $email,
            "รายได้ ฿" . number_format((float)($payout['amount'] ?? 0), 2) . " โอนเข้าบัญชีแล้ว",
            'emails.photographer.payout-notification',
            [
                'name'            => $name,
                'amount'          => $payout['amount'] ?? 0,
                'orderCount'      => $payout['order_count'] ?? 0,
                'period'          => $payout['period'] ?? null,
                'bankName'        => $payout['bank_name'] ?? null,
                'accountLast4'    => $payout['account_last4'] ?? null,
                'transferDate'    => $payout['transfer_date'] ?? now()->format('d/m/Y H:i'),
                'referenceNumber' => $payout['reference'] ?? null,
                'grossSales'      => $payout['gross_sales'] ?? 0,
                'platformFee'     => $payout['platform_fee'] ?? 0,
                'adjustments'     => $payout['adjustments'] ?? 0,
                'statementUrl'    => $payout['statement_url'] ?? null,
            ],
            'photographer_payout'
        );
    }

    public function photographerPayoutFailed(string $email, string $name, array $payout, string $reason = ''): bool
    {
        return $this->sendTemplate(
            $email,
            "⚠️ การโอนเงินรายได้ไม่สำเร็จ",
            'emails.photographer.payout-failed',
            [
                'name'      => $name,
                'amount'    => $payout['amount'] ?? 0,
                'period'    => $payout['period'] ?? null,
                'reason'    => $reason,
                'updateUrl' => url('/photographer/profile/setup-bank'),
            ],
            'photographer_payout_failed'
        );
    }

    /**
     * Notify the photographer that one of their photos was rejected by AI moderation + admin review.
     *
     * Expected $data keys:
     *   - photo_id       (int)
     *   - event_name     (string)
     *   - filename       (string)
     *   - reason         (string) — admin-provided rejection note
     *   - labels         (array)  — top Rekognition labels [['name' => ..., 'confidence' => ...]]
     *   - rejected_at    (string) — formatted timestamp
     *   - appeal_url     (string, optional) — link for the photographer to respond/appeal
     */
    public function photoRejected(string $email, string $name, array $data): bool
    {
        $eventName = $data['event_name'] ?? 'อีเวนต์';
        $photoId   = $data['photo_id']  ?? 'N/A';

        return $this->sendTemplate(
            $email,
            "⚠️ ภาพของคุณถูกปฏิเสธ — {$eventName} (Photo #{$photoId})",
            'emails.photographer.photo-rejected',
            [
                'name'       => $name,
                'photoId'    => $photoId,
                'eventName'  => $eventName,
                'filename'   => $data['filename']    ?? null,
                'reason'     => $data['reason']      ?? null,
                'labels'     => $data['labels']      ?? [],
                'rejectedAt' => $data['rejected_at'] ?? now()->format('d/m/Y H:i'),
                'appealUrl'  => $data['appeal_url']  ?? url('/photographer/photos'),
            ],
            'photo_rejected'
        );
    }

    public function photographerNewReview(string $email, string $name, array $review): bool
    {
        return $this->sendTemplate(
            $email,
            "⭐ ลูกค้ารีวิวคุณ {$review['rating']}/5",
            'emails.photographer.new-review',
            [
                'name'         => $name,
                'rating'       => $review['rating'] ?? 5,
                'comment'      => $review['comment'] ?? '',
                'customerName' => $review['customer_name'] ?? 'ลูกค้า',
                'eventName'    => $review['event_name'] ?? null,
                'reviewDate'   => $review['created_at'] ?? now()->format('d/m/Y H:i'),
                'replyUrl'     => $review['reply_url'] ?? url('/photographer/reviews'),
            ],
            'photographer_review'
        );
    }

    /**
     * Legacy compatibility: payout notification (simpler signature).
     */
    public function payoutNotification(string $email, string $name, float $amount, int $count): bool
    {
        return $this->photographerPayoutSent($email, $name, [
            'amount'      => $amount,
            'order_count' => $count,
        ]);
    }

    /* ═════════════════════════════════════════════════════════════════
     *  ADMIN ALERT EMAILS
     * ═════════════════════════════════════════════════════════════════ */

    public function adminNewOrderAlert(string $adminEmail, array $order, array $items): bool
    {
        $orderNumber = $order['order_number'] ?? $order['id'] ?? 'N/A';

        return $this->sendTemplate(
            $adminEmail,
            "🛒 คำสั่งซื้อใหม่ #{$orderNumber} — ฿" . number_format((float)($order['total'] ?? 0), 2),
            'emails.admin.new-order-alert',
            [
                'orderId'        => $order['id'] ?? null,
                'orderNumber'    => $orderNumber,
                'total'          => $order['total'] ?? 0,
                'customerName'   => $order['customer_name'] ?? 'N/A',
                'customerEmail'  => $order['customer_email'] ?? 'N/A',
                'customerPhone'  => $order['customer_phone'] ?? 'N/A',
                'paymentMethod'  => $order['payment_method'] ?? 'รอเลือก',
                'statusLabel'    => $order['status_label'] ?? 'รอดำเนินการ',
                'orderDate'      => $order['order_date'] ?? now()->format('d/m/Y H:i'),
                'items'          => $items,
                'adminOrderUrl'  => $order['admin_url'] ?? url('/admin/orders/' . ($order['id'] ?? '')),
            ],
            'admin_new_order'
        );
    }

    public function adminNewContactAlert(string $adminEmail, array $message): bool
    {
        return $this->sendTemplate(
            $adminEmail,
            "📨 ข้อความใหม่จาก {$message['sender_name']}",
            'emails.admin.new-contact-alert',
            [
                'senderName'      => $message['sender_name'] ?? 'ไม่ระบุ',
                'senderEmail'     => $message['sender_email'] ?? 'N/A',
                'senderPhone'     => $message['sender_phone'] ?? null,
                'subject'         => $message['subject'] ?? 'ไม่ระบุ',
                'category'        => $message['category'] ?? null,
                'message'         => $message['message'] ?? '',
                'sentAt'          => $message['created_at'] ?? now()->format('d/m/Y H:i'),
                'ipAddress'       => $message['ip_address'] ?? null,
                'adminMessageUrl' => url('/admin/messages/' . ($message['id'] ?? '')),
            ],
            'admin_new_contact'
        );
    }

    public function adminNewPhotographerAlert(string $adminEmail, array $photographer): bool
    {
        return $this->sendTemplate(
            $adminEmail,
            "📸 ช่างภาพใหม่สมัครเข้ามา: {$photographer['name']}",
            'emails.admin.new-photographer-alert',
            [
                'photographerName' => $photographer['name'] ?? 'N/A',
                'email'            => $photographer['email'] ?? 'N/A',
                'phone'            => $photographer['phone'] ?? null,
                'portfolioUrl'     => $photographer['portfolio_url'] ?? null,
                'bio'              => $photographer['bio'] ?? null,
                'registeredAt'     => $photographer['created_at'] ?? now()->format('d/m/Y H:i'),
                'adminReviewUrl'   => url('/admin/photographers/' . ($photographer['id'] ?? '')),
            ],
            'admin_new_photographer'
        );
    }

    public function adminSlipPendingAlert(string $adminEmail, array $slip): bool
    {
        return $this->sendTemplate(
            $adminEmail,
            "📄 สลิปรอตรวจ #{$slip['order_number']} — ฿" . number_format((float)($slip['total'] ?? 0), 2),
            'emails.admin.slip-pending-alert',
            [
                'orderId'           => $slip['order_id'] ?? null,
                'orderNumber'       => $slip['order_number'] ?? 'N/A',
                'total'             => $slip['total'] ?? 0,
                'customerName'      => $slip['customer_name'] ?? 'N/A',
                'bankName'          => $slip['bank_name'] ?? null,
                'refCode'           => $slip['ref_code'] ?? null,
                'uploadedAt'        => $slip['uploaded_at'] ?? now()->format('d/m/Y H:i'),
                'slipVerification'  => $slip['verification_status'] ?? 'pending',
                'adminSlipUrl'      => url('/admin/payments/slips'),
            ],
            'admin_slip_pending'
        );
    }

    public function adminRefundRequestAlert(string $adminEmail, array $refund): bool
    {
        $orderNumber = $refund['order_number'] ?? $refund['order_id'] ?? 'N/A';

        return $this->sendTemplate(
            $adminEmail,
            "💸 คำขอคืนเงิน ฿" . number_format((float)($refund['amount'] ?? 0), 2) . " #{$orderNumber}",
            'emails.admin.refund-request-alert',
            [
                'orderId'         => $refund['order_id'] ?? null,
                'orderNumber'     => $orderNumber,
                'amount'          => $refund['amount'] ?? 0,
                'customerName'    => $refund['customer_name'] ?? 'N/A',
                'customerEmail'   => $refund['customer_email'] ?? 'N/A',
                'paymentMethod'   => $refund['payment_method'] ?? 'N/A',
                'reason'          => $refund['reason'] ?? 'ไม่ระบุเหตุผล',
                'requestedAt'     => $refund['requested_at'] ?? now()->format('d/m/Y H:i'),
                'adminRefundUrl'  => url('/admin/finance/refunds'),
            ],
            'admin_refund_request'
        );
    }

    public function adminDailySummary(string $adminEmail, array $summary): bool
    {
        $date = $summary['date'] ?? now()->subDay()->format('d/m/Y');

        return $this->sendTemplate(
            $adminEmail,
            "📊 สรุปรายงานประจำวันที่ {$date}",
            'emails.admin.daily-summary',
            array_merge([
                'date'                  => $date,
                'dashboardUrl'          => url('/admin/dashboard'),
            ], $summary),
            'admin_daily_summary'
        );
    }

    public function adminSecurityAlert(string $adminEmail, array $alert): bool
    {
        return $this->sendTemplate(
            $adminEmail,
            "🚨 Security Alert: {$alert['alert_type']}",
            'emails.admin.security-alert',
            [
                'alertType'             => $alert['alert_type'] ?? 'unknown',
                'severity'              => $alert['severity'] ?? 'medium',
                'ipAddress'             => $alert['ip_address'] ?? null,
                'country'               => $alert['country'] ?? null,
                'userAgent'             => $alert['user_agent'] ?? null,
                'attemptedAccount'      => $alert['attempted_account'] ?? null,
                'description'           => $alert['description'] ?? null,
                'detectedAt'            => $alert['detected_at'] ?? now()->format('d/m/Y H:i:s'),
                'securityDashboardUrl'  => url('/admin/security/dashboard'),
            ],
            'admin_security'
        );
    }

    /* ═════════════════════════════════════════════════════════════════
     *  TEST & UTILITY
     * ═════════════════════════════════════════════════════════════════ */

    public function sendTestEmail(string $to): bool
    {
        $driver = AppSetting::get('mail_driver', config('mail.default', 'log'));

        return $this->sendTemplate(
            $to,
            "Test Email — " . $this->siteName(),
            'emails.test',
            [
                'to'     => $to,
                'driver' => $driver,
                'sentAt' => now()->format('Y-m-d H:i:s'),
            ],
            'test'
        );
    }

    /* ═════════════════════════════════════════════════════════════════
     *  INTERNAL HELPERS
     * ═════════════════════════════════════════════════════════════════ */

    /**
     * Common view data injected into every email template.
     */
    private function defaultViewData(): array
    {
        return [
            'siteName'     => $this->siteName(),
            'siteUrl'      => rtrim(config('app.url', url('/')), '/'),
            'supportEmail' => AppSetting::get('support_email', AppSetting::get('mail_from_email', 'support@example.com')),
        ];
    }

    private function siteName(): string
    {
        $name = AppSetting::get('site_name', '');
        return $name !== '' ? $name : (string) config('app.name', 'Photo Gallery');
    }

    /**
     * Apply mail settings from DB to Laravel's runtime config.
     */
    private function applyMailConfig(string $driver): void
    {
        $host       = AppSetting::get('smtp_host', '127.0.0.1');
        $port       = (int) AppSetting::get('smtp_port', 587);
        $encryption = AppSetting::get('smtp_encryption', 'tls');
        $username   = AppSetting::get('smtp_username', '');
        $password   = AppSetting::get('smtp_password', '');
        $fromEmail  = AppSetting::get('mail_from_email', config('mail.from.address', 'noreply@example.com'));
        $fromName   = AppSetting::get('mail_from_name', config('mail.from.name', config('app.name')));

        config([
            'mail.default'                 => $driver,
            'mail.mailers.smtp.host'       => $host,
            'mail.mailers.smtp.port'       => $port,
            'mail.mailers.smtp.encryption' => $encryption ?: null,
            'mail.mailers.smtp.username'   => $username,
            'mail.mailers.smtp.password'   => $password,
            'mail.from.address'            => $fromEmail,
            'mail.from.name'               => $fromName,
        ]);
    }

    /**
     * Log an email send attempt to the email_logs table.
     */
    private function log(
        string  $to,
        string  $subject,
        string  $type,
        string  $status,
        ?string $errorMessage,
        ?string $driver
    ): void {
        try {
            if (!Schema::hasTable('email_logs')) {
                return;
            }

            EmailLog::create([
                'to_email'      => $to,
                'subject'       => $subject,
                'type'          => $type,
                'status'        => $status,
                'error_message' => $errorMessage,
                'driver'        => $driver,
                'created_at'    => now(),
            ]);
        } catch (\Throwable $e) {
            // Logging must never break the application
        }
    }
}

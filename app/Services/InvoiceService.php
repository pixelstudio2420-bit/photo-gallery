<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;

class InvoiceService
{
    /**
     * Generate invoice PDF for an order.
     */
    public function generatePdf(Order $order): \Barryvdh\DomPDF\PDF
    {
        $order->load(['items', 'user', 'event']);

        $data = [
            'order'    => $order,
            'siteName' => (AppSetting::get('site_name') ?: config('app.name')),
            'siteUrl'  => url('/'),
            'date'     => $order->created_at->format('d/m/Y'),
            'invoiceNo'=> 'INV-' . str_pad($order->id, 6, '0', STR_PAD_LEFT),
        ];

        $pdf = Pdf::loadView('invoices.receipt', $data);
        $pdf->setPaper('A4', 'portrait');

        return $pdf;
    }

    /**
     * Send invoice email to customer.
     */
    public function sendEmail(Order $order): bool
    {
        $order->load(['items', 'user', 'event']);

        $user = $order->user;
        if (!$user || !$user->email) {
            return false;
        }

        $invoiceNo = 'INV-' . str_pad($order->id, 6, '0', STR_PAD_LEFT);
        $pdf = $this->generatePdf($order);
        $pdfContent = $pdf->output();

        $siteName = (AppSetting::get('site_name') ?: config('app.name'));
        $name = htmlspecialchars($user->name ?? 'Customer', ENT_QUOTES);
        $total = number_format((float) $order->total, 2);

        $itemRows = '';
        foreach ($order->items as $item) {
            $itemName = htmlspecialchars($item->description ?? $item->file_name ?? 'Photo', ENT_QUOTES);
            $qty = (int) ($item->quantity ?? 1);
            $price = number_format((float) ($item->price ?? 0), 2);
            $itemRows .= "<div style='display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid #e9ecef;font-size:14px;'>"
                . "<span style='color:#6b7280;font-weight:500;'>{$itemName} x {$qty}</span>"
                . "<span style='color:#111827;font-weight:600;'>฿{$price}</span></div>";
        }

        $body = <<<HTML
<h2>ใบเสร็จรับเงิน</h2>
<p>สวัสดีค่ะ/ครับ <strong>{$name}</strong> ขอบคุณสำหรับคำสั่งซื้อของคุณ ใบเสร็จแนบมาในอีเมลนี้</p>
<div style="background:#f8f9fa;border-radius:8px;padding:16px 20px;margin:20px 0;">
  <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid #e9ecef;font-size:14px;"><span style="color:#6b7280;font-weight:500;">เลขที่ใบเสร็จ</span><span style="color:#111827;font-weight:600;">{$invoiceNo}</span></div>
  {$itemRows}
  <div style="display:flex;justify-content:space-between;padding:8px 0;font-size:16px;"><span style="font-weight:700;">ยอดรวม</span><span style="font-weight:700;color:#6366f1;">฿{$total}</span></div>
</div>
<p style="color:#6b7280;font-size:13px;">ไฟล์ PDF ใบเสร็จแนบอยู่ในอีเมลนี้ หากมีข้อสงสัยกรุณาติดต่อเรา</p>
HTML;

        // Send with PDF attachment
        $enabled = AppSetting::get('mail_enabled', '0');
        if ($enabled !== '1') {
            return false;
        }

        try {
            $fromEmail = AppSetting::get('mail_from_email', config('mail.from.address', 'noreply@example.com'));
            $fromName = AppSetting::get('mail_from_name', config('mail.from.name', config('app.name')));

            // Apply mail config
            $driver = AppSetting::get('mail_driver', config('mail.default', 'log'));
            config([
                'mail.default' => $driver,
                'mail.mailers.smtp.host' => AppSetting::get('smtp_host', '127.0.0.1'),
                'mail.mailers.smtp.port' => (int) AppSetting::get('smtp_port', 587),
                'mail.mailers.smtp.encryption' => AppSetting::get('smtp_encryption', 'tls') ?: null,
                'mail.mailers.smtp.username' => AppSetting::get('smtp_username', ''),
                'mail.mailers.smtp.password' => AppSetting::get('smtp_password', ''),
                'mail.from.address' => $fromEmail,
                'mail.from.name' => $fromName,
            ]);

            $htmlBody = $this->wrapEmail("ใบเสร็จรับเงิน — {$invoiceNo}", $body);

            \Illuminate\Support\Facades\Mail::html($htmlBody, function ($message) use ($user, $invoiceNo, $pdfContent, $fromEmail, $fromName, $siteName) {
                $message->to($user->email)
                    ->subject("ใบเสร็จรับเงิน {$invoiceNo} — {$siteName}")
                    ->from($fromEmail, $fromName)
                    ->attachData($pdfContent, "{$invoiceNo}.pdf", ['mime' => 'application/pdf']);
            });

            return true;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Invoice email failed: ' . $e->getMessage());
            return false;
        }
    }

    private function wrapEmail(string $title, string $bodyHtml): string
    {
        $siteName = (AppSetting::get('site_name') ?: config('app.name'));
        $year = date('Y');

        return <<<HTML
<!DOCTYPE html><html><head><meta charset="UTF-8"><title>{$title}</title></head>
<body style="margin:0;padding:0;background:#f4f6f8;font-family:'Segoe UI',Arial,sans-serif;color:#333;">
<div style="max-width:600px;margin:32px auto;background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.08);">
  <div style="background:linear-gradient(135deg,#6366f1,#4f46e5);padding:32px 40px;text-align:center;">
    <h1 style="margin:0;color:#fff;font-size:24px;font-weight:700;">{$siteName}</h1>
    <p style="margin:6px 0 0;color:rgba(255,255,255,.85);font-size:13px;">{$title}</p>
  </div>
  <div style="padding:36px 40px;">{$bodyHtml}</div>
  <div style="background:#f8f9fa;padding:20px 40px;text-align:center;border-top:1px solid #e9ecef;">
    <p style="margin:0;color:#9ca3af;font-size:12px;">&copy; {$year} {$siteName}. All rights reserved.</p>
  </div>
</div>
</body></html>
HTML;
    }
}

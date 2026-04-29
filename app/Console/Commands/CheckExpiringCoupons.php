<?php

namespace App\Console\Commands;

use App\Models\AdminNotification;
use App\Models\AppSetting;
use App\Models\Coupon;
use App\Services\MailService;
use Illuminate\Console\Command;

/**
 * Check for expiring coupons and notify admin.
 *
 * Schedule: daily at 9 AM
 *   $schedule->command('coupons:check-expiring')->dailyAt('09:00');
 */
class CheckExpiringCoupons extends Command
{
    protected $signature = 'coupons:check-expiring {--days=7 : Notify for coupons expiring within N days}';
    protected $description = 'ตรวจสอบคูปองที่ใกล้หมดอายุและแจ้งเตือน admin';

    public function handle(MailService $mail): int
    {
        $days = (int) $this->option('days');

        $expiring = Coupon::expiringSoon($days)->orderBy('end_date')->get();
        $expired = Coupon::expired()
            ->where('is_active', true)
            ->where('end_date', '>=', now()->subDay())
            ->get();

        $this->info("พบคูปองใกล้หมดอายุ ({$days} วัน): " . $expiring->count());
        $this->info("พบคูปองหมดอายุวันนี้: " . $expired->count());

        if ($expiring->count() === 0 && $expired->count() === 0) {
            return 0;
        }

        // Deactivate newly expired
        foreach ($expired as $c) {
            if ($c->is_active) {
                $c->update(['is_active' => false]);
                $this->line("  ปิดใช้งาน: {$c->code} (หมดอายุ {$c->end_date->format('d/m/Y H:i')})");
            }
        }

        // Admin notification for expiring
        if ($expiring->count() > 0) {
            try {
                AdminNotification::notify(
                    'system',
                    "⏰ คูปอง {$expiring->count()} รายการใกล้หมดอายุ",
                    "คูปองต่อไปนี้จะหมดอายุภายใน {$days} วัน: " . $expiring->pluck('code')->take(5)->implode(', '),
                    'admin/coupons?status=expiring'
                );
            } catch (\Throwable $e) {
                \Log::warning('Admin notification failed: ' . $e->getMessage());
            }
        }

        // Email summary to admin
        try {
            $adminEmail = AppSetting::get('admin_notification_email', AppSetting::get('mail_from_email'));
            if ($adminEmail && ($expiring->count() > 0 || $expired->count() > 0)) {
                $body = "<h2>รายงานคูปอง</h2>";

                if ($expiring->count() > 0) {
                    $body .= "<h3>⏰ ใกล้หมดอายุ ({$days} วัน):</h3><ul>";
                    foreach ($expiring as $c) {
                        $body .= "<li><strong>{$c->code}</strong> - {$c->name} (หมดอายุ " . $c->end_date->format('d/m/Y H:i') . " - " . $c->end_date->diffForHumans() . ")</li>";
                    }
                    $body .= "</ul>";
                }

                if ($expired->count() > 0) {
                    $body .= "<h3>🚫 หมดอายุแล้ว:</h3><ul>";
                    foreach ($expired as $c) {
                        $body .= "<li><strong>{$c->code}</strong> - {$c->name} (ใช้แล้ว {$c->usage_count} ครั้ง)</li>";
                    }
                    $body .= "</ul>";
                }

                $body .= '<p><a href="' . url('/admin/coupons/dashboard') . '">ดู Dashboard</a></p>';

                $mail->send(
                    $adminEmail,
                    "📊 รายงานคูปอง: " . ($expiring->count() + $expired->count()) . " รายการต้องการความสนใจ",
                    $body,
                    'coupon_expiry_report'
                );
                $this->info("ส่งอีเมลสรุปให้ admin เรียบร้อย");
            }
        } catch (\Throwable $e) {
            \Log::warning('Coupon expiry email failed: ' . $e->getMessage());
        }

        $this->info("เสร็จสิ้น");
        return 0;
    }
}

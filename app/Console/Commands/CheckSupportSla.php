<?php

namespace App\Console\Commands;

use App\Models\ContactMessage;
use Illuminate\Console\Command;

/**
 * Check support tickets for SLA breaches and send warnings.
 *
 * Run every 30 minutes via scheduler:
 *   $schedule->command('support:check-sla')->everyThirtyMinutes();
 */
class CheckSupportSla extends Command
{
    protected $signature = 'support:check-sla {--warn-minutes=30 : Minutes before deadline to warn}';
    protected $description = 'ตรวจสอบ SLA ของ tickets และแจ้งเตือนเมื่อใกล้เกินกำหนด';

    public function handle(): int
    {
        $warnMinutes = (int) $this->option('warn-minutes');
        $warnAt = now()->addMinutes($warnMinutes);

        // Find tickets approaching SLA deadline (warning phase)
        $approaching = ContactMessage::open()
            ->whereNotNull('sla_deadline')
            ->where('sla_deadline', '>', now())
            ->where('sla_deadline', '<=', $warnAt)
            ->whereDoesntHave('activities', function ($q) {
                $q->where('type', 'sla_warning')
                  ->where('created_at', '>', now()->subHour());
            })
            ->get();

        $this->info("พบ tickets ที่ใกล้เกินกำหนด: " . $approaching->count());

        foreach ($approaching as $t) {
            $this->line("  ⚠️  {$t->ticket_number} - {$t->subject} (เหลือ {$t->sla_deadline->diffForHumans(null, true)})");

            // Notify assigned admin
            if ($t->assigned_to_admin_id) {
                try {
                    \App\Models\AdminNotification::notify(
                        'support_sla',
                        "⏰ SLA ใกล้หมดเวลา: {$t->ticket_number}",
                        "{$t->subject} - เหลือ " . $t->sla_deadline->diffForHumans(null, true),
                        "admin/messages/{$t->id}",
                        (string) $t->id
                    );
                } catch (\Throwable $e) {}
            }

            $t->logActivity('sla_warning', null, [
                'remaining_minutes' => (int) now()->diffInMinutes($t->sla_deadline, false),
            ], "SLA ใกล้หมด - เหลือ " . $t->sla_deadline->diffForHumans(null, true));
        }

        // Find overdue tickets
        $overdue = ContactMessage::overdue()
            ->whereDoesntHave('activities', function ($q) {
                $q->where('type', 'sla_breach')
                  ->where('created_at', '>', now()->subHours(4));
            })
            ->get();

        $this->warn("พบ tickets ที่เกินกำหนดแล้ว: " . $overdue->count());

        foreach ($overdue as $t) {
            $this->line("  🚨 {$t->ticket_number} - {$t->subject} (เกินมา {$t->sla_deadline->diffForHumans(null, true)})");

            // Escalate priority if not urgent
            if ($t->priority !== 'urgent') {
                $oldPriority = $t->priority;
                $t->update(['priority' => 'urgent']);
                $t->logActivity('priority_changed', null, [
                    'old' => $oldPriority,
                    'new' => 'urgent',
                    'reason' => 'auto_escalated_sla_breach',
                ], 'ยกระดับความสำคัญเป็น Urgent เนื่องจากเกิน SLA');
            }

            // Alert admins via notification + email
            try {
                $adminEmail = \App\Models\AppSetting::get('admin_notification_email');
                if ($adminEmail) {
                    app(\App\Services\MailService::class)->send(
                        $adminEmail,
                        "🚨 SLA Breach: {$t->ticket_number}",
                        "<h2>SLA เกินกำหนด</h2>" .
                        "<p>Ticket: <strong>{$t->ticket_number}</strong></p>" .
                        "<p>Subject: {$t->subject}</p>" .
                        "<p>เกินกำหนดมา: " . $t->sla_deadline->diffForHumans(null, true) . "</p>" .
                        "<p><a href='" . url("/admin/messages/{$t->id}") . "'>ดูรายละเอียด</a></p>",
                        'sla_breach'
                    );
                }

                if ($t->assigned_to_admin_id) {
                    \App\Models\AdminNotification::notify(
                        'support_sla',
                        "🚨 SLA Breach: {$t->ticket_number}",
                        "Ticket นี้เกินกำหนด SLA แล้ว กรุณาดำเนินการด่วน!",
                        "admin/messages/{$t->id}",
                        (string) $t->id
                    );
                }
            } catch (\Throwable $e) {
                \Log::warning('SLA breach alert failed: ' . $e->getMessage());
            }

            $t->logActivity('sla_breach', null, [
                'hours_overdue' => (int) $t->sla_deadline->diffInHours(now()),
            ], 'เกิน SLA ' . $t->sla_deadline->diffForHumans(null, true));
        }

        $this->info("เสร็จสิ้น");
        return 0;
    }
}

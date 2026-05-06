<?php

namespace App\Services\Marketing;

use App\Models\AppSetting;
use App\Models\Marketing\Subscriber;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Newsletter subscriber lifecycle:
 *   subscribe → (double-optin?) → confirmed → unsubscribed/bounced
 *
 * All methods no-op when `marketing_newsletter_enabled` = 0.
 */
class NewsletterService
{
    public function __construct(protected MarketingService $marketing) {}

    public function enabled(): bool
    {
        return $this->marketing->enabled('newsletter');
    }

    public function doubleOptIn(): bool
    {
        return AppSetting::get('marketing_newsletter_double_optin', '1') === '1';
    }

    /**
     * Add someone to the list.
     * Returns the subscriber + a flag indicating if confirmation email should be sent.
     *
     * @return array{ok: bool, subscriber: ?Subscriber, needs_confirmation: bool, message: string}
     */
    public function subscribe(string $email, array $options = []): array
    {
        if (!$this->enabled()) {
            return ['ok' => false, 'subscriber' => null, 'needs_confirmation' => false, 'message' => 'Newsletter ปิดอยู่'];
        }

        $email = strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'subscriber' => null, 'needs_confirmation' => false, 'message' => 'อีเมลไม่ถูกต้อง'];
        }

        $sub = Subscriber::firstOrNew(['email' => $email]);

        // Re-activate if they previously unsubscribed
        if ($sub->exists && $sub->status === 'unsubscribed') {
            $sub->status = $this->doubleOptIn() ? 'pending' : 'confirmed';
            $sub->unsubscribed_at = null;
        }

        if (!$sub->exists) {
            $sub->status = $this->doubleOptIn() ? 'pending' : 'confirmed';
        }

        $sub->name          = $options['name']   ?? $sub->name;
        $sub->locale        = $options['locale'] ?? ($sub->locale ?: app()->getLocale());
        $sub->source        = $options['source'] ?? ($sub->source ?: 'widget');
        $sub->user_id       = $options['user_id'] ?? $sub->user_id;
        $sub->tags          = $options['tags'] ?? $sub->tags ?? [];
        $sub->meta          = array_merge($sub->meta ?? [], $options['meta'] ?? []);
        $sub->confirm_token = Subscriber::newConfirmToken();

        if ($sub->status === 'confirmed' && !$sub->confirmed_at) {
            $sub->confirmed_at = now();
        }
        $sub->save();

        $needsConfirm = $sub->status === 'pending';
        if ($needsConfirm) {
            $this->sendConfirmationEmail($sub);
        } elseif (AppSetting::get('marketing_newsletter_welcome_enabled', '0') === '1') {
            $this->sendWelcomeEmail($sub);
        }

        return [
            'ok' => true,
            'subscriber' => $sub,
            'needs_confirmation' => $needsConfirm,
            'message' => $needsConfirm
                ? 'กรุณาเช็คอีเมลเพื่อยืนยัน subscribe'
                : 'Subscribe สำเร็จ',
        ];
    }

    public function confirm(string $token): array
    {
        if (!$this->enabled()) {
            return ['ok' => false, 'message' => 'Newsletter ปิดอยู่'];
        }
        $sub = Subscriber::where('confirm_token', $token)->first();
        if (!$sub) {
            return ['ok' => false, 'message' => 'Token ไม่ถูกต้อง หรือหมดอายุ'];
        }
        if ($sub->isConfirmed()) {
            return ['ok' => true, 'subscriber' => $sub, 'message' => 'ยืนยันแล้ว'];
        }
        $sub->markConfirmed();

        if (AppSetting::get('marketing_newsletter_welcome_enabled', '0') === '1') {
            $this->sendWelcomeEmail($sub);
        }
        return ['ok' => true, 'subscriber' => $sub, 'message' => 'ยืนยันการ subscribe สำเร็จ'];
    }

    public function unsubscribe(string $email, ?string $reason = null): array
    {
        $email = strtolower(trim($email));
        $sub = Subscriber::where('email', $email)->first();
        if (!$sub) {
            return ['ok' => false, 'message' => 'ไม่พบอีเมลนี้ในระบบ'];
        }
        $sub->markUnsubscribed();
        if ($reason) {
            $sub->meta = array_merge($sub->meta ?? [], ['unsubscribe_reason' => $reason]);
            $sub->save();
        }
        return ['ok' => true, 'message' => 'ยกเลิกการรับอีเมลเรียบร้อย'];
    }

    // ── Email templates (simple markdown → queued mailable) ──

    protected function sendConfirmationEmail(Subscriber $sub): void
    {
        try {
            $from = $this->fromAddress();
            $url  = route('newsletter.confirm', ['token' => $sub->confirm_token]);
            Mail::html($this->confirmationBody($sub, $url), function ($m) use ($sub, $from) {
                $m->to($sub->email, $sub->name ?: null)
                  ->from($from['address'], $from['name'])
                  ->subject('[' . config('app.name') . '] กรุณายืนยันการ subscribe');
            });
        } catch (\Throwable $e) {
            Log::warning('newsletter.confirmation_failed', ['email' => $sub->email, 'err' => $e->getMessage()]);
        }
    }

    protected function sendWelcomeEmail(Subscriber $sub): void
    {
        try {
            $from = $this->fromAddress();
            Mail::html($this->welcomeBody($sub), function ($m) use ($sub, $from) {
                $m->to($sub->email, $sub->name ?: null)
                  ->from($from['address'], $from['name'])
                  ->subject('[' . config('app.name') . '] ยินดีต้อนรับสู่จดหมายข่าว');
            });
        } catch (\Throwable $e) {
            Log::warning('newsletter.welcome_failed', ['email' => $sub->email, 'err' => $e->getMessage()]);
        }
    }

    protected function fromAddress(): array
    {
        return [
            'address' => AppSetting::get('marketing_email_from_address', config('mail.from.address'))
                         ?: config('mail.from.address'),
            'name'    => AppSetting::get('marketing_email_from_name', config('mail.from.name'))
                         ?: (config('mail.from.name') ?? config('app.name')),
        ];
    }

    protected function confirmationBody(Subscriber $sub, string $confirmUrl): string
    {
        $name = e($sub->name ?: 'คุณ');
        $app  = e(config('app.name'));
        return <<<HTML
<div style="font-family:Arial,sans-serif;max-width:580px;margin:0 auto;padding:24px;color:#111">
    <h2 style="margin:0 0 16px">สวัสดี {$name} 👋</h2>
    <p>ขอบคุณที่สนใจรับจดหมายข่าวจาก <strong>{$app}</strong></p>
    <p>กรุณายืนยันอีเมลโดยคลิกปุ่มด้านล่าง:</p>
    <div style="text-align:center;margin:28px 0">
        <a href="{$confirmUrl}" style="display:inline-block;padding:12px 28px;background:#4f46e5;color:#fff;text-decoration:none;border-radius:8px;font-weight:600">ยืนยันการ Subscribe</a>
    </div>
    <p style="font-size:13px;color:#666">ถ้าปุ่มไม่ทำงาน วาง link นี้ใน browser:<br><a href="{$confirmUrl}">{$confirmUrl}</a></p>
    <hr style="border:0;border-top:1px solid #eee;margin:24px 0">
    <p style="font-size:12px;color:#999">หากคุณไม่ได้ subscribe email นี้ ข้ามไปได้เลย — เราจะไม่ส่งอีเมลอื่นถ้าไม่ยืนยัน</p>
</div>
HTML;
    }

    protected function welcomeBody(Subscriber $sub): string
    {
        $name = e($sub->name ?: 'คุณ');
        $app  = e(config('app.name'));
        $unsub = route('newsletter.unsubscribe', ['email' => $sub->email]);
        // Cadence statement matches the actual cron in routes/console.php:
        //   subscribers:send-weekly → Tuesdays 10:00, auto-skipped on
        //   weeks with no fresh events + no active promotions.
        return <<<HTML
<div style="font-family:Arial,sans-serif;max-width:580px;margin:0 auto;padding:24px;color:#111">
    <h2>ยินดีต้อนรับ {$name} 🎉</h2>
    <p>ตอนนี้คุณได้เข้าร่วมจดหมายข่าวของ <strong>{$app}</strong> เรียบร้อย</p>
    <p>เราจะสรุปอีเวนต์ใหม่ + โปรโมชั่น + เคล็ดลับ 1 ข้อ ส่งให้ทุกวันอังคาร —
    ถ้าสัปดาห์ไหนไม่มีของใหม่จริงๆ เราจะข้ามไป ไม่ส่งอีเมลเปล่า</p>
    <hr style="border:0;border-top:1px solid #eee;margin:24px 0">
    <p style="font-size:12px;color:#999">ไม่อยากรับอีเมล? <a href="{$unsub}">ยกเลิกการรับอีเมลที่นี่</a></p>
</div>
HTML;
    }
}

<?php

namespace App\Services\Notifications;

use App\Models\PhotographerSubscription;
use App\Models\SubscriptionPlan;

/**
 * Builds a {@see LifecycleMessage} for every photographer-billing event.
 *
 * One method per event kind so the wording is auditable in one place.
 * Channels (in-app/LINE/email) consume the resulting message and adapt
 * to their medium without reinventing the copy.
 *
 * Wording rules:
 *   • Always include the amount with `฿\xC2\xA0` (non-breaking space) so
 *     the symbol can never wrap onto a new line.
 *   • Always include a CTA URL — even idle informational messages should
 *     have a "ดูรายละเอียด" link for the photographer who wants more.
 *   • Use Thai dates (d/m/Y H:i) for human readability.
 *   • Critical events (renewal failed, expired, depleted) use red flex.
 *     Warnings use amber. Info uses indigo.
 */
class LifecycleMessageFormatter
{
    private function money(float $amount): string
    {
        return "฿\xC2\xA0" . number_format($amount, 0);
    }

    /* ───────────────── Subscription started (first time) ───────────────── */

    public function subscriptionStarted(PhotographerSubscription $sub): LifecycleMessage
    {
        $plan      = $sub->plan;
        $planName  = $plan?->name ?? 'แผน';
        $price     = $this->money((float) ($plan?->price_thb ?? 0));
        $renewsAt  = $sub->current_period_end;

        $bullets = [
            "ราคา: {$price}/" . $this->cycleLabel($plan?->billing_cycle),
            $renewsAt ? "ต่ออายุครั้งถัดไป: {$renewsAt->format('d/m/Y')}" : 'ต่ออายุอัตโนมัติ',
            "พื้นที่: " . $this->bytes((int) ($plan?->storage_bytes ?? 0)),
            "AI Credits: " . number_format((int) ($plan?->monthly_ai_credits ?? 0)) . '/เดือน',
        ];

        return new LifecycleMessage(
            kind:     LifecycleMessage::KIND_SUBSCRIPTION_STARTED,
            severity: LifecycleMessage::SEVERITY_INFO,
            headline: "🎉 เริ่มต้น{$planName} เรียบร้อย",
            shortBody: "ยินดีต้อนรับสู่{$planName} · " . ($renewsAt ? "ต่ออายุ {$renewsAt->format('d/m/Y')}" : 'ใช้งานได้แล้ว'),
            body:     "ขอบคุณที่สมัครใช้{$planName} — ระบบเปิดใช้งานเรียบร้อย "
                    . "คุณสามารถใช้ฟีเจอร์ทั้งหมดของแผนได้ทันที",
            bullets:  $bullets,
            cta:      ['label' => 'ดูแผนของฉัน', 'url' => url('/photographer/store/status')],
            subject:  "✅ เริ่มต้น{$planName} แล้ว — ยินดีต้อนรับ",
            flexBubble: $this->bubble("🎉 เริ่มต้น{$planName} แล้ว", $price, $bullets, '#10b981',
                                       'ดูแผนของฉัน', url('/photographer/store/status')),
            refId:    "sub.{$sub->id}.started",
        );
    }

    /* ───────────────── Subscription renewed ───────────────── */

    public function subscriptionRenewed(PhotographerSubscription $sub): LifecycleMessage
    {
        $plan     = $sub->plan;
        $planName = $plan?->name ?? 'แผน';
        $price    = $this->money((float) ($plan?->price_thb ?? 0));
        $newEnd   = $sub->current_period_end;

        $bullets = [
            "ยอดต่ออายุ: {$price}",
            $newEnd ? "ใช้ได้ถึง: {$newEnd->format('d/m/Y')}" : 'ใช้งานต่อเนื่อง',
            "ใบเสร็จ: ดูในประวัติการชำระเงิน",
        ];

        return new LifecycleMessage(
            kind:     LifecycleMessage::KIND_SUBSCRIPTION_RENEWED,
            severity: LifecycleMessage::SEVERITY_INFO,
            headline: "🔄 ต่ออายุ{$planName} เรียบร้อย",
            shortBody: "ระบบต่ออายุ{$planName}แล้ว · ใช้ได้ถึง " . ($newEnd?->format('d/m/Y') ?? '-'),
            body:     "ระบบหักเงิน {$price} เพื่อต่ออายุ{$planName}เรียบร้อย "
                    . "บริการของคุณยังคงเปิดใช้งานต่อเนื่อง",
            bullets:  $bullets,
            cta:      ['label' => 'ดูใบเสร็จ', 'url' => url('/photographer/subscription/invoices')],
            subject:  "🔄 ต่ออายุ{$planName} แล้ว · {$price}",
            flexBubble: $this->bubble("🔄 ต่ออายุ{$planName}", $price, $bullets, '#3b82f6',
                                       'ดูใบเสร็จ', url('/photographer/subscription/invoices')),
            refId:    "sub.{$sub->id}.renewed.{$sub->last_renewed_at?->format('Ymd')}",
        );
    }

    /* ───────────────── Subscription renewal failed ───────────────── */

    public function subscriptionRenewalFailed(PhotographerSubscription $sub, string $reason = ''): LifecycleMessage
    {
        $plan     = $sub->plan;
        $planName = $plan?->name ?? 'แผน';
        $graceEnd = $sub->grace_ends_at;
        $price    = $this->money((float) ($plan?->price_thb ?? 0));

        $bullets = [
            "ยอดที่ค้าง: {$price}",
            $reason !== '' ? "เหตุผล: {$reason}" : 'ไม่สามารถหักเงินได้ ตรวจสอบบัตรหรือยอดเงินในบัญชี',
            $graceEnd ? "ระบบจะปิดบริการวันที่: {$graceEnd->format('d/m/Y')}" : '',
            "การลองใหม่: ระบบจะลองหักอีก 2 ครั้งใน 3 วัน",
        ];
        $bullets = array_filter($bullets);

        return new LifecycleMessage(
            kind:     LifecycleMessage::KIND_SUBSCRIPTION_RENEWAL_FAIL,
            severity: LifecycleMessage::SEVERITY_CRITICAL,
            headline: "⚠️ ต่ออายุ{$planName}ไม่สำเร็จ",
            shortBody: "ต่ออายุ{$planName}ไม่สำเร็จ — กรุณาตรวจสอบการชำระเงิน",
            body:     "ระบบไม่สามารถหักเงินค่า{$planName} ({$price}) ได้ "
                    . "บริการยังเปิดใช้งานในช่วงผ่อนผัน "
                    . ($graceEnd ? "จนถึง {$graceEnd->format('d/m/Y')} " : '')
                    . "หลังจากนั้นจะเปลี่ยนเป็นแผนฟรีโดยอัตโนมัติ "
                    . "กรุณาอัปเดตช่องทางการชำระเงินก่อนหมดระยะผ่อนผัน",
            bullets:  array_values($bullets),
            cta:      ['label' => 'อัปเดตการชำระเงิน', 'url' => url('/photographer/subscription')],
            subject:  "⚠️ ต่ออายุ{$planName}ไม่สำเร็จ · กรุณาตรวจสอบการชำระเงิน",
            flexBubble: $this->bubble("⚠️ ต่ออายุไม่สำเร็จ", $price, array_values($bullets), '#dc2626',
                                       'อัปเดตการชำระเงิน', url('/photographer/subscription')),
            refId:    "sub.{$sub->id}.renewal_failed.{$sub->renewal_attempts}",
        );
    }

    /* ───────────────── Subscription expiring soon ───────────────── */

    public function subscriptionExpiringSoon(PhotographerSubscription $sub, int $daysLeft): LifecycleMessage
    {
        $plan     = $sub->plan;
        $planName = $plan?->name ?? 'แผน';
        $endAt    = $sub->current_period_end;
        $autoRenew = !$sub->cancel_at_period_end;

        $bullets = [
            "วันที่หมดอายุ: " . ($endAt?->format('d/m/Y') ?? '-'),
            "เหลือเวลา: {$daysLeft} วัน",
            $autoRenew
                ? '✓ ตั้งค่าต่ออายุอัตโนมัติไว้แล้ว'
                : '✗ ไม่ได้ตั้งต่ออายุ — บริการจะปิดเมื่อหมดอายุ',
        ];

        $severity = $daysLeft <= 1
            ? LifecycleMessage::SEVERITY_CRITICAL
            : LifecycleMessage::SEVERITY_WARN;

        return new LifecycleMessage(
            kind:      LifecycleMessage::KIND_SUBSCRIPTION_EXPIRING,
            severity:  $severity,
            headline:  $daysLeft <= 1
                         ? "🚨 {$planName}กำลังจะหมดอายุพรุ่งนี้"
                         : "⏰ {$planName}จะหมดอายุในอีก {$daysLeft} วัน",
            shortBody: "{$planName}เหลือ {$daysLeft} วัน · " . ($autoRenew ? 'จะต่ออายุอัตโนมัติ' : 'กรุณาต่ออายุ'),
            body:      "{$planName}ของคุณจะหมดอายุในอีก {$daysLeft} วัน "
                     . ($autoRenew
                          ? 'ระบบจะหักเงินและต่ออายุให้อัตโนมัติ ไม่ต้องดำเนินการอะไร'
                          : 'หากไม่ต่ออายุก่อนหมดเวลา บริการจะปิดและคุณจะถูกปรับเป็นแผนฟรี'),
            bullets:   $bullets,
            cta:       [
                'label' => $autoRenew ? 'ดูแผนของฉัน' : 'ต่ออายุเลย',
                'url'   => url('/photographer/subscription'),
            ],
            subject:   "⏰ {$planName}จะหมดอายุในอีก {$daysLeft} วัน",
            flexBubble: $this->bubble(
                $daysLeft <= 1 ? "🚨 หมดอายุพรุ่งนี้" : "⏰ เหลือ {$daysLeft} วัน",
                "{$daysLeft} วัน",
                $bullets,
                $daysLeft <= 1 ? '#dc2626' : '#f59e0b',
                $autoRenew ? 'ดูแผนของฉัน' : 'ต่ออายุเลย',
                url('/photographer/subscription'),
            ),
            refId:     "sub.{$sub->id}.expiring.{$daysLeft}d",
        );
    }

    /* ───────────────── Subscription expired (downgraded to free) ───────────────── */

    public function subscriptionExpired(PhotographerSubscription $sub, ?string $previousPlanName = null): LifecycleMessage
    {
        $planName = $previousPlanName ?? $sub->plan?->name ?? 'แผน';

        $bullets = [
            "บัญชีของคุณถูกปรับเป็น: แผนฟรี",
            "ฟีเจอร์เพิ่มเติม: ปิดใช้งานชั่วคราว",
            "พื้นที่จัดเก็บ: ลดเหลือ quota แผนฟรี",
            "ต้องการกลับมาใช้: สมัครใหม่ได้ทันที",
        ];

        return new LifecycleMessage(
            kind:      LifecycleMessage::KIND_SUBSCRIPTION_EXPIRED,
            severity:  LifecycleMessage::SEVERITY_CRITICAL,
            headline:  "❌ {$planName}สิ้นสุดแล้ว",
            shortBody: "{$planName}สิ้นสุดแล้ว — บัญชีถูกปรับเป็นแผนฟรี",
            body:      "{$planName}ของคุณสิ้นสุดและระยะผ่อนผันหมดแล้ว "
                     . "ระบบได้ปรับบัญชีของคุณเป็นแผนฟรีโดยอัตโนมัติ "
                     . "ไฟล์งานของคุณยังอยู่ครบ แต่ฟีเจอร์พรีเมียมและ AI credits ถูกลดเป็น quota แผนฟรี "
                     . "หากต้องการกลับมาใช้งาน{$planName} สามารถสมัครได้ทันที",
            bullets:   $bullets,
            cta:       ['label' => 'สมัครใหม่', 'url' => url('/photographer/subscription/plans')],
            subject:   "❌ {$planName}สิ้นสุดแล้ว · บัญชีถูกปรับเป็นแผนฟรี",
            flexBubble: $this->bubble("❌ {$planName}สิ้นสุด", 'แผนฟรี', $bullets, '#dc2626',
                                       'สมัครใหม่', url('/photographer/subscription/plans')),
            refId:     "sub.{$sub->id}.expired",
        );
    }

    /* ───────────────── Subscription cancelled (will end at period end) ───────────────── */

    public function subscriptionCancelled(PhotographerSubscription $sub): LifecycleMessage
    {
        $plan     = $sub->plan;
        $planName = $plan?->name ?? 'แผน';
        $endAt    = $sub->current_period_end;

        $bullets = [
            $endAt ? "ใช้งานได้ถึง: {$endAt->format('d/m/Y')}" : '-',
            "หลังจากนั้น: เปลี่ยนเป็นแผนฟรีโดยอัตโนมัติ",
            "เปลี่ยนใจ: กดปุ่ม 'ใช้แผนต่อ' ก่อนวันสิ้นสุด",
        ];

        return new LifecycleMessage(
            kind:      LifecycleMessage::KIND_SUBSCRIPTION_CANCELLED,
            severity:  LifecycleMessage::SEVERITY_INFO,
            headline:  "✕ ยกเลิกการต่ออายุ{$planName}",
            shortBody: "ยกเลิก{$planName} · ใช้ได้ถึง " . ($endAt?->format('d/m/Y') ?? '-'),
            body:      "ระบบบันทึกการยกเลิก{$planName}แล้ว "
                     . "คุณยังคงใช้งานได้ตามปกติจนถึง"
                     . ($endAt ? " {$endAt->format('d/m/Y')} " : ' วันสิ้นสุดรอบบิล ')
                     . "หลังจากนั้นบัญชีจะปรับเป็นแผนฟรีโดยอัตโนมัติ "
                     . "หากเปลี่ยนใจ สามารถกลับมาใช้แผนต่อได้ก่อนวันสิ้นสุด",
            bullets:   $bullets,
            cta:       ['label' => 'ใช้แผนต่อ', 'url' => url('/photographer/subscription')],
            subject:   "✕ ยกเลิก{$planName} · ใช้ได้ถึง " . ($endAt?->format('d/m/Y') ?? '-'),
            flexBubble: $this->bubble("✕ ยกเลิก{$planName}",
                                       $endAt ? "ถึง {$endAt->format('d/m/Y')}" : '',
                                       $bullets, '#64748b',
                                       'ใช้แผนต่อ', url('/photographer/subscription')),
            refId:     "sub.{$sub->id}.cancelled",
        );
    }

    /* ───────────────── Subscription resumed ───────────────── */

    public function subscriptionResumed(PhotographerSubscription $sub): LifecycleMessage
    {
        $planName = $sub->plan?->name ?? 'แผน';
        $renewsAt = $sub->current_period_end;

        return new LifecycleMessage(
            kind:      LifecycleMessage::KIND_SUBSCRIPTION_RESUMED,
            severity:  LifecycleMessage::SEVERITY_INFO,
            headline:  "✓ ใช้{$planName}ต่อ",
            shortBody: "เปิดใช้{$planName}ต่อ · ต่ออายุ " . ($renewsAt?->format('d/m/Y') ?? '-'),
            body:      "เรียบร้อย — ระบบจะต่ออายุ{$planName}ให้อัตโนมัติในรอบถัดไป",
            bullets:   [
                $renewsAt ? "ต่ออายุครั้งถัดไป: {$renewsAt->format('d/m/Y')}" : '',
                "ทุกฟีเจอร์: เปิดใช้งานต่อเนื่อง",
            ],
            cta:       ['label' => 'ดูแผนของฉัน', 'url' => url('/photographer/subscription')],
            subject:   "✓ ใช้{$planName}ต่อ — เปิดต่ออายุอัตโนมัติแล้ว",
            flexBubble: $this->bubble("✓ ใช้{$planName}ต่อ", '', [
                $renewsAt ? "ต่ออายุ {$renewsAt->format('d/m/Y')}" : '',
            ], '#10b981', 'ดูแผนของฉัน', url('/photographer/subscription')),
            refId:     "sub.{$sub->id}.resumed.{$sub->updated_at?->format('Ymd')}",
        );
    }

    /* ───────────────── Add-on activated ───────────────── */

    public function addonActivated(int $purchaseId, array $snapshot, ?\Carbon\CarbonInterface $expiresAt): LifecycleMessage
    {
        $label   = $snapshot['label']   ?? 'Add-on';
        $price   = $this->money((float) ($snapshot['price_thb'] ?? 0));
        $tagline = $snapshot['tagline'] ?? '';

        $bullets = array_filter([
            "ราคา: {$price}",
            $expiresAt ? "ใช้ได้ถึง: {$expiresAt->format('d/m/Y H:i')}" : 'ตลอดชีพ',
            $tagline,
        ]);

        return new LifecycleMessage(
            kind:      LifecycleMessage::KIND_ADDON_ACTIVATED,
            severity:  LifecycleMessage::SEVERITY_INFO,
            headline:  "✨ เปิดใช้งาน \"{$label}\" แล้ว",
            shortBody: "เปิดใช้ {$label} เรียบร้อย",
            body:      "การชำระเงินของคุณสำเร็จ ระบบเปิดใช้งาน {$label} เรียบร้อยแล้ว "
                     . ($expiresAt
                          ? "บริการนี้ใช้ได้ถึง {$expiresAt->format('d/m/Y H:i')}"
                          : 'บริการนี้ใช้ได้ตลอดอายุ subscription'),
            bullets:   array_values($bullets),
            cta:       ['label' => 'ดูบริการของฉัน', 'url' => url('/photographer/store/status')],
            subject:   "✨ เปิดใช้ \"{$label}\" แล้ว · {$price}",
            flexBubble: $this->bubble("✨ เปิดใช้ {$label}", $price, array_values($bullets),
                                       '#10b981', 'ดูบริการของฉัน', url('/photographer/store/status')),
            refId:     "addon.{$purchaseId}.activated",
        );
    }

    /* ───────────────── Add-on expiring soon ───────────────── */

    public function addonExpiringSoon(int $purchaseId, array $snapshot, \Carbon\CarbonInterface $expiresAt): LifecycleMessage
    {
        $label = $snapshot['label'] ?? 'Add-on';
        $daysLeft = max(0, (int) round(now()->diffInHours($expiresAt) / 24));

        $bullets = [
            "หมดอายุ: {$expiresAt->format('d/m/Y H:i')}",
            "เหลือเวลา: " . ($daysLeft === 0 ? 'น้อยกว่า 1 วัน' : "{$daysLeft} วัน"),
            "ต่ออายุ: ซื้อ pack เดิมอีกครั้งจาก Store",
        ];

        return new LifecycleMessage(
            kind:      LifecycleMessage::KIND_ADDON_EXPIRING,
            severity:  LifecycleMessage::SEVERITY_WARN,
            headline:  "⏰ \"{$label}\" จะหมดอายุในอีก {$daysLeft} วัน",
            shortBody: "{$label} เหลือ " . ($daysLeft === 0 ? 'น้อยกว่า 1 วัน' : "{$daysLeft} วัน"),
            body:      "บริการเสริม \"{$label}\" ของคุณกำลังจะหมดอายุ "
                     . "หากต้องการใช้ต่อเนื่อง สามารถซื้อ pack เดิมอีกครั้งจาก Store",
            bullets:   $bullets,
            cta:       ['label' => 'ต่ออายุที่ Store', 'url' => url('/photographer/store')],
            subject:   "⏰ \"{$label}\" จะหมดอายุในอีก {$daysLeft} วัน",
            flexBubble: $this->bubble("⏰ {$label} หมดอายุเร็ว ๆ นี้", "{$daysLeft} วัน",
                                       $bullets, '#f59e0b', 'ต่ออายุที่ Store', url('/photographer/store')),
            refId:     "addon.{$purchaseId}.expiring.{$daysLeft}d",
        );
    }

    /* ───────────────── Add-on expired ───────────────── */

    public function addonExpired(int $purchaseId, array $snapshot): LifecycleMessage
    {
        $label = $snapshot['label'] ?? 'Add-on';

        return new LifecycleMessage(
            kind:      LifecycleMessage::KIND_ADDON_EXPIRED,
            severity:  LifecycleMessage::SEVERITY_INFO,
            headline:  "📦 \"{$label}\" สิ้นสุดแล้ว",
            shortBody: "{$label} หมดอายุแล้ว",
            body:      "บริการเสริม \"{$label}\" ของคุณสิ้นสุดแล้ว "
                     . "หากต้องการใช้ต่อ สามารถซื้อ pack เดิมจาก Store ได้เสมอ",
            bullets:   ['สถานะ: ปิดใช้งาน', 'ดูบริการอื่น: เลือกซื้อจาก Store'],
            cta:       ['label' => 'เลือกซื้อใหม่', 'url' => url('/photographer/store')],
            subject:   "📦 \"{$label}\" สิ้นสุดแล้ว",
            flexBubble: $this->bubble("📦 {$label} สิ้นสุด", '', [
                'สถานะ: ปิดใช้งาน',
                'ดูบริการอื่น: เลือกซื้อจาก Store',
            ], '#64748b', 'เลือกซื้อใหม่', url('/photographer/store')),
            refId:     "addon.{$purchaseId}.expired",
        );
    }

    /* ───────────────── Storage usage warning ───────────────── */

    public function storageUsageWarning(int $usedBytes, int $quotaBytes, bool $critical): LifecycleMessage
    {
        $pct = $quotaBytes > 0 ? round($usedBytes / $quotaBytes * 100, 1) : 0;
        $bullets = [
            "ใช้ไป: " . $this->bytes($usedBytes),
            "ทั้งหมด: " . $this->bytes($quotaBytes),
            "คิดเป็น: {$pct}%",
            "ซื้อพื้นที่เพิ่ม: 50GB เริ่มต้น ฿290",
        ];

        return new LifecycleMessage(
            kind:      $critical
                         ? LifecycleMessage::KIND_USAGE_STORAGE_CRITICAL
                         : LifecycleMessage::KIND_USAGE_STORAGE_WARNING,
            severity:  $critical ? LifecycleMessage::SEVERITY_CRITICAL : LifecycleMessage::SEVERITY_WARN,
            headline:  $critical ? "🚨 พื้นที่ใกล้เต็ม ({$pct}%)" : "⚠️ พื้นที่ใช้ไป {$pct}%",
            shortBody: "ใช้พื้นที่ {$pct}% · ซื้อเพิ่มได้ที่ Store",
            body:      $critical
                         ? "พื้นที่จัดเก็บของคุณเหลือไม่ถึง 5% — การอัปโหลดงานใหม่อาจถูกปิดในไม่ช้า "
                         . "กรุณาลบไฟล์ที่ไม่ใช้ หรือซื้อพื้นที่เพิ่มเพื่อใช้งานต่อ"
                         : "พื้นที่จัดเก็บของคุณใช้ไปเกิน 80% แล้ว ซื้อพื้นที่เพิ่มเพื่อใช้งานสบายใจ",
            bullets:   $bullets,
            cta:       ['label' => 'ซื้อพื้นที่เพิ่ม', 'url' => url('/photographer/store') . '#storage'],
            subject:   ($critical ? "🚨 พื้นที่ใกล้เต็ม" : "⚠️ พื้นที่ใช้ไป") . " {$pct}%",
            flexBubble: $this->bubble(
                $critical ? "🚨 ใกล้เต็ม" : "⚠️ ใกล้เต็ม",
                "{$pct}%",
                $bullets,
                $critical ? '#dc2626' : '#f59e0b',
                'ซื้อพื้นที่เพิ่ม',
                url('/photographer/store') . '#storage',
            ),
            // refId includes the bucket so an 80% warning + a separate 95%
            // warning are tracked as distinct events; without the suffix,
            // the dedup would suppress the critical alert after the 80%.
            refId:     "usage.storage." . ($critical ? 'critical' : 'warning') . '.' . now()->format('Ym'),
        );
    }

    /* ───────────────── AI credits depleted ───────────────── */

    public function aiCreditsDepleted(int $used, int $cap, ?\Carbon\CarbonInterface $resetAt): LifecycleMessage
    {
        $bullets = [
            "ใช้ไป: " . number_format($used) . ' / ' . number_format($cap),
            $resetAt ? "รอบใหม่: {$resetAt->format('d/m/Y')}" : '',
            "ซื้อ Credits เพิ่ม: 5,000 เริ่ม ฿199",
        ];
        $bullets = array_filter($bullets);

        return new LifecycleMessage(
            kind:      LifecycleMessage::KIND_USAGE_AI_CREDITS_DEPLETED,
            severity:  LifecycleMessage::SEVERITY_CRITICAL,
            headline:  "🔋 AI Credits หมดแล้ว",
            shortBody: "AI Credits หมดในรอบนี้ · ซื้อเพิ่มที่ Store",
            body:      "AI Credits ของคุณในรอบบิลนี้ใช้หมดแล้ว — ฟีเจอร์ AI Face Search / "
                     . "Best Shot / Auto-Tag จะไม่ทำงานจนกว่าจะถึงรอบใหม่ "
                     . ($resetAt ? "({$resetAt->format('d/m/Y')}) " : '')
                     . "หรือซื้อ Credits Pack เพิ่มได้ทันทีจาก Store",
            bullets:   array_values($bullets),
            cta:       ['label' => 'ซื้อ Credits เพิ่ม', 'url' => url('/photographer/store') . '#ai_credits'],
            subject:   "🔋 AI Credits หมดในรอบนี้",
            flexBubble: $this->bubble("🔋 Credits หมด", '', array_values($bullets), '#dc2626',
                                       'ซื้อ Credits เพิ่ม', url('/photographer/store') . '#ai_credits'),
            refId:     'usage.ai_credits.depleted.' . now()->format('Ym'),
        );
    }

    /* ────────────────────────── helpers ────────────────────────── */

    private function bytes(int $bytes): string
    {
        if ($bytes <= 0) return '0 B';
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0; $v = $bytes;
        while ($v >= 1024 && $i < count($units) - 1) { $v /= 1024; $i++; }
        return number_format($v, $v < 10 && $i > 0 ? 1 : 0) . ' ' . $units[$i];
    }

    private function cycleLabel(?string $cycle): string
    {
        return match ($cycle) {
            'monthly' => 'เดือน',
            'yearly'  => 'ปี',
            'weekly'  => 'สัปดาห์',
            'daily'   => 'วัน',
            default   => 'รอบ',
        };
    }

    /**
     * Build a LINE Flex bubble. Same skeleton for every event kind so
     * photographers learn the layout once. Only the colour + content
     * varies.
     */
    private function bubble(string $title, string $bigText, array $bullets, string $accent, string $ctaLabel, string $ctaUrl): array
    {
        $rows = [];
        foreach ($bullets as $b) {
            $rows[] = [
                'type'  => 'text',
                'text'  => "• {$b}",
                'size'  => 'sm',
                'color' => '#0f172a',
                'wrap'  => true,
            ];
        }
        if (empty($rows)) {
            $rows[] = ['type' => 'text', 'text' => ' ', 'size' => 'sm', 'color' => '#0f172a'];
        }

        $headerContents = [
            ['type' => 'text', 'text' => $title, 'color' => '#ffffff', 'weight' => 'bold', 'size' => 'md'],
        ];
        if ($bigText !== '') {
            $headerContents[] = ['type' => 'text', 'text' => $bigText, 'color' => '#ffffff', 'weight' => 'bold', 'size' => 'xxl', 'margin' => 'sm'];
        }

        return [
            'type' => 'bubble',
            'header' => [
                'type' => 'box', 'layout' => 'vertical', 'paddingAll' => '16px',
                'backgroundColor' => $accent,
                'contents' => $headerContents,
            ],
            'body' => [
                'type' => 'box', 'layout' => 'vertical', 'spacing' => 'sm', 'paddingAll' => '16px',
                'contents' => $rows,
            ],
            'footer' => [
                'type' => 'box', 'layout' => 'vertical',
                'contents' => [[
                    'type' => 'button', 'style' => 'primary', 'color' => $accent,
                    'action' => ['type' => 'uri', 'label' => $ctaLabel, 'uri' => $ctaUrl],
                ]],
            ],
        ];
    }
}

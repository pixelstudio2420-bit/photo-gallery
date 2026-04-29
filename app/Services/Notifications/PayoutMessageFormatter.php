<?php

namespace App\Services\Notifications;

use App\Models\PhotographerDisbursement;
use App\Models\PhotographerProfile;
use App\Models\User;

/**
 * Single source of truth for the wording of payout notifications.
 *
 * Why this exists
 * ───────────────
 * Before this formatter, the same disbursement event produced THREE
 * subtly different stories across channels:
 *
 *   • In-app  : "การโอนเงินสำเร็จ — ฿1,234.56"
 *   • LINE    : "💰 เงินถูกโอนเข้าบัญชีแล้ว! จำนวน: 1234.56 บาท"
 *   • Email   : (built per-call, sometimes "ยอด ฿1,234", sometimes "รายได้ ฿1,234.56 โอนเข้าบัญชีแล้ว")
 *
 * Photographers asking "did the system actually pay me?" had to map
 * across phrasing differences and number formats — the in-app number
 * was rounded, the LINE number was raw, the email subject mentioned a
 * date the in-app didn't. This formatter renders ONE structured payload
 * that every channel adapts to its medium without changing the substance.
 *
 * Layout
 * ──────
 * Each call returns a {@see PayoutMessage} with consistent fields:
 *
 *   • headline   — short title ("เงินรายได้โอนแล้ว")
 *   • amount     — formatted "฿1,234.56"
 *   • body       — 2-line paragraph with breakdown + reference
 *   • shortBody  — 1-line for LINE notification preview
 *   • bullets    — ['ยอดรวม: ฿X', 'ค่าธรรมเนียม: ฿Y', 'ยอดสุทธิ: ฿Z']
 *   • cta        — ['label' => 'ดูรายละเอียด', 'url' => 'https://…']
 *   • subject    — for email subject line
 *   • flexBubble — pre-built LINE flex JSON (richest channel)
 *
 * Channels can use whichever pieces fit their UX. Numbers are formatted
 * once (number_format with 2 decimals) so "1,234.56" appears identically
 * everywhere. Currency symbol is a non-breaking space (U+00A0) before
 * the digits so line-wrap never separates "฿" from the amount.
 *
 * Two main entry points:
 *   - success(...) → settled successfully
 *   - failure(...) → could not settle (admin-actionable + photographer-actionable variants)
 */
class PayoutMessageFormatter
{
    /**
     * Successful payout message — money has cleared.
     */
    public function success(PhotographerDisbursement $d): PayoutMessage
    {
        $amount        = (float) $d->amount_thb;
        $payoutCount   = (int) $d->payout_count;
        $providerTxnId = (string) ($d->provider_txn_id ?? '');
        $settledAt     = $d->settled_at ?? now();

        $profile = PhotographerProfile::where('user_id', $d->photographer_id)->first();
        $accountLast4 = $profile && $profile->bank_account_number
            ? substr(preg_replace('/\D/', '', (string) $profile->bank_account_number), -4)
            : null;
        $bankName     = $profile?->bank_name;
        $promptpayLast4 = $profile && $profile->promptpay_number
            ? substr(preg_replace('/\D/', '', (string) $profile->promptpay_number), -4)
            : null;

        // Account display: prefer bank info; fall back to PromptPay last 4.
        $accountLine = $bankName && $accountLast4
            ? "{$bankName} ลงท้าย {$accountLast4}"
            : ($promptpayLast4 ? "PromptPay ลงท้าย {$promptpayLast4}" : 'ตามที่ตั้งค่าไว้');

        $amountFmt = $this->money($amount);

        // Compute the breakdown only when the disbursement carries real
        // numbers (defensive — older rows may not have payout_count).
        $bullets = [];
        if ($payoutCount > 0) {
            $bullets[] = "จากออเดอร์: {$payoutCount} รายการ";
        }
        $bullets[] = "ยอดสุทธิ: {$amountFmt}";
        $bullets[] = "เข้าบัญชี: {$accountLine}";
        if ($providerTxnId !== '') {
            $bullets[] = "อ้างอิง: {$providerTxnId}";
        }
        $bullets[] = "เวลา: " . $settledAt->format('d/m/Y H:i');

        return new PayoutMessage(
            kind:      PayoutMessage::KIND_SUCCESS,
            headline:  '✅ เงินรายได้โอนแล้ว',
            amount:    $amountFmt,
            shortBody: "ยอด {$amountFmt} โอนเข้าบัญชีของคุณเรียบร้อย",
            body:      "ระบบโอนเงินรายได้ {$amountFmt} เข้า{$accountLine} เรียบร้อยแล้ว"
                    . " — จากออเดอร์รวม {$payoutCount} รายการ",
            bullets:   $bullets,
            cta:       ['label' => 'ดูสรุปรายได้', 'url' => url('/photographer/earnings')],
            subject:   "✅ รายได้ {$amountFmt} โอนเข้าบัญชีแล้ว",
            flexBubble: $this->successBubble($amountFmt, $accountLine, $payoutCount, $providerTxnId, $settledAt->format('d/m/Y H:i')),
        );
    }

    /**
     * Failed payout — provider rejected or could not settle.
     *
     * @param  bool  $nameMismatch  ITMX name-mismatch path (photographer needs to
     *                              fix bank_account_name before retry will succeed).
     */
    public function failure(
        PhotographerDisbursement $d,
        string $errorMessage = '',
        bool $nameMismatch = false,
    ): PayoutMessage {
        $amount     = (float) $d->amount_thb;
        $amountFmt  = $this->money($amount);

        if ($nameMismatch) {
            $reason = 'ชื่อบัญชีไม่ตรงกับทะเบียน PromptPay ที่ธนาคาร';
            $action = 'แก้ไขชื่อบัญชีในหน้าตั้งค่าการรับเงิน แล้วระบบจะลองโอนใหม่อัตโนมัติ';
            $headline = '❗ กรุณาแก้ไขชื่อบัญชี';
            $cta = ['label' => 'แก้ไขชื่อบัญชี', 'url' => url('/photographer/profile/setup-bank')];
        } else {
            $reason = $errorMessage !== ''
                ? $errorMessage
                : 'การโอนเงินถูก provider ปฏิเสธ';
            $action = 'ระบบจะลองโอนใหม่อัตโนมัติในรอบถัดไป — ไม่ต้องดำเนินการเพิ่มเติม';
            $headline = '⚠️ การโอนเงินยังไม่สำเร็จ';
            $cta = ['label' => 'ดูรายละเอียด', 'url' => url('/photographer/earnings')];
        }

        return new PayoutMessage(
            kind:      $nameMismatch ? PayoutMessage::KIND_FAILURE_NAME : PayoutMessage::KIND_FAILURE_GENERIC,
            headline:  $headline,
            amount:    $amountFmt,
            shortBody: "ยอด {$amountFmt} ยังไม่ได้โอน — {$reason}",
            body:      "ยอด {$amountFmt} ยังไม่ได้เข้าบัญชี\nสาเหตุ: {$reason}\nสิ่งที่ต้องทำ: {$action}",
            bullets:   [
                "ยอดที่ยังค้าง: {$amountFmt}",
                "สาเหตุ: {$reason}",
                "สิ่งที่ต้องทำ: {$action}",
            ],
            cta:       $cta,
            subject:   "{$headline} — ยอด {$amountFmt}",
            flexBubble: $this->failureBubble($amountFmt, $reason, $action, $cta, $nameMismatch),
        );
    }

    /**
     * Format Thai baht with non-breaking space and 2 decimal places.
     * "฿\xC2\xA01,234.56" — the U+00A0 ensures the symbol never wraps to
     * a different line from the digits in chat messages.
     */
    public function money(float $amount): string
    {
        return "฿\xC2\xA0" . number_format($amount, 2);
    }

    // ──────────────────────────────────────────────────────────────────
    //  LINE Flex bubble builders
    //  Flex messages are LINE's rich UI — much more readable than plain
    //  text, but require structured JSON. We build them here so the
    //  channel-side code (LineNotifyService) doesn't reinvent the layout.
    // ──────────────────────────────────────────────────────────────────

    private function successBubble(string $amountFmt, string $accountLine, int $payoutCount, string $txnId, string $when): array
    {
        $rows = array_filter([
            $payoutCount > 0 ? ['ออเดอร์', "{$payoutCount} รายการ"] : null,
            ['เข้าบัญชี', $accountLine],
            $txnId !== '' ? ['อ้างอิง', $txnId] : null,
            ['เวลาโอน', $when],
        ]);

        $contents = [];
        foreach ($rows as $row) {
            $contents[] = [
                'type' => 'box',
                'layout' => 'baseline',
                'spacing' => 'sm',
                'contents' => [
                    ['type' => 'text', 'text' => $row[0], 'color' => '#94a3b8', 'size' => 'sm', 'flex' => 2],
                    ['type' => 'text', 'text' => $row[1], 'color' => '#0f172a', 'size' => 'sm', 'flex' => 5, 'wrap' => true],
                ],
            ];
        }

        return [
            'type' => 'bubble',
            'header' => [
                'type' => 'box', 'layout' => 'vertical', 'paddingAll' => '16px',
                'backgroundColor' => '#10b981',
                'contents' => [
                    ['type' => 'text', 'text' => '✅ เงินรายได้โอนแล้ว', 'color' => '#ffffff', 'weight' => 'bold', 'size' => 'md'],
                    ['type' => 'text', 'text' => $amountFmt, 'color' => '#ffffff', 'weight' => 'bold', 'size' => 'xxl', 'margin' => 'sm'],
                ],
            ],
            'body' => [
                'type' => 'box', 'layout' => 'vertical', 'spacing' => 'sm', 'paddingAll' => '16px',
                'contents' => $contents,
            ],
            'footer' => [
                'type' => 'box', 'layout' => 'vertical',
                'contents' => [[
                    'type' => 'button', 'style' => 'primary', 'color' => '#10b981',
                    'action' => [
                        'type' => 'uri', 'label' => 'ดูสรุปรายได้',
                        'uri' => url('/photographer/earnings'),
                    ],
                ]],
            ],
        ];
    }

    private function failureBubble(string $amountFmt, string $reason, string $action, array $cta, bool $nameMismatch): array
    {
        $color = $nameMismatch ? '#dc2626' : '#f59e0b';
        $icon  = $nameMismatch ? '❗' : '⚠️';
        $title = $nameMismatch ? 'กรุณาแก้ไขชื่อบัญชี' : 'การโอนเงินยังไม่สำเร็จ';

        return [
            'type' => 'bubble',
            'header' => [
                'type' => 'box', 'layout' => 'vertical', 'paddingAll' => '16px',
                'backgroundColor' => $color,
                'contents' => [
                    ['type' => 'text', 'text' => "{$icon} {$title}", 'color' => '#ffffff', 'weight' => 'bold', 'size' => 'md'],
                    ['type' => 'text', 'text' => "ยอด {$amountFmt}", 'color' => '#ffffff', 'weight' => 'bold', 'size' => 'xl', 'margin' => 'sm'],
                ],
            ],
            'body' => [
                'type' => 'box', 'layout' => 'vertical', 'spacing' => 'md', 'paddingAll' => '16px',
                'contents' => [
                    [
                        'type' => 'text', 'text' => 'สาเหตุ',
                        'color' => '#94a3b8', 'size' => 'xs',
                    ],
                    [
                        'type' => 'text', 'text' => $reason,
                        'color' => '#0f172a', 'size' => 'sm', 'wrap' => true,
                    ],
                    [
                        'type' => 'text', 'text' => 'สิ่งที่ต้องทำ',
                        'color' => '#94a3b8', 'size' => 'xs', 'margin' => 'md',
                    ],
                    [
                        'type' => 'text', 'text' => $action,
                        'color' => '#0f172a', 'size' => 'sm', 'wrap' => true,
                    ],
                ],
            ],
            'footer' => [
                'type' => 'box', 'layout' => 'vertical',
                'contents' => [[
                    'type' => 'button', 'style' => 'primary', 'color' => $color,
                    'action' => [
                        'type' => 'uri', 'label' => $cta['label'],
                        'uri' => $cta['url'],
                    ],
                ]],
            ],
        ];
    }
}

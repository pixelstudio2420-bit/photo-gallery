<?php

namespace App\Services\Payment;

use App\Models\AppSetting;
use App\Models\BankAccount;
use App\Models\PaymentMethod;
use App\Models\SubscriptionPlan;
use App\Services\Payment\SlipOKService;
use Illuminate\Support\Collection;

/**
 * Single source of truth for "is the subscription purchase flow ready?"
 *
 * Both `payment:readiness` (artisan) and the admin web page at
 * /admin/payment-readiness call this service. That guarantees CLI and UI
 * never drift — fix one bug here and both surfaces heal.
 *
 * Each check returns a small array shape:
 *
 *   [
 *     'id'      => 'unique-slug',
 *     'label'   => 'Human-readable check name',
 *     'pass'    => bool,             // overall pass/fail
 *     'level'   => 'critical'|'warn',// "critical" blocks purchases entirely
 *     'detail'  => 'one-liner explanation',
 *     'fix'     => 'concrete next step to make this pass',
 *     'fix_url' => '/admin/...',     // optional — where in admin to fix it
 *   ]
 *
 * The overall ready() boolean is true only when every CRITICAL check
 * passes. Warnings reduce the "score" but don't block purchases.
 */
class PaymentReadinessService
{
    /**
     * Run every check and return the full report.
     *
     * @return array{
     *   ready: bool,
     *   ready_for_free_only: bool,
     *   total: int,
     *   passed: int,
     *   critical_failed: int,
     *   warn_failed: int,
     *   checks: array<int, array<string, mixed>>,
     *   gateway_summary: array<int, array<string, mixed>>,
     *   active_gateways: int,
     * }
     */
    public function run(): array
    {
        $checks = [
            $this->checkSystemEnabled(),
            $this->checkPlansExist(),
            $this->checkAtLeastOnePublicPaidPlan(),
            $this->checkAtLeastOneGatewayLive(),
            $this->checkPromptPay(),
            $this->checkOmise(),
            $this->checkBankTransfer(),
            $this->checkManualGateway(),
            $this->checkSlipOKVerification(),
            $this->checkSlipAutoApproveMode(),
            $this->checkSubscriptionAutomation(),
            $this->checkRefundFlow(),
            $this->checkOrderFulfillment(),
        ];

        $passed = 0;
        $criticalFailed = 0;
        $warnFailed = 0;
        foreach ($checks as $c) {
            if ($c['pass']) {
                $passed++;
            } elseif ($c['level'] === 'critical') {
                $criticalFailed++;
            } else {
                $warnFailed++;
            }
        }

        $gatewaySummary = $this->gatewaySummary();
        $activeGateways = collect($gatewaySummary)->where('ready', true)->count();

        return [
            // ready === every critical check passes (warnings don't block)
            'ready'               => $criticalFailed === 0,
            // free plans don't need a gateway, only the system enabled + plans
            'ready_for_free_only' => $checks[0]['pass'] && $checks[1]['pass'],
            'total'               => count($checks),
            'passed'              => $passed,
            'critical_failed'     => $criticalFailed,
            'warn_failed'         => $warnFailed,
            'checks'              => $checks,
            'gateway_summary'     => $gatewaySummary,
            'active_gateways'     => $activeGateways,
        ];
    }

    // ───────────────────────────────────────────────────────────────────
    // Individual checks
    // ───────────────────────────────────────────────────────────────────

    private function checkSystemEnabled(): array
    {
        // SubscriptionService::systemEnabled() is the master gate the
        // controller checks first. If this is OFF, /photographer/subscription/subscribe
        // returns "ระบบสมัครสมาชิกปิดใช้งานชั่วคราว" before doing anything.
        $on = AppSetting::get('subscriptions_enabled', '1') === '1';

        return [
            'id'      => 'system_enabled',
            'label'   => 'ระบบสมัครสมาชิกเปิดใช้งานอยู่',
            'pass'    => $on,
            'level'   => 'critical',
            'detail'  => $on
                ? 'AppSetting `subscriptions_enabled` = 1'
                : 'AppSetting `subscriptions_enabled` ถูกปิดอยู่ — ลูกค้าจะเห็นข้อความ "ระบบสมัครสมาชิกปิดใช้งานชั่วคราว"',
            'fix'     => 'ตั้ง AppSetting `subscriptions_enabled` = 1 (admin → settings → subscriptions)',
            'fix_url' => null,
        ];
    }

    private function checkPlansExist(): array
    {
        $count = SubscriptionPlan::query()->count();
        $activeCount = SubscriptionPlan::query()->where('is_active', true)->count();

        return [
            'id'      => 'plans_exist',
            'label'   => 'มีแผนสมาชิกในระบบ',
            'pass'    => $activeCount > 0,
            'level'   => 'critical',
            'detail'  => "พบ {$activeCount} แผน active จากทั้งหมด {$count} แผน",
            'fix'     => 'ไปที่ /admin/subscription-plans เพื่อเพิ่ม/เปิดใช้งานแผน',
            'fix_url' => $this->safeRoute('admin.subscription-plans.index'),
        ];
    }

    private function checkAtLeastOnePublicPaidPlan(): array
    {
        // Free plan alone is fine for "subscribe" working, but customer
        // can never UPGRADE if there's no public paid plan visible on
        // the picker. This is critical for revenue.
        $count = SubscriptionPlan::query()
            ->where('is_active', true)
            ->where('is_public', true)
            ->where('price_thb', '>', 0)
            ->count();

        return [
            'id'      => 'public_paid_plan',
            'label'   => 'มีแผนเสียเงินที่แสดงต่อสาธารณะอย่างน้อย 1 แผน',
            'pass'    => $count > 0,
            'level'   => 'critical',
            'detail'  => "พบแผนเสียเงิน + active + is_public = {$count} แผน",
            'fix'     => 'ตั้ง is_public = true และ is_active = true ในแผนที่ต้องการขาย',
            'fix_url' => $this->safeRoute('admin.subscription-plans.index'),
        ];
    }

    private function checkAtLeastOneGatewayLive(): array
    {
        // The single most-impactful check: even if everything else is
        // green, customers can't pay if no gateway both has its admin
        // toggle ON and credentials configured.
        $live = $this->gatewaysReady();

        return [
            'id'      => 'gateway_live',
            'label'   => 'มี payment gateway พร้อมรับเงินอย่างน้อย 1 ตัว',
            'pass'    => $live->isNotEmpty(),
            'level'   => 'critical',
            'detail'  => $live->isEmpty()
                ? 'ไม่มี gateway ใดที่ทั้ง toggle ON + credentials ครบ — ลูกค้าจะเห็น checkout ว่างเปล่า'
                : 'พร้อม: ' . $live->pluck('label')->implode(', '),
            'fix'     => 'ตั้งค่าและเปิดใช้งานอย่างน้อย 1 gateway (PromptPay เร็วที่สุด — ใส่เบอร์ PromptPay 1 ช่อง)',
            'fix_url' => $this->safeRoute('admin.payments.methods'),
        ];
    }

    private function checkPromptPay(): array
    {
        $toggleOn = AppSetting::get('promptpay_enabled', '1') === '1';
        $number = trim((string) AppSetting::get('promptpay_number', ''));
        $hasNumber = $number !== '';

        try {
            $available = PaymentService::isGatewayEnabled('promptpay');
        } catch (\Throwable) {
            $available = false;
        }

        $pass = $available && $toggleOn && $hasNumber;

        return [
            'id'      => 'gateway_promptpay',
            'label'   => 'PromptPay พร้อมใช้งาน',
            'pass'    => $pass,
            'level'   => 'warn',
            'detail'  => $pass
                ? "พร้อม — เบอร์ {$this->maskMobile($number)}"
                : sprintf(
                    'toggle=%s · เบอร์=%s · gateway.isAvailable=%s',
                    $toggleOn ? 'ON' : 'OFF',
                    $hasNumber ? 'set' : 'EMPTY',
                    $available ? 'YES' : 'no'
                ),
            'fix'     => 'ตั้ง AppSetting `promptpay_number` (เบอร์มือถือ 10 หลัก หรือเลขประจำตัวประชาชน 13 หลัก) และ toggle ON',
            'fix_url' => $this->safeRoute('admin.payments.methods'),
        ];
    }

    private function checkOmise(): array
    {
        $toggleOn = AppSetting::get('omise_enabled', '1') === '1';
        $publicKey = trim((string) AppSetting::get('omise_public_key', ''));
        $secretKey = trim((string) AppSetting::get('omise_secret_key', ''));
        $hasKeys = $publicKey !== '' && $secretKey !== '';

        try {
            $available = PaymentService::isGatewayEnabled('omise');
        } catch (\Throwable) {
            $available = false;
        }

        $pass = $available && $toggleOn && $hasKeys;

        return [
            'id'      => 'gateway_omise',
            'label'   => 'Omise (บัตรเครดิต) พร้อมใช้งาน',
            'pass'    => $pass,
            'level'   => 'warn',
            'detail'  => $pass
                ? 'พร้อม — public + secret key ตั้งครบ'
                : sprintf(
                    'toggle=%s · public_key=%s · secret_key=%s · gateway.isAvailable=%s',
                    $toggleOn ? 'ON' : 'OFF',
                    $publicKey === '' ? 'EMPTY' : 'set',
                    $secretKey === '' ? 'EMPTY' : 'set',
                    $available ? 'YES' : 'no'
                ),
            'fix'     => 'ตั้ง AppSetting `omise_public_key` + `omise_secret_key` จาก Omise dashboard และ toggle ON',
            'fix_url' => $this->safeRoute('admin.payments.methods'),
        ];
    }

    private function checkBankTransfer(): array
    {
        $methodActive = PaymentMethod::query()
            ->where('method_type', 'bank_transfer')
            ->where('is_active', true)
            ->exists();
        $bankCount = BankAccount::query()->where('is_active', true)->count();

        try {
            $available = PaymentService::isGatewayEnabled('bank_transfer');
        } catch (\Throwable) {
            $available = false;
        }

        $pass = $available && $methodActive && $bankCount > 0;

        return [
            'id'      => 'gateway_bank',
            'label'   => 'โอนผ่านธนาคาร (พร้อมบัญชีปลายทาง)',
            'pass'    => $pass,
            'level'   => 'warn',
            'detail'  => $pass
                ? "พร้อม — มี {$bankCount} บัญชี active"
                : sprintf(
                    'method.is_active=%s · บัญชี active=%d · gateway.isAvailable=%s',
                    $methodActive ? 'YES' : 'no',
                    $bankCount,
                    $available ? 'YES' : 'no'
                ),
            'fix'     => 'เพิ่มบัญชีธนาคารใน /admin/payments/banks อย่างน้อย 1 บัญชี และเปิด method bank_transfer',
            'fix_url' => $this->safeRoute('admin.payments.banks'),
        ];
    }

    private function checkManualGateway(): array
    {
        // Manual is the always-on fallback used by admins to confirm
        // off-platform payments (e.g. cash, custom invoice). It has no
        // credentials but it should still be enabled in payment_methods
        // so it shows up in the picker for admin-issued orders.
        $methodActive = PaymentMethod::query()
            ->where('method_type', 'manual')
            ->where('is_active', true)
            ->exists();

        return [
            'id'      => 'gateway_manual',
            'label'   => 'Manual (admin-issued) gateway',
            'pass'    => $methodActive,
            'level'   => 'warn',
            'detail'  => $methodActive
                ? 'เปิดอยู่ — ใช้สำหรับลูกค้าจ่ายเงินสดหรือ custom invoice'
                : 'method `manual` ปิดอยู่ใน payment_methods — admin จะ issue order ด้วยตัวเองไม่ได้',
            'fix'     => 'เปิด method type "manual" ใน /admin/payments/methods',
            'fix_url' => $this->safeRoute('admin.payments.methods'),
        ];
    }

    /**
     * SlipOK external API for slip verification — the engine that lets
     * the system auto-confirm bank-transfer slips without an admin
     * clicking approve. Same code path serves both photo orders and
     * subscription orders (PaymentController::uploadSlip → SlipVerifier
     * → SlipOKService), so configuring it once enables auto-verify
     * across the whole site.
     *
     * Three keys must be present:
     *   • slipok_enabled = '1'
     *   • slipok_api_key = the dashboard key
     *   • slipok_api_url = the full POST endpoint from the SlipOK
     *     dashboard (or legacy slipok_branch_id which auto-builds it)
     */
    private function checkSlipOKVerification(): array
    {
        $svc = new SlipOKService();
        $enabled    = $svc->isEnabled();
        $configured = $svc->isConfigured();
        $hasKey = !empty(AppSetting::get('slipok_api_key', ''));
        $hasUrl = $svc->resolveApiUrl() !== null;

        $pass = $enabled && $configured;

        return [
            'id'      => 'slipok_verify',
            'label'   => 'SlipOK ตรวจสลิปอัตโนมัติ',
            'pass'    => $pass,
            'level'   => 'warn',
            'detail'  => $pass
                ? 'พร้อม — slip ที่อัปโหลดจะส่งไปตรวจกับ SlipOK API ก่อน'
                : sprintf(
                    'enabled=%s · api_key=%s · api_url=%s',
                    $enabled ? 'ON' : 'OFF',
                    $hasKey ? 'set' : 'EMPTY',
                    $hasUrl ? 'set' : 'EMPTY'
                ),
            'fix'     => 'ตั้ง AppSetting `slipok_enabled`=1, `slipok_api_key` + `slipok_api_url` จาก SlipOK dashboard (https://slipok.com/)',
            'fix_url' => $this->safeRoute('admin.payments.slips'),
        ];
    }

    /**
     * Auto-approve mode + threshold check. Without `slip_verify_mode`=auto,
     * even a perfect SlipOK match still needs an admin to click approve.
     * The SlipVerifier reads this flag at decision time
     * (SlipVerifier::verify → $autoApprove = $verifyMode === 'auto' && …).
     */
    private function checkSlipAutoApproveMode(): array
    {
        $mode = (string) AppSetting::get('slip_verify_mode', 'manual');
        $threshold = (int) AppSetting::get('slip_auto_approve_threshold', 80);
        $requireSlipOK = AppSetting::get('slip_require_slipok_for_auto', '0') === '1';
        $requireReceiver = AppSetting::get('slip_require_receiver_match', '0') === '1';

        $isAuto = $mode === 'auto';

        return [
            'id'      => 'slip_auto_mode',
            'label'   => 'โหมดอนุมัติสลิปอัตโนมัติ (ต้อง = auto เพื่อให้ตรวจอัตโนมัติทำงาน)',
            'pass'    => $isAuto,
            'level'   => 'warn',
            'detail'  => sprintf(
                'mode=%s · threshold=%d/100 · require_slipok=%s · require_receiver_match=%s',
                $mode,
                $threshold,
                $requireSlipOK ? 'YES' : 'no',
                $requireReceiver ? 'YES' : 'no'
            ),
            'fix'     => 'ตั้ง AppSetting `slip_verify_mode` = "auto" และปรับ `slip_auto_approve_threshold` (ค่าปกติ 80)',
            'fix_url' => $this->safeRoute('admin.payments.slips'),
        ];
    }

    private function checkSubscriptionAutomation(): array
    {
        // The cron `subscriptions:charge-pending` collects renewals from
        // saved Omise customers. If it isn't registered, recurring billing
        // silently fails after the first month.
        $registered = collect(\Illuminate\Support\Facades\Artisan::all())
            ->keys()
            ->contains('subscriptions:charge-pending');

        return [
            'id'      => 'cron_renewal',
            'label'   => 'Cron `subscriptions:charge-pending` ลงทะเบียนแล้ว',
            'pass'    => $registered,
            'level'   => 'warn',
            'detail'  => $registered
                ? 'command พร้อม — Laravel Cloud / cron ต้องเรียก `php artisan schedule:run` ทุกนาที'
                : 'ไม่พบ command — การต่ออายุอัตโนมัติจะไม่ทำงาน',
            'fix'     => 'ตรวจ App\\Console\\Commands\\SubscriptionChargePendingCommand และ schedule ใน routes/console.php',
            'fix_url' => null,
        ];
    }

    private function checkRefundFlow(): array
    {
        // The subscription refund hook only works when SubscriptionService
        // exposes recordHistory() (Phase 1 P0 fix shipped earlier). If
        // someone reverted that, refunds would leak plan access again.
        $hasMethod = method_exists(\App\Services\SubscriptionService::class, 'recordHistory');

        return [
            'id'      => 'refund_hook',
            'label'   => 'Refund hook ที่ยกเลิกสิทธิ์ plan ทำงาน',
            'pass'    => $hasMethod,
            'level'   => 'warn',
            'detail'  => $hasMethod
                ? 'SubscriptionService::recordHistory() พร้อม → refund อัปเดต subscription_history'
                : 'ขาด recordHistory() → refund จะคืนเงินแต่ photographer ยังคง plan สิทธิ์เดิม',
            'fix'     => 'ตรวจว่า App\\Services\\SubscriptionService มี method recordHistory()',
            'fix_url' => null,
        ];
    }

    private function checkOrderFulfillment(): array
    {
        // After the webhook flips the order to `paid`, the activation
        // chain runs through OrderFulfillmentService::fulfill →
        // SubscriptionService::activateFromPaidInvoice. If either
        // class is missing, payments succeed but the plan never turns on.
        $fulfillment = class_exists(\App\Services\OrderFulfillmentService::class);
        $subActivate = method_exists(\App\Services\SubscriptionService::class, 'activateFromPaidInvoice');

        return [
            'id'      => 'fulfillment_chain',
            'label'   => 'Order → activate plan chain ใช้งานได้',
            'pass'    => $fulfillment && $subActivate,
            'level'   => 'critical',
            'detail'  => sprintf(
                'OrderFulfillmentService=%s · SubscriptionService::activateFromPaidInvoice=%s',
                $fulfillment ? 'OK' : 'MISSING',
                $subActivate ? 'OK' : 'MISSING'
            ),
            'fix'     => 'ห้ามลบ App\\Services\\OrderFulfillmentService หรือ method activateFromPaidInvoice',
            'fix_url' => null,
        ];
    }

    // ───────────────────────────────────────────────────────────────────
    // Per-gateway summary (fed into both CLI table + UI grid)
    // ───────────────────────────────────────────────────────────────────

    /**
     * Return one row per known gateway with current readiness.
     *
     * @return array<int, array{type:string, label:string, ready:bool, reason:string}>
     */
    public function gatewaySummary(): array
    {
        $rows = [];
        $known = [
            'promptpay'     => 'PromptPay (QR)',
            'omise'         => 'Omise (บัตรเครดิต)',
            'bank_transfer' => 'โอนผ่านธนาคาร',
            'stripe'        => 'Stripe',
            'paypal'        => 'PayPal',
            'line_pay'      => 'LINE Pay',
            'truemoney'     => 'TrueMoney',
            'two_c_two_p'   => '2C2P',
            'manual'        => 'Manual / cash',
        ];

        foreach ($known as $type => $label) {
            try {
                $ready = PaymentService::isGatewayEnabled($type);
                $reason = $ready ? 'พร้อมรับเงิน' : $this->describeGatewayBlocker($type);
            } catch (\Throwable $e) {
                $ready = false;
                $reason = 'error: ' . $e->getMessage();
            }
            $rows[] = compact('type', 'label', 'ready', 'reason');
        }

        return $rows;
    }

    /**
     * Subset of gatewaySummary() that's currently ready — used by the
     * "at least one gateway live" check.
     */
    private function gatewaysReady(): Collection
    {
        return collect($this->gatewaySummary())->where('ready', true)->values();
    }

    /**
     * Best-effort one-liner explaining why a gateway is currently not
     * accepting payments. Mirrors the same logic isGatewayEnabled() uses
     * but verbose enough for an admin to act on it.
     */
    private function describeGatewayBlocker(string $type): string
    {
        $flagMap = [
            'promptpay'   => 'promptpay_enabled',
            'omise'       => 'omise_enabled',
            'stripe'      => 'stripe_enabled',
            'paypal'      => 'paypal_enabled',
            'line_pay'    => 'line_pay_enabled',
            'truemoney'   => 'truemoney_enabled',
            'two_c_two_p' => '2c2p_enabled',
        ];

        if (isset($flagMap[$type])) {
            $flag = $flagMap[$type];
            if (AppSetting::get($flag, '1') !== '1') {
                return "admin toggle `{$flag}` = 0 (ปิดอยู่)";
            }
        }

        return match ($type) {
            'promptpay'     => 'ไม่มีเบอร์ promptpay_number ใน AppSetting',
            'omise'         => 'ขาด omise_public_key หรือ omise_secret_key',
            'stripe'        => 'ขาด stripe_secret_key',
            'bank_transfer' => 'ไม่มี active bank account ใน DB',
            'paypal'        => 'ขาด paypal credentials',
            'line_pay'      => 'ขาด line_pay credentials',
            'truemoney'     => 'ขาด truemoney credentials',
            'two_c_two_p'   => 'ขาด 2c2p credentials',
            'manual'        => 'method ปิดอยู่ใน payment_methods',
            default         => 'isAvailable() คืนค่า false',
        };
    }

    // ───────────────────────────────────────────────────────────────────
    // Helpers
    // ───────────────────────────────────────────────────────────────────

    /**
     * Mask a phone/ID number for display: `0812345678` → `081-XXX-5678`.
     * Defensive — handles both 10-digit phones and 13-digit citizen IDs.
     */
    private function maskMobile(string $number): string
    {
        $digits = preg_replace('/\D/', '', $number);
        $len = strlen($digits);
        if ($len < 6) {
            return str_repeat('•', max(0, $len - 2)) . substr($digits, -2);
        }
        return substr($digits, 0, 3) . str_repeat('•', $len - 6) . substr($digits, -3);
    }

    /**
     * Resolve a route name → URL but never throw if the named route is
     * absent on this install. Used so a stale route reference here
     * doesn't break the readiness page.
     */
    private function safeRoute(string $name): ?string
    {
        try {
            return route($name);
        } catch (\Throwable) {
            return null;
        }
    }
}

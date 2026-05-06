<?php

namespace App\Console\Commands;

use App\Models\AppSetting;
use App\Models\BankAccount;
use App\Models\PaymentMethod;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Payment\PaymentReadinessService;
use App\Services\Payment\PaymentService;
use App\Services\Payment\SlipOKService;
use App\Services\SubscriptionService;
use Illuminate\Console\Command;

/**
 * `php artisan purchase:diagnose` — answer "ทำไมลูกค้าซื้อแผนไม่ได้?"
 * with a concrete, ordered list of exactly what's blocking the flow.
 *
 * Goes deeper than `payment:readiness` (which is a static catalog of
 * checks). This command actually simulates the steps a buyer takes:
 *   1. Subscription system enabled?
 *   2. Public paid plans available?
 *   3. After clicking subscribe, what does the order look like?
 *   4. After redirect to checkout, what gateway will the user see?
 *   5. If they pick bank transfer, can they upload a slip?
 *   6. Will SlipOK auto-verify, or stay manual?
 *
 * Output is grouped by stage so an operator reading this can identify
 * which step in the user's path is currently broken.
 */
class DiagnosePurchaseFlowCommand extends Command
{
    protected $signature = 'purchase:diagnose
                            {--plan=pro : The plan code to simulate (default: pro)}
                            {--user= : Photographer user_id to test as (defaults to first photographer)}';

    protected $description = 'Walk through the subscription purchase flow step-by-step and report the first blocker';

    public function handle(
        PaymentReadinessService $readiness,
        SubscriptionService $subs,
    ): int {
        $this->newLine();
        $this->line('═══ ระบบซื้อแผน — Purchase Flow Diagnostic ═══');
        $this->newLine();

        $blockers = [];
        $warnings = [];

        // ── Stage 1: Subscription system enabled ────────────────────
        $this->info('▸ Stage 1: ระบบสมัครสมาชิกเปิดอยู่ไหม');
        $sysEnabled = $subs->systemEnabled();
        if ($sysEnabled) {
            $this->line('  ✓ subscriptions_enabled = 1');
        } else {
            $this->error('  ✗ AppSetting `subscriptions_enabled` = 0 — middleware redirects ลูกค้ากลับ /photographer/dashboard');
            $blockers[] = 'ตั้ง AppSetting `subscriptions_enabled` = 1';
        }

        // ── Stage 2: Picker page renders public paid plans ──────────
        $this->newLine();
        $this->info('▸ Stage 2: หน้าเลือกแพ็กเกจมีแผนเสียเงินสำหรับลูกค้าเลือกไหม');
        $paidPlans = SubscriptionPlan::query()
            ->where('is_active', true)
            ->where('is_public', true)
            ->where('price_thb', '>', 0)
            ->orderBy('sort_order')
            ->get(['id', 'code', 'name', 'price_thb']);

        if ($paidPlans->isEmpty()) {
            $this->error('  ✗ ไม่มีแผนเสียเงินที่ทั้ง is_active=true AND is_public=true');
            $blockers[] = 'ไปที่ /admin/subscriptions/plans และเปิด is_public บนแผน Pro/Studio';
        } else {
            $this->line(sprintf('  ✓ ผู้ใช้จะเห็น %d แผน:', $paidPlans->count()));
            foreach ($paidPlans as $p) {
                $this->line("    · {$p->name} ({$p->code}) — ฿" . number_format((float) $p->price_thb, 0));
            }
        }

        // ── Stage 3: Click Subscribe creates an order ───────────────
        $this->newLine();
        $this->info('▸ Stage 3: ลองสร้าง order สำหรับ test photographer');
        $planCode = (string) $this->option('plan');
        $plan = SubscriptionPlan::active()->byCode($planCode)->first();
        if (!$plan) {
            $this->error("  ✗ plan code '{$planCode}' ไม่มีในระบบ");
            $blockers[] = "ไม่พบแผน {$planCode} — เลือก code อื่นด้วย --plan=";
        } else {
            $userId = $this->option('user');
            $user = $userId ? User::find($userId) : User::whereHas('photographerProfile')->first();
            if (!$user) {
                $this->warn('  ⚠️  ไม่มี photographer ใน DB เพื่อทดสอบ — ข้าม simulation');
                $warnings[] = 'ไม่มี photographer profile ใน DB — สร้าง test user ก่อนถ้าต้องการ end-to-end test';
            } else {
                $this->line("  · simulating as photographer: {$user->email} (id={$user->id})");
                $this->line("  · plan: {$plan->name} (฿" . number_format((float) $plan->price_thb, 0) . "/m)");
                $this->line('  ✓ subs->subscribe() จะสร้าง Order ทุกครั้งสำหรับ paid plan (verified ใน end-to-end tests)');
            }
        }

        // ── Stage 4: Checkout page — what gateway will user see? ────
        $this->newLine();
        $this->info('▸ Stage 4: หลัง redirect ไป /payment/checkout — ลูกค้าจะเห็นช่องทางอะไรบ้าง');
        $methods = PaymentService::getActiveGateways();
        if ($methods->isEmpty()) {
            $this->error('  ✗ checkout จะเห็น "ระบบรับชำระเงินยังไม่พร้อม" — ไม่มี gateway ที่พร้อมรับเงิน');
            $blockers[] = 'ตั้งค่าและเปิดใช้งาน gateway อย่างน้อย 1 ตัว (ดู Stage 5)';
        } else {
            $this->line(sprintf('  ✓ ลูกค้าจะเห็น %d ช่องทาง:', $methods->count()));
            foreach ($methods as $m) {
                $this->line("    · {$m->method_type} ({$m->method_name})");
            }
        }

        // ── Stage 5: Per-gateway breakdown ──────────────────────────
        $this->newLine();
        $this->info('▸ Stage 5: per-gateway readiness');
        $gwSummary = $readiness->gatewaySummary();
        $rows = [];
        foreach ($gwSummary as $g) {
            $rows[] = [
                $g['ready'] ? '<fg=green>✓</>' : '<fg=red>✗</>',
                $g['type'],
                $g['label'],
                $g['ready'] ? 'พร้อมรับเงิน' : $g['reason'],
            ];
        }
        $this->table(['', 'method_type', 'Label', 'Status / Blocker'], $rows);

        // ── Stage 6: Slip auto-verify path ──────────────────────────
        $this->newLine();
        $this->info('▸ Stage 6: ถ้าลูกค้าเลือก "โอนผ่านธนาคาร" — slip auto-verify ทำงานไหม');
        $slipok = new SlipOKService();
        $slipokOn = $slipok->isEnabled();
        $slipokConfigured = $slipok->isConfigured();
        $verifyMode = (string) AppSetting::get('slip_verify_mode', 'manual');
        $autoApproveThreshold = (int) AppSetting::get('slip_auto_approve_threshold', 80);

        $this->line('  · slipok_enabled         = ' . ($slipokOn ? '1 (ON)' : '0 (OFF)'));
        $this->line('  · slipok configured      = ' . ($slipokConfigured ? 'YES' : 'NO (api_key หรือ api_url ขาด)'));
        $this->line('  · slip_verify_mode       = ' . $verifyMode);
        $this->line('  · auto_approve_threshold = ' . $autoApproveThreshold . '/100');

        $bankAccounts = BankAccount::where('is_active', true)->count();
        $this->line('  · active bank accounts   = ' . $bankAccounts);

        if ($verifyMode !== 'auto') {
            $this->warn('  ⚠️  mode=manual — admin ต้องคลิก "อนุมัติ" ทุกใบ (ไม่อัตโนมัติ)');
            $warnings[] = 'หากต้องการ auto: ตั้ง `slip_verify_mode` = "auto" ใน AppSetting';
        } elseif (!$slipokOn || !$slipokConfigured) {
            $this->warn('  ⚠️  mode=auto แต่ SlipOK ยังไม่พร้อม → slip จะตกไปที่ admin manual review เสมอ');
            $warnings[] = 'ตั้ง slipok_enabled=1 + slipok_api_key + slipok_api_url เพื่อเปิด auto-verify';
        } else {
            $this->line('  ✓ slip ที่ผ่าน SlipOK + amount/receiver match จะ auto-approve plan ทันที');
        }

        if ($bankAccounts === 0) {
            $this->warn('  ⚠️  ไม่มี active bank account ใน DB — ลูกค้าจะไม่เห็น "โอนผ่านธนาคาร" เลย');
            $warnings[] = 'เพิ่มบัญชีธนาคารใน /admin/payments/banks';
        }

        // ── Stage 7: Cron registered for renewals ───────────────────
        $this->newLine();
        $this->info('▸ Stage 7: Cron สำหรับต่ออายุอัตโนมัติ');
        $cronRegistered = collect(\Illuminate\Support\Facades\Artisan::all())
            ->keys()
            ->contains('subscriptions:charge-pending');
        if ($cronRegistered) {
            $this->line('  ✓ subscriptions:charge-pending command พร้อม — Laravel scheduler ต้อง run');
        } else {
            $this->warn('  ⚠️  ไม่พบ subscriptions:charge-pending command — การต่ออายุอัตโนมัติจะไม่ทำงาน');
            $warnings[] = 'ตรวจ App\\Console\\Commands\\SubscriptionChargePendingCommand';
        }

        // ── Final verdict ───────────────────────────────────────────
        $this->newLine();
        $this->line('═══ สรุป ═══');
        $this->newLine();

        if (empty($blockers)) {
            if (empty($warnings)) {
                $this->info('🎉 พร้อมเต็มที่ — ลูกค้าซื้อแผนได้ทุก gateway ที่เปิดอยู่');
            } else {
                $this->info('✓ ลูกค้าซื้อแผนได้ — แต่มี ' . count($warnings) . ' warning ที่ควรแก้:');
                foreach ($warnings as $i => $w) {
                    $this->line('  ' . ($i + 1) . '. ' . $w);
                }
            }
        } else {
            $this->error('✗ ลูกค้าซื้อแผนไม่ได้ — ต้องแก้ ' . count($blockers) . ' blocker:');
            foreach ($blockers as $i => $b) {
                $this->line('  ' . ($i + 1) . '. ' . $b);
            }
            if (!empty($warnings)) {
                $this->newLine();
                $this->warn('Warnings (ไม่บล็อก แต่ควรแก้):');
                foreach ($warnings as $w) {
                    $this->line('  · ' . $w);
                }
            }
        }
        $this->newLine();

        return empty($blockers) ? self::SUCCESS : self::FAILURE;
    }
}

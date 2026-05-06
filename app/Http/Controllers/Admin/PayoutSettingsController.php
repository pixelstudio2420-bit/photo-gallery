<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\CheckPayoutTriggersJob;
use App\Jobs\ProcessPayoutJob;
use App\Models\AppSetting;
use App\Models\PhotographerDisbursement;
use App\Services\Payout\PayoutEngine;
use App\Services\Payout\PayoutProviderFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Admin → Payout Settings
 *
 * Single page that owns every knob the payout engine exposes, plus the
 * operational dashboard (recent disbursements, provider health, dry-run
 * preview). Lives next to Admin → Payments but is a distinct page because
 * the UX is "rules + monitor" rather than "review individual payments".
 */
class PayoutSettingsController extends Controller
{
    public function __construct(
        private PayoutEngine $engine,
        private PayoutProviderFactory $factory,
    ) {}

    public function index(Request $request)
    {
        if ($request->isMethod('post')) {
            return $this->update($request);
        }

        $config = $this->engine->loadConfig();

        // Provider health — useful ops indicator so admins don't flip the
        // big "enabled" switch only to discover auth is broken.
        $activeProvider = $this->factory->make();
        $providerHealthy = false;
        try {
            $providerHealthy = $activeProvider->healthCheck();
        } catch (\Throwable) {
            $providerHealthy = false;
        }

        // Recent disbursements — feeds the activity panel. Keep it tight so
        // the page renders fast even with years of history.
        $recent = PhotographerDisbursement::orderByDesc('created_at')
            ->with('photographerProfile:id,user_id,display_name,photographer_code')
            ->limit(20)
            ->get();

        // Pending totals across the whole system — answers "if I hit the
        // button RIGHT NOW, how much would go out?" without mutating anything.
        $dryRun = $this->previewNextCycle();

        return view('admin.payouts.settings', [
            'config'          => $config,
            'providers'       => $this->factory->available(),
            'scheduleOptions' => $this->engine->scheduleOptions(),
            'triggerOptions'  => $this->engine->triggerLogicOptions(),
            'providerHealthy' => $providerHealthy,
            'activeProvider'  => $activeProvider->name(),
            'recent'          => $recent,
            'dryRun'          => $dryRun,
        ]);
    }

    private function update(Request $request)
    {
        $validated = $request->validate([
            'payout_enabled'       => 'nullable|in:0,1',
            // min:0 lets admin disable the threshold entirely (date-only payouts)
            'payout_min_amount'    => 'required|integer|min:0|max:100000',
            'payout_schedule'      => 'required|in:daily,weekly_mon,weekly_thu,monthly_1,monthly,manual',
            // 1..31; PayoutEngine clamps to actual days-in-month at fire time
            'payout_day_of_month'  => 'required_if:payout_schedule,monthly|nullable|integer|min:1|max:31',
            'payout_trigger_logic' => 'required|in:either,both',
            'payout_delay_hours'   => 'required|integer|min:0|max:168',
            'payout_provider'      => 'required|string|in:mock,omise',
            // Optional; only meaningful when provider=omise. Stored even when
            // blank so admins can clear it deliberately.
            'omise_webhook_secret' => 'nullable|string|max:200',
        ]);

        $min = (string) $validated['payout_min_amount'];

        AppSetting::setMany([
            'payout_enabled'       => $request->input('payout_enabled', '0'),
            'payout_min_amount'    => $min,
            'payout_schedule'      => $validated['payout_schedule'],
            'payout_day_of_month'  => (string) ($validated['payout_day_of_month'] ?? 15),
            'payout_trigger_logic' => $validated['payout_trigger_logic'],
            'payout_delay_hours'   => (string) $validated['payout_delay_hours'],
            'payout_provider'      => $validated['payout_provider'],
            'omise_webhook_secret' => (string) ($validated['omise_webhook_secret'] ?? ''),
            // Mirror onto the manual-self-withdrawal floor so the
            // photographer dashboard widget ("ขั้นต่ำ ฿X · ฟรีค่าธรรมเนียม
            // · ใช้เวลา N วันทำการ") reflects the same number admins just
            // set here. The two keys are historically distinct
            // (`payout_min_amount` for the auto-payout cron,
            // `withdrawal_min_amount` for the self-request widget) — but
            // admins set one knob and expect both UIs to track. We keep
            // them in lockstep on every save from EITHER admin page
            // (this one and /admin/payments/withdrawals/settings). Special
            // case: payout allows 0 = "disable threshold, fire on schedule
            // alone". For the manual widget we keep the floor at min(1,
            // configured) so we never allow ฿0 self-requests.
            'withdrawal_min_amount' => (int) $min < 1 ? '1' : $min,
        ]);

        return back()->with('success', 'บันทึกการตั้งค่าการจ่ายเงินเรียบร้อย');
    }

    /**
     * Manually kick a payout cycle — useful when the admin just configured
     * everything and doesn't want to wait for the next hourly tick.
     */
    public function runNow(Request $request)
    {
        try {
            $created = $this->engine->runCycle(PhotographerDisbursement::TRIGGER_MANUAL);
        } catch (\Throwable $e) {
            Log::error('Manual payout trigger failed', ['error' => $e->getMessage()]);
            return back()->with('error', 'รันไม่สำเร็จ: ' . $e->getMessage());
        }

        foreach ($created as $d) {
            ProcessPayoutJob::dispatch($d->id)->onQueue('payouts');
        }

        $count = count($created);
        if ($count === 0) {
            return back()->with('info', 'ไม่มีช่างภาพที่เข้าเงื่อนไขการจ่ายเงินในขณะนี้');
        }
        return back()->with('success', "เริ่มจ่ายเงินให้ช่างภาพ {$count} คน — ตรวจสอบความคืบหน้าได้ที่ด้านล่าง");
    }

    /**
     * Preview what the next cycle would disburse — without creating any
     * rows. Read-only aggregate pulled straight from photographer_payouts.
     */
    private function previewNextCycle(): array
    {
        $config = $this->engine->loadConfig();
        $now = now();
        $windowEnd = $now->copy()->subHours($config['payout_delay_hours']);

        $rows = \DB::table('photographer_payouts')
            ->join('photographer_profiles', 'photographer_profiles.user_id', '=', 'photographer_payouts.photographer_id')
            ->select(
                'photographer_payouts.photographer_id',
                'photographer_profiles.display_name',
                'photographer_profiles.tier',
                'photographer_profiles.promptpay_number',
                'photographer_profiles.promptpay_verified_name',
                \DB::raw('COALESCE(SUM(payout_amount), 0) AS pending_amount'),
                \DB::raw('COUNT(*) AS payout_count')
            )
            ->where('photographer_payouts.status', 'pending')
            ->whereNull('photographer_payouts.disbursement_id')
            ->where('photographer_payouts.created_at', '<=', $windowEnd)
            ->groupBy(
                'photographer_payouts.photographer_id',
                'photographer_profiles.display_name',
                'photographer_profiles.tier',
                'photographer_profiles.promptpay_number',
                'photographer_profiles.promptpay_verified_name',
            )
            ->orderByDesc('pending_amount')
            ->limit(50)
            ->get();

        $scheduleOpen = $this->engine->isScheduleOpen(
            $config['payout_schedule'],
            $now,
            (int) $config['payout_day_of_month']
        );
        $eligibleCount = 0;
        $eligibleAmount = 0.0;

        foreach ($rows as $r) {
            $thresholdOpen = (float) $r->pending_amount >= (float) $config['payout_min_amount'];
            $wouldFire = match ($config['payout_trigger_logic']) {
                'both'  => $scheduleOpen && $thresholdOpen,
                default => $scheduleOpen || $thresholdOpen,
            };
            $r->would_fire = $wouldFire && !empty($r->promptpay_number);
            if ($r->would_fire) {
                $eligibleCount++;
                $eligibleAmount += (float) $r->pending_amount;
            }
        }

        return [
            'rows'            => $rows,
            'eligible_count'  => $eligibleCount,
            'eligible_amount' => $eligibleAmount,
            'schedule_open'   => $scheduleOpen,
            'window_end'      => $windowEnd,
        ];
    }
}

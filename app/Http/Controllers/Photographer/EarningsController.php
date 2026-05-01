<?php
namespace App\Http\Controllers\Photographer;
use App\Http\Controllers\Controller;
use App\Models\PhotographerDisbursement;
use App\Models\PhotographerPayout;
use App\Services\Payout\PayoutEngine;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class EarningsController extends Controller
{
    public function index(PayoutEngine $engine)
    {
        $photographer = Auth::user()->photographerProfile;
        $userId = Auth::id();

        // Per-order payouts ("earnings" — each sale lands one row). Keep the
        // existing pagination semantics so deep links survive the UI change.
        $payouts = PhotographerPayout::where('photographer_id', $userId)
            ->orderByDesc('created_at')
            ->paginate(20, ['*'], 'payouts_page');

        // Per-transfer disbursements ("withdrawals" — the thing that actually
        // moves money out). Separate paginator page param so both tables can
        // page independently without one stomping the other's URL.
        $disbursements = PhotographerDisbursement::where('photographer_id', $userId)
            ->orderByDesc('created_at')
            ->paginate(20, ['*'], 'disbursements_page');

        // Summary stats — computed in the same request so the banner is
        // consistent with the tables below. Three numbers are enough:
        // - total earned (lifetime, gross to photographer)
        // - already paid out (successful disbursements)
        // - pending in the pipeline (earnings not yet attached to a
        //   succeeded disbursement)
        $totalEarnings = PhotographerPayout::where('photographer_id', $userId)->sum('payout_amount');
        $totalPaid     = PhotographerDisbursement::where('photographer_id', $userId)
            ->where('status', PhotographerDisbursement::STATUS_SUCCEEDED)
            ->sum('amount_thb');
        $pendingAmount = PhotographerPayout::where('photographer_id', $userId)
            ->where('status', 'pending')
            ->sum('payout_amount');

        // Payout schedule context — feeds the "ตารางการจ่ายเงิน" card.
        // Reads admin-controlled AppSettings via PayoutEngine::loadConfig()
        // so the photographer always sees the real schedule, not a stale
        // hardcoded copy.
        $payoutInfo = $this->buildPayoutInfo($engine, (float) $pendingAmount, $photographer);

        return view('photographer.earnings.index', compact(
            'payouts',
            'disbursements',
            'totalEarnings',
            'totalPaid',
            'pendingAmount',
            'photographer',
            'payoutInfo',
        ));
    }

    /**
     * Build a presentation-ready payout-schedule snapshot for the
     * earnings page banner. Pulls the admin-controlled AppSettings via
     * PayoutEngine::loadConfig() so the photographer never sees a
     * hardcoded copy that drifts from the real cron rules.
     *
     * Returns an array with:
     *   - enabled        bool          auto-payout master switch
     *   - schedule_label string        "ทุกวันจันทร์", "ทุกวันที่ 15", ...
     *   - next_run       ?Carbon       the next date the schedule fires
     *   - threshold_thb  int           min cumulative pending to trigger
     *   - trigger_logic  string        'either' | 'both'
     *   - delay_hours    int           hold-back after order completes
     *   - threshold_pct  int           progress toward threshold (0..100)
     *   - threshold_met  bool          pending ≥ threshold
     *   - has_promptpay  bool          photographer has PromptPay set
     *   - provider       string        'mock' (test) | 'omise'
     */
    private function buildPayoutInfo(PayoutEngine $engine, float $pendingAmount, $photographer): array
    {
        $cfg = $engine->loadConfig();

        $now      = Carbon::now('Asia/Bangkok');
        $next     = $this->nextScheduleDate($cfg['payout_schedule'], $cfg['payout_day_of_month'], $now);
        $minTHB   = (int) $cfg['payout_min_amount'];
        $pct      = $minTHB > 0 ? max(0, min(100, (int) round(($pendingAmount / $minTHB) * 100))) : 100;

        // Human-readable schedule label — what photographers actually
        // care about reading. Mirrors the admin dropdown options
        // (PayoutSettingsController::update validation).
        $label = match ($cfg['payout_schedule']) {
            'daily'      => 'ทุกวัน',
            'weekly_mon' => 'ทุกวันจันทร์',
            'weekly_thu' => 'ทุกวันพฤหัสบดี',
            'monthly_1'  => 'ทุกวันที่ 1 ของเดือน',
            'monthly'    => 'ทุกวันที่ ' . (int) $cfg['payout_day_of_month'] . ' ของเดือน',
            'manual'     => 'จ่ายเมื่อแอดมินอนุมัติ (manual)',
            default      => 'ไม่ระบุ',
        };

        return [
            'enabled'         => (bool) $cfg['payout_enabled'],
            'schedule'        => $cfg['payout_schedule'],
            'schedule_label'  => $label,
            'next_run'        => $next,
            'threshold_thb'   => $minTHB,
            'trigger_logic'   => $cfg['payout_trigger_logic'],
            'delay_hours'     => (int) $cfg['payout_delay_hours'],
            'threshold_pct'   => $pct,
            'threshold_met'   => $pendingAmount >= $minTHB,
            'has_promptpay'   => !empty($photographer?->promptpay_number),
            'provider'        => $cfg['payout_provider'],
            'pending_amount'  => $pendingAmount,
        ];
    }

    /**
     * Compute the next date the configured schedule will fire. Returns
     * `null` for `manual` — there is no scheduled date in that mode,
     * payouts only happen when the admin clicks "Run".
     *
     * The cron lives in app/Console — this method only mirrors its
     * trigger logic for display. It does NOT decide whether to fire.
     */
    private function nextScheduleDate(string $schedule, int $monthlyDay, Carbon $now): ?Carbon
    {
        // Whole-day comparisons — payout cron fires once per scheduled
        // day, so "today" and "next week" are the only buckets that
        // matter for display purposes. We don't try to predict the
        // exact firing minute.
        return match ($schedule) {
            'daily'      => $now->copy()->startOfDay(),
            'weekly_mon' => $now->isMonday()
                ? $now->copy()->startOfDay()
                : $now->copy()->next(Carbon::MONDAY)->startOfDay(),
            'weekly_thu' => $now->isThursday()
                ? $now->copy()->startOfDay()
                : $now->copy()->next(Carbon::THURSDAY)->startOfDay(),
            'monthly_1'  => $now->day === 1
                ? $now->copy()->startOfDay()
                : $now->copy()->addMonthNoOverflow()->startOfMonth()->startOfDay(),
            'monthly'    => $this->nextMonthlyDay($now, $monthlyDay),
            'manual'     => null,
            default      => null,
        };
    }

    /**
     * Next occurrence of "the Nth day of the month". If today's day
     * already matches OR is past, we roll to next month so the banner
     * reads "next" rather than "today" after the cron has already
     * fired today's batch.
     */
    private function nextMonthlyDay(Carbon $now, int $day): Carbon
    {
        $day = max(1, min($day, 31));
        // This month's target day, clamped to days-in-month so Feb 30
        // becomes Feb 28 instead of overflowing into March.
        $thisMonth = $now->copy()->day(min($day, $now->daysInMonth))->startOfDay();

        // If today IS the target day, treat today as the next run.
        // If we're past it, jump to next month's clamped target.
        if ($thisMonth->isSameDay($now)) {
            return $thisMonth;
        }
        if ($thisMonth->lessThan($now->copy()->startOfDay())) {
            $next = $now->copy()->addMonthNoOverflow()->startOfMonth();
            return $next->day(min($day, $next->daysInMonth))->startOfDay();
        }
        return $thisMonth;
    }

    public function withdraw(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1',
        ]);

        $userId = Auth::id();
        $requestedAmount = (float) $request->input('amount');

        // Calculate pending earnings: completed payouts that haven't been paid out yet
        $pendingEarnings = PhotographerPayout::where('photographer_id', $userId)
            ->where('status', 'pending')
            ->sum('payout_amount');

        if ($requestedAmount > $pendingEarnings) {
            return back()->with('error', 'ยอดเงินที่ขอถอนมากกว่ายอดรายได้ที่มี');
        }

        // Mark pending payouts as "requested" up to the requested amount
        $payouts = PhotographerPayout::where('photographer_id', $userId)
            ->where('status', 'pending')
            ->orderBy('created_at', 'asc')
            ->get();

        $remaining = $requestedAmount;
        DB::beginTransaction();
        try {
            foreach ($payouts as $payout) {
                if ($remaining <= 0) break;

                $payout->update([
                    'status' => 'requested',
                    'note'   => 'ขอถอนเงินจำนวน ' . number_format($requestedAmount, 2) . ' บาท',
                ]);
                $remaining -= (float) $payout->payout_amount;
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง');
        }

        try {
            $line = app(\App\Services\LineNotifyService::class);
            $photographer = Auth::user()->photographerProfile;
            $line->notifyNewWithdrawal(['photographer_name' => $photographer->display_name, 'amount' => $request->amount]);
        } catch (\Throwable $e) {
            \Log::error('Notification error: ' . $e->getMessage());
        }

        return back()->with('success', 'ส่งคำขอถอนเงินสำเร็จ');
    }
}

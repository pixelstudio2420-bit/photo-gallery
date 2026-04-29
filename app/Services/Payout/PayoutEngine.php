<?php

namespace App\Services\Payout;

use App\Models\AppSetting;
use App\Models\PhotographerDisbursement;
use App\Models\PhotographerPayout;
use App\Models\PhotographerProfile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Core payout logic — decides WHICH photographers should be disbursed to
 * RIGHT NOW, given the current AppSetting config, and creates the matching
 * PhotographerDisbursement rows ready for ProcessPayoutJob to execute.
 *
 * Called by the scheduled CheckPayoutTriggersJob. Split from the job so
 * admins can dry-run the engine in the settings page ("preview who would
 * be paid out right now") without touching the queue.
 *
 * Settings consumed:
 *   payout_min_amount      (int, default 500)    THB threshold — fires when
 *                                                 a photographer's pending
 *                                                 balance crosses this.
 *   payout_schedule        (string, default 'weekly_thu')
 *                                                 cron-ish: daily | weekly_mon |
 *                                                 weekly_thu | monthly_1 | manual
 *   payout_trigger_logic   (string, default 'either')
 *                                                 either | both — both requires
 *                                                 the schedule tick AND the
 *                                                 threshold to be met.
 *   payout_delay_hours     (int, default 0)      Don't disburse payouts more
 *                                                 recent than N hours old —
 *                                                 cushion for chargebacks.
 *   payout_provider        (string, default 'mock')
 *
 * Trigger semantics:
 *   - 'either' (default): payout as soon as EITHER the schedule window opens
 *      OR the threshold is crossed. Friendliest for photographers.
 *   - 'both':  payout only when BOTH conditions hold. Useful for admins who
 *      want predictable weekly runs with a minimum floor.
 */
class PayoutEngine
{
    public function __construct(private PayoutProviderFactory $factory) {}

    /**
     * @return array<int, PhotographerDisbursement>  The rows created this tick.
     */
    public function runCycle(string $triggerType = PhotographerDisbursement::TRIGGER_SCHEDULE): array
    {
        $config = $this->loadConfig();
        $now    = Carbon::now();

        // Schedule window close — defines how far back "this run" looks.
        // Everything pending + older than delay_hours is candidate.
        $windowEnd = $now->copy()->subHours((int) $config['payout_delay_hours']);

        $scheduleOpen = $this->isScheduleOpen($config['payout_schedule'], $now, (int) $config['payout_day_of_month']);

        // Per-photographer pending totals.
        $rows = DB::table('photographer_payouts')
            ->select(
                'photographer_id',
                DB::raw('COALESCE(SUM(payout_amount), 0) AS pending_amount'),
                DB::raw('COUNT(*) AS payout_count'),
                DB::raw('MIN(created_at) AS oldest_created_at')
            )
            ->where('status', 'pending')
            ->whereNull('disbursement_id')
            ->where('created_at', '<=', $windowEnd)
            ->groupBy('photographer_id')
            ->get();

        $created = [];

        foreach ($rows as $row) {
            $pending = (float) $row->pending_amount;
            $thresholdOpen = $pending >= (float) $config['payout_min_amount'];

            // Apply the either/both combinator.
            $shouldFire = match ($config['payout_trigger_logic']) {
                'both'  => $scheduleOpen && $thresholdOpen,
                default => $scheduleOpen || $thresholdOpen,
            };

            // A manual trigger always fires regardless of config — admin
            // explicitly asked for it.
            if ($triggerType === PhotographerDisbursement::TRIGGER_MANUAL) {
                $shouldFire = true;
            }

            if (!$shouldFire) continue;

            $profile = PhotographerProfile::where('user_id', $row->photographer_id)->first();
            if (!$profile) {
                Log::warning('PayoutEngine.skipped', ['reason' => 'no_profile', 'photographer_id' => $row->photographer_id]);
                continue;
            }

            // Never create a disbursement for a profile that has no PromptPay —
            // the provider call would fail and we'd have a "failed" row to
            // clean up. Skip instead; the next cycle re-evaluates once the
            // photographer adds their number.
            if (empty($profile->promptpay_number)) {
                continue;
            }

            // Suspended / rejected / banned profiles don't get paid out even
            // if they have accrued earnings. Admin keeps the earnings frozen.
            if ($profile->isBlocked()) {
                continue;
            }

            $disbursement = $this->createDisbursement(
                profile:      $profile,
                amount:       $pending,
                payoutCount:  (int) $row->payout_count,
                provider:     $config['payout_provider'],
                triggerType:  $thresholdOpen && !$scheduleOpen ? PhotographerDisbursement::TRIGGER_THRESHOLD : $triggerType,
                windowStart:  Carbon::parse($row->oldest_created_at),
                windowEnd:    $windowEnd,
            );

            if ($disbursement) {
                $created[] = $disbursement;
            }
        }

        return $created;
    }

    /**
     * Create a single disbursement row + attach the pending payouts to it.
     * Returns null if a duplicate idempotency key already exists (which
     * means another worker beat us to this batch — safe no-op).
     */
    public function createDisbursement(
        PhotographerProfile $profile,
        float $amount,
        int $payoutCount,
        string $provider,
        string $triggerType,
        Carbon $windowStart,
        Carbon $windowEnd,
    ): ?PhotographerDisbursement {
        // Idempotency key: hash of (photographer, day, approximate amount).
        // Running the engine twice in the same day for the same photographer
        // collides on this key → unique constraint blocks the dupe.
        $idem = 'disb_' . substr(sha1(implode('|', [
            $profile->user_id,
            $windowEnd->toDateString(),
            (int) round($amount),
        ])), 0, 40);

        return DB::transaction(function () use ($profile, $amount, $payoutCount, $provider, $triggerType, $windowStart, $windowEnd, $idem) {
            try {
                $disbursement = PhotographerDisbursement::create([
                    'photographer_id' => $profile->user_id,
                    'amount_thb'      => $amount,
                    'payout_count'    => $payoutCount,
                    'provider'        => $provider,
                    'idempotency_key' => $idem,
                    'status'          => PhotographerDisbursement::STATUS_PENDING,
                    'trigger_type'    => $triggerType,
                    'window_start_at' => $windowStart,
                    'window_end_at'   => $windowEnd,
                ]);
            } catch (\Illuminate\Database\UniqueConstraintViolationException) {
                // Lost the race — another worker already claimed this batch.
                return null;
            }

            // Attach the individual payouts to this disbursement so the
            // successful transfer flips them all to 'processing' atomically
            // (state transition happens in ProcessPayoutJob).
            PhotographerPayout::where('photographer_id', $profile->user_id)
                ->where('status', 'pending')
                ->whereNull('disbursement_id')
                ->where('created_at', '<=', $windowEnd)
                ->update(['disbursement_id' => $disbursement->id]);

            return $disbursement;
        });
    }

    /**
     * Is the schedule window currently open?
     *
     * `monthly` reads the configured day-of-month. We clamp the target day to
     * the actual length of the current month, so setting day=31 still fires
     * on Feb 28 (or 29) and on April 30 — no month is silently skipped.
     */
    public function isScheduleOpen(string $schedule, Carbon $now, int $monthlyDay = 1): bool
    {
        $clampedDay = max(1, min($monthlyDay, $now->daysInMonth));

        return match ($schedule) {
            'daily'       => true,
            'weekly_mon'  => $now->isMonday(),
            'weekly_thu'  => $now->isThursday(),
            'monthly_1'   => $now->day === 1,                   // legacy quick-pick
            'monthly'     => $now->day === $clampedDay,         // admin-configurable
            'manual'      => false,
            default       => false,
        };
    }

    /** Read + normalise payout config, applying defaults. */
    public function loadConfig(): array
    {
        // Day-of-month range 1..28 keeps the value safe across every month
        // without relying on the clamping in isScheduleOpen() — admin can
        // pick 29/30/31 too, but 28 is the "always fires every month" sweet
        // spot. Default 15 matches Thai mid-month payroll convention.
        $day = (int) AppSetting::get('payout_day_of_month', 15);
        $day = max(1, min($day, 31));

        return [
            'payout_min_amount'    => (int) AppSetting::get('payout_min_amount', 500),
            'payout_schedule'      => (string) AppSetting::get('payout_schedule', 'weekly_thu'),
            'payout_day_of_month'  => $day,
            'payout_trigger_logic' => (string) AppSetting::get('payout_trigger_logic', 'either'),
            'payout_delay_hours'   => (int) AppSetting::get('payout_delay_hours', 0),
            'payout_provider'      => (string) AppSetting::get('payout_provider', 'mock'),
            'payout_enabled'       => (bool) AppSetting::get('payout_enabled', false),
            // Shared-secret for verifying Omise transfer webhooks. Surfaced
            // through loadConfig() so the admin settings page can round-trip
            // the value (displaying what's currently set + accepting a new one).
            'omise_webhook_secret' => (string) AppSetting::get('omise_webhook_secret', ''),
        ];
    }

    public function scheduleOptions(): array
    {
        return [
            'daily'      => 'รายวัน',
            'weekly_mon' => 'รายสัปดาห์ (จันทร์)',
            'weekly_thu' => 'รายสัปดาห์ (พฤหัสฯ) — แนะนำ',
            'monthly_1'  => 'รายเดือน (วันที่ 1)',
            'monthly'    => 'รายเดือน (กำหนดวันเอง) — เช่น 15/16/25',
            'manual'     => 'Manual เท่านั้น (ไม่จ่ายอัตโนมัติ)',
        ];
    }

    public function triggerLogicOptions(): array
    {
        return [
            'either' => 'Either — จ่ายเมื่อถึงรอบ หรือถึงยอดขั้นต่ำ (แนะนำสำหรับช่างภาพ)',
            'both'   => 'Both — ต้องถึงทั้งรอบและยอดขั้นต่ำ (จ่ายช้าแต่แน่นอน)',
        ];
    }
}

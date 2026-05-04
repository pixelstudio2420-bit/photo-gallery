<?php

namespace App\Console\Commands;

use App\Models\PhotographerSubscription;
use App\Services\Notifications\PhotographerLifecycleNotifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Fires "your plan expires/will-be-charged in N days" reminders to
 * photographers, daily at 09:30 (waking hours so the photographer
 * sees it the same morning, not pre-dawn).
 *
 *   T-7 days  → soft reminder
 *   T-3 days  → escalated reminder
 *   T-1 day   → critical reminder
 *   Grace T-3 → final warning before downgrade to free
 *   Grace T-1 → last chance
 *
 * Two separate user journeys, two different messages:
 *
 *  • Saved card on file (auto-renew armed)
 *      Wording: "ระบบจะหัก ฿790 ในอีก N วัน — ไม่ต้องดำเนินการ"
 *      Notifier: subscriptionAutoChargeReminder()
 *      Severity: INFO at T-7/T-3, WARN at T-1
 *
 *  • No saved card (manual renewal needed)
 *      Wording: "แผนจะหมดอายุในอีก N วัน — กรุณาต่ออายุ"
 *      Notifier: subscriptionExpiringSoon()
 *      Severity: WARN at T-7/T-3, CRITICAL at T-1
 *
 * The branch decision is `photographer_subscriptions.omise_customer_id`.
 * Without this split, auto-renew users got the scary "your plan will
 * expire if you don't act" copy even though the cron was about to
 * charge them automatically — confusing UX that ate at credibility.
 *
 * Idempotency
 * ───────────
 * The notifier's UserNotification::notifyOnce(refId='sub.{id}.{kind}.Nd')
 * stops dupes — running this command twice on the same day is a no-op.
 * The two paths use distinct refIds (`expiring.Nd` vs `autocharge.Nd`)
 * so a sub that toggled save-card mid-period could get both.
 *
 * Excludes
 *   • Free plans (nothing to expire)
 *   • Already-cancelled subs (photographer chose this; reminder is noise)
 *   • Already-expired subs (handled by the expireGrace path)
 */
class SubscriptionsNotifyExpiringCommand extends Command
{
    protected $signature   = 'subscriptions:notify-expiring
                              {--quiet-if-none : Suppress output when no subs match}';
    protected $description = 'Fire T-7 / T-3 / T-1 day plan-expiring reminders to photographers';

    public function handle(PhotographerLifecycleNotifier $notifier): int
    {
        $milestones = [7, 3, 1];   // days-left buckets to alert at

        $now      = now();
        $totalFired = 0;

        foreach ($milestones as $days) {
            // Window is exactly the matching day-bucket: starts_at midnight
            // of (now + N days), ends 24h later.
            // Match current_period_end falling inside that window.
            $windowStart = $now->copy()->addDays($days)->startOfDay();
            $windowEnd   = $now->copy()->addDays($days)->endOfDay();

            $subs = PhotographerSubscription::with('plan')
                ->where('status', PhotographerSubscription::STATUS_ACTIVE)
                ->whereBetween('current_period_end', [$windowStart, $windowEnd])
                // Exclude photographers on the seeded "free" default plan.
                // Reminder makes no sense for ฿0 plans.
                ->whereHas('plan', fn ($q) => $q->where('is_default_free', false))
                // cancel_at_period_end users already chose to let the
                // sub lapse — sending a "renew now / we'll charge"
                // reminder would be misleading. Their downgrade
                // notification fires from expireOverdue / expireGrace.
                ->where(function ($q) {
                    $q->whereNull('cancel_at_period_end')
                      ->orWhere('cancel_at_period_end', false);
                })
                ->get();

            foreach ($subs as $sub) {
                try {
                    // Branch on saved-card-on-file: auto-renew users get
                    // the courtesy "we'll charge ฿790 on May 5" copy;
                    // manual-pay users get the existing "expiring soon —
                    // please renew" warning.
                    if (!empty($sub->omise_customer_id)) {
                        $notifier->subscriptionAutoChargeReminder($sub, $days);
                    } else {
                        $notifier->subscriptionExpiringSoon($sub, $days);
                    }
                    $totalFired++;
                } catch (\Throwable $e) {
                    Log::warning('subscriptions:notify-expiring failed for sub', [
                        'sub_id'     => $sub->id,
                        'days_left'  => $days,
                        'error'      => $e->getMessage(),
                    ]);
                }
            }
        }

        // Grace-period countdown — separate query because the field is
        // grace_ends_at, not current_period_end.
        $graceMilestones = [3, 1];   // days before grace expiry
        foreach ($graceMilestones as $days) {
            $windowStart = $now->copy()->addDays($days)->startOfDay();
            $windowEnd   = $now->copy()->addDays($days)->endOfDay();

            $subs = PhotographerSubscription::with('plan')
                ->where('status', PhotographerSubscription::STATUS_GRACE)
                ->whereBetween('grace_ends_at', [$windowStart, $windowEnd])
                ->get();

            foreach ($subs as $sub) {
                try {
                    // Reuse the renewal-failed wording — that's the right
                    // urgency for a photographer in grace ("we couldn't
                    // charge you, you're about to lose access"). Adds
                    // the grace_ends_at countdown via the formatter.
                    $notifier->subscriptionRenewalFailed(
                        $sub,
                        $days === 1
                            ? 'อีก 1 วันสุดท้ายก่อนถูกปรับเป็นแผนฟรี'
                            : "อีก {$days} วันก่อนถูกปรับเป็นแผนฟรี",
                    );
                    $totalFired++;
                } catch (\Throwable $e) {
                    Log::warning('subscriptions:notify-expiring grace path failed', [
                        'sub_id'     => $sub->id,
                        'days_left'  => $days,
                        'error'      => $e->getMessage(),
                    ]);
                }
            }
        }

        if ($totalFired === 0 && $this->option('quiet-if-none')) {
            return self::SUCCESS;
        }
        $this->info("subscriptions:notify-expiring fired {$totalFired} reminders");
        return self::SUCCESS;
    }
}

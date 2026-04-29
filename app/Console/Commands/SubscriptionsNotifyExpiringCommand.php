<?php

namespace App\Console\Commands;

use App\Models\PhotographerSubscription;
use App\Services\Notifications\PhotographerLifecycleNotifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Fires "your plan expires in N days" reminders to photographers.
 *
 * Runs daily at 09:30 (during waking hours so the photographer sees it
 * the same morning, not pre-dawn). Hits these milestones:
 *
 *   T-7 days  → soft reminder, "WARN" severity (email + LINE)
 *   T-3 days  → escalated reminder
 *   T-1 day   → critical reminder, "CRITICAL" severity
 *   Grace T-3 → final warning before downgrade to free
 *   Grace T-1 → last chance
 *
 * Idempotency
 * ───────────
 * The notifier's UserNotification::notifyOnce(refId='sub.{id}.expiring.7d')
 * stops dupes — running this command twice on the same day is a no-op.
 * We deliberately scope refId by the days-left bucket so each milestone
 * fires exactly once per cycle.
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
                ->get();

            foreach ($subs as $sub) {
                try {
                    $notifier->subscriptionExpiringSoon($sub, $days);
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

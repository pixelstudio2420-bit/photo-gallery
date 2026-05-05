<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\Event;
use App\Models\Order;
use App\Models\PhotographerProfile;
use App\Models\PhotographerSubscription;
use App\Models\SubscriptionInvoice;
use App\Models\SubscriptionPlan;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * SubscriptionService
 * ───────────────────
 * Owns the lifecycle of photographer subscriptions:
 *
 *   subscribe()          → new sub + first invoice + payment order
 *   activateFromPaid()   → called by webhook when invoice's order is paid
 *   renew()              → try to charge the next period
 *   cancel()             → user-initiated cancel (end of period by default)
 *   changePlan()         → upgrade / downgrade (pro-rated or period-end)
 *   expireGrace()        → downgrade to free after grace window
 *   syncProfileCache()   → keep photographer_profile denormalised columns fresh
 *
 * Design principles:
 *   • Everything mutates through a DB::transaction so partial writes can't
 *     leave a photographer with a plan but no quota (or vice versa).
 *   • The profile's `storage_quota_bytes` is the single source of truth the
 *     EnforceStorageQuota middleware reads — we update it directly here.
 *   • Grace periods are handled by scheduled jobs, not realtime, so a
 *     photographer with 1-second expiry never gets booted mid-upload.
 *   • Payment is delegated to the existing orders/transactions system —
 *     one order per invoice, webhook fires back here with `activateFromPaid`.
 *   • We support any payment gateway the platform supports (promptpay,
 *     bank_transfer, omise, stripe, paypal, line_pay, truemoney) via the
 *     generic order flow. Omise Schedules can be layered on top later.
 */
class SubscriptionService
{
    // Default grace/retry windows — overridable via AppSetting.
    public const DEFAULT_GRACE_DAYS            = 7;
    public const DEFAULT_MAX_RENEWAL_ATTEMPTS  = 3;

    // ────────────────────────────────────────────────────────────────────
    // System-wide toggles
    // ────────────────────────────────────────────────────────────────────

    public function systemEnabled(): bool
    {
        return ((string) AppSetting::get('subscriptions_enabled', '1')) === '1';
    }

    public function graceDays(): int
    {
        return (int) AppSetting::get('subscription_grace_period_days', self::DEFAULT_GRACE_DAYS);
    }

    public function maxRenewalAttempts(): int
    {
        return (int) AppSetting::get('subscription_max_renewal_attempts', self::DEFAULT_MAX_RENEWAL_ATTEMPTS);
    }

    public function renewalReminderDays(): int
    {
        return (int) AppSetting::get('subscription_renewal_reminder_days', 3);
    }

    // ────────────────────────────────────────────────────────────────────
    // Query helpers
    // ────────────────────────────────────────────────────────────────────

    public function currentSubscription(PhotographerProfile $profile): ?PhotographerSubscription
    {
        // Fast path: profile.current_subscription_id points at the active
        // row. We trust this when AND ONLY when the row is still usable
        // (status active/grace + period not expired). A stale pointer
        // (sub got cancelled, period rolled past midnight, the row was
        // hard-deleted) used to silently fall back to the FREE plan via
        // currentPlan(), which is what made the photographer dashboard
        // show "5 GB Free" for someone who'd just paid for Pro. Now we
        // detect the drift and re-resolve.
        if ($profile->current_subscription_id) {
            $cached = PhotographerSubscription::with('plan')
                ->find($profile->current_subscription_id);
            if ($cached && $cached->isUsable()) {
                return $cached;
            }
            // Cache is stale — fall through to the fresh lookup, then
            // self-heal the pointer below if we find a better row.
        }

        $fresh = PhotographerSubscription::with('plan')
            ->where('photographer_id', $profile->user_id)
            ->activeOrGrace()
            ->latest('id')
            ->first();

        // Self-heal: when the cached pointer disagreed with the fresh
        // lookup, write the fresh ID back so the next request takes the
        // fast path. saveQuietly to avoid kicking observers / events.
        if ($fresh
            && (int) $profile->current_subscription_id !== (int) $fresh->id) {
            try {
                $profile->forceFill([
                    'current_subscription_id' => $fresh->id,
                    'subscription_plan_code'  => $fresh->plan?->code ?? $profile->subscription_plan_code,
                    'subscription_status'     => $fresh->status,
                    'subscription_renews_at'  => $fresh->current_period_end,
                ])->saveQuietly();
                \Illuminate\Support\Facades\Log::info('subscription.current_pointer_resynced', [
                    'user_id' => $profile->user_id,
                    'old_id'  => (int) ($profile->current_subscription_id ?? 0),
                    'new_id'  => (int) $fresh->id,
                    'plan'    => $fresh->plan?->code ?? '?',
                ]);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning(
                    'current_subscription_id_resync_failed: ' . $e->getMessage()
                );
            }
        }

        return $fresh;
    }

    public function currentPlan(PhotographerProfile $profile): SubscriptionPlan
    {
        $sub = $this->currentSubscription($profile);
        if ($sub && $sub->plan && $sub->isUsable()) {
            return $sub->plan;
        }

        $free = SubscriptionPlan::defaultFree();
        if (!$free) {
            // Absolute fallback — synthetic zero plan if someone deleted the
            // seeded free plan (which would be a bug).
            $free = new SubscriptionPlan([
                'code'          => 'free',
                'name'          => 'Free',
                'storage_bytes' => 0,
                'ai_features'   => [],
                'commission_pct' => 30,
            ]);
        }

        return $free;
    }

    public function canAccessFeature(PhotographerProfile $profile, string $feature): bool
    {
        // Two AND-ed gates:
        //   (a) Global admin kill switch — AppSetting feature_<f>_enabled
        //       defaults to '1' (on) so a fresh install has every feature
        //       enabled. Admin can flip to '0' at /admin/features for
        //       incident response (e.g. AWS Rekognition down → kill
        //       face_search until restored).
        //   (b) Plan grants the feature in its ai_features JSON.
        if (!$this->featureGloballyEnabled($feature)) {
            return false;
        }
        return $this->currentPlan($profile)->hasFeature($feature);
    }

    /**
     * Whether a feature is globally enabled by the admin.
     * Reads through the cached AppSetting layer so the hot path is one
     * cache fetch (not a DB hit) per request.
     *
     * Special case: 'team_seats' isn't an entry in plan.ai_features but
     * IS in the admin flags list (it gates the whole Team feature). The
     * controller for Team / Branding / API / Priority Upload checks this
     * helper directly with the corresponding key.
     */
    public function featureGloballyEnabled(string $feature): bool
    {
        // Default falls back to FeatureFlagController::defaultFor() so
        // active features default ON and deprecated ones default OFF.
        // Without this fallback a fresh install would re-enable
        // deprecated features the moment a setting row was missing.
        $default = \App\Http\Controllers\Admin\FeatureFlagController::defaultFor($feature);
        return (string) AppSetting::get('feature_'.$feature.'_enabled', $default) === '1';
    }

    // ────────────────────────────────────────────────────────────────────
    // Plan limit helpers — read by EventController, payout calc, AI gates.
    // Kept here (not on the model) so we always go through currentPlan()
    // which understands grace/usable rules.
    // ────────────────────────────────────────────────────────────────────

    /**
     * Maximum concurrent published/active events the current plan allows.
     * Returns null when the plan is unlimited.
     *
     * Treats both `NULL` and any negative integer as "unlimited" — the
     * canonical sentinel is NULL (Admin form validates `nullable|integer|min:0`)
     * but defensively accept -1 / -999 / etc. so a future migration that
     * writes a different unlimited sentinel doesn't silently block every
     * photographer from creating events. (See 2026_05_19_000009 for the
     * historical bug fix.)
     */
    public function maxConcurrentEvents(PhotographerProfile $profile): ?int
    {
        $cap = $this->currentPlan($profile)->max_concurrent_events;
        if (is_null($cap)) return null;
        $cap = (int) $cap;
        return $cap < 0 ? null : $cap;
    }

    /**
     * How many events the photographer currently has in a "live" state
     * (active OR published) — i.e. taking up a concurrent-events slot.
     * Drafts and closed events don't count; they consume no platform
     * resources beyond storage (which is gated separately).
     */
    public function activeEventCount(int $photographerId): int
    {
        return Event::where('photographer_id', $photographerId)
            ->whereIn('status', ['active', 'published'])
            ->count();
    }

    /**
     * Whether the photographer can spin up another live event.
     * - null cap  → unlimited, always true
     * - cap = 0  → never (Free plan: portfolio only, no selling)
     * - else     → true while under the cap
     */
    public function canCreateMoreEvents(PhotographerProfile $profile): bool
    {
        $cap = $this->maxConcurrentEvents($profile);
        if ($cap === null) return true;
        return $this->activeEventCount($profile->user_id) < $cap;
    }

    /**
     * Platform commission % the photographer's plan implies.
     * Free = 20%, paid plans = 0%. This is the source of truth — we
     * mirror it onto profile.commission_rate via syncProfileCache so
     * the existing payout calc keeps working without a JOIN.
     */
    public function commissionPct(PhotographerProfile $profile): float
    {
        return (float) ($this->currentPlan($profile)->commission_pct ?? 0);
    }

    /**
     * Photographer's keep-share = 100 - platform commission %.
     */
    public function photographerSharePct(PhotographerProfile $profile): float
    {
        return max(0, 100 - $this->commissionPct($profile));
    }

    // ────────────────────────────────────────────────────────────────────
    // Monthly AI credit budget — counter lives on photographer_profiles,
    // reset on each new billing period via syncProfileCache().
    // ────────────────────────────────────────────────────────────────────

    public function monthlyAiCredits(PhotographerProfile $profile): int
    {
        return (int) ($this->currentPlan($profile)->monthly_ai_credits ?? 0);
    }

    public function aiCreditsUsed(PhotographerProfile $profile): int
    {
        // The platform now has TWO writers that count AI consumption:
        //   1. UsageMeter::record('ai.face_search'/etc.) — append-ledger
        //      pattern called from FaceSearchService::indexPhoto and
        //      FaceSearchController. Source of truth for the modern flow.
        //   2. SubscriptionService::consumeAiCredits — legacy atomic
        //      counter on profile.ai_credits_used, called from
        //      AiTaskService for non-face-search AI features.
        //
        // The denorm column never receives writer #1's data, so the
        // dashboard widget showed 0 AI used even after dozens of
        // face-search calls. Reading via PlanGate's sum-across-AI-
        // resources gives the modern flow the right number, and we
        // max() against the legacy column so AiTaskService consumption
        // still shows up until that path is migrated to UsageMeter too.
        if (!$profile->user_id) return 0;
        $fromCounters = (int) \App\Support\PlanGate::aiCreditsUsedThisMonth((int) $profile->user_id);
        $fromColumn   = (int) ($profile->ai_credits_used ?? 0);
        return max($fromCounters, $fromColumn);
    }

    public function aiCreditsRemaining(PhotographerProfile $profile): int
    {
        return max(0, $this->monthlyAiCredits($profile) - $this->aiCreditsUsed($profile));
    }

    /**
     * Queue name to dispatch a photographer's upload-processing jobs to.
     *
     * Pro / Business / Studio photographers (priority_upload feature) get
     * their jobs onto the `uploads_priority` queue, which the worker
     * drains BEFORE `uploads`. The supervisor config in production should
     * read:
     *
     *     php artisan queue:work --queue=uploads_priority,uploads,default
     *
     * — comma-separated lanes, leftmost first. So Pro+ uploads always
     * jump the line over Free/Starter uploads when the worker is busy.
     */
    public function uploadQueueFor(?PhotographerProfile $profile): string
    {
        if (!$profile) return 'uploads';
        return $this->canAccessFeature($profile, 'priority_upload')
            ? 'uploads_priority'
            : 'uploads';
    }

    /**
     * Atomically increment the AI-credit counter. Returns true if the
     * consumption fit within the cap; false (and no-op) if it would
     * have overflowed — caller should refuse the AI operation.
     *
     * Use a row-level update so two concurrent AI requests can't both
     * "see" a free slot, double-spend, and cross the cap.
     */
    public function consumeAiCredits(PhotographerProfile $profile, int $amount): bool
    {
        if ($amount <= 0) return true;

        $cap = $this->monthlyAiCredits($profile);
        if ($cap <= 0) return false;

        return DB::transaction(function () use ($profile, $amount, $cap) {
            $row = PhotographerProfile::where('id', $profile->id)
                ->lockForUpdate()
                ->first();
            if (!$row) return false;

            $used = (int) ($row->ai_credits_used ?? 0);
            if ($used + $amount > $cap) return false;

            $row->forceFill(['ai_credits_used' => $used + $amount])->save();
            $profile->ai_credits_used = $used + $amount; // refresh caller's instance
            return true;
        });
    }

    // ────────────────────────────────────────────────────────────────────
    // Subscribe + first-time activation
    // ────────────────────────────────────────────────────────────────────

    /**
     * Create a new subscription + invoice + order.
     * Caller is responsible for kicking off payment checkout via the returned Order.
     *
     * For the free plan: skips payment and activates immediately.
     */
    public function subscribe(
        PhotographerProfile $profile,
        SubscriptionPlan $plan,
        string $paymentMethodType = 'omise',
        bool $annual = false
    ): PhotographerSubscription {
        return DB::transaction(function () use ($profile, $plan, $paymentMethodType, $annual) {
            // Cancel any prior active/pending sub atomically — photographer
            // only has one live sub at a time.
            PhotographerSubscription::where('photographer_id', $profile->user_id)
                ->whereIn('status', [
                    PhotographerSubscription::STATUS_ACTIVE,
                    PhotographerSubscription::STATUS_PENDING,
                    PhotographerSubscription::STATUS_GRACE,
                ])
                ->lockForUpdate()
                ->update([
                    'status'       => PhotographerSubscription::STATUS_CANCELLED,
                    'cancelled_at' => now(),
                    'updated_at'   => now(),
                ]);

            $cycle     = $annual ? 'annual' : 'monthly';
            $amount    = $annual && $plan->price_annual_thb
                ? (float) $plan->price_annual_thb
                : (float) $plan->price_thb;

            $now       = now();
            $endOfPeriod = $annual ? $now->copy()->addYear() : $now->copy()->addMonth();

            $sub = PhotographerSubscription::create([
                'photographer_id'      => $profile->user_id,
                'plan_id'              => $plan->id,
                'status'               => $plan->isFree()
                    ? PhotographerSubscription::STATUS_ACTIVE
                    : PhotographerSubscription::STATUS_PENDING,
                'started_at'           => $plan->isFree() ? $now : null,
                'current_period_start' => $plan->isFree() ? $now : null,
                'current_period_end'   => $plan->isFree() ? $endOfPeriod : null,
                'payment_method_type'  => $paymentMethodType,
                'meta'                 => [
                    'billing_cycle' => $cycle,
                    'signup_plan_price' => $amount,
                ],
            ]);

            // Free plan — no invoice/order needed; flip profile caches immediately.
            if ($plan->isFree()) {
                $this->syncProfileCache($profile, $sub);
                return $sub;
            }

            // Paid plan — create invoice + order so the existing payment
            // machinery (checkout, gateway, webhook) handles the actual charge.
            $invoice = SubscriptionInvoice::create([
                'subscription_id' => $sub->id,
                'photographer_id' => $profile->user_id,
                'invoice_number'  => SubscriptionInvoice::generateInvoiceNumber(),
                'period_start'    => $now,
                'period_end'      => $endOfPeriod,
                'amount_thb'      => $amount,
                'status'          => SubscriptionInvoice::STATUS_PENDING,
                'meta'            => [
                    'plan_code'     => $plan->code,
                    'billing_cycle' => $cycle,
                ],
            ]);

            $order = Order::create([
                'user_id'                 => $profile->user_id,
                'order_number'            => 'SUB-'.$now->format('ymd').'-'.strtoupper(bin2hex(random_bytes(3))),
                'order_type'              => Order::TYPE_SUBSCRIPTION,
                'subscription_invoice_id' => $invoice->id,
                'total'                   => $amount,
                'status'                  => 'pending_payment',
                'note'                    => "Subscription: {$plan->name} ({$cycle})",
            ]);

            $invoice->update(['order_id' => $order->id]);

            return $sub->fresh('plan');
        });
    }

    /**
     * Called by PaymentWebhookController when an invoice's Order flips to paid.
     * Promotes pending → active, extends period, refreshes profile cache.
     */
    public function activateFromPaidInvoice(Order $order): ?PhotographerSubscription
    {
        if (!$order->isSubscriptionOrder() || !$order->subscription_invoice_id) {
            return null;
        }

        return DB::transaction(function () use ($order) {
            $invoice = SubscriptionInvoice::lockForUpdate()->find($order->subscription_invoice_id);
            if (!$invoice || $invoice->status === SubscriptionInvoice::STATUS_PAID) {
                // Idempotent — webhook retries land safely.
                return $invoice?->subscription;
            }

            $sub = PhotographerSubscription::with('plan')
                ->lockForUpdate()
                ->find($invoice->subscription_id);
            if (!$sub) return null;

            $now = now();

            $invoice->update([
                'status'  => SubscriptionInvoice::STATUS_PAID,
                'paid_at' => $now,
            ]);

            // Plan-change invoices behave differently: keep the existing period
            // (we already prorated for what's left), just flip plan_id and
            // refresh quota. NEVER reset started_at or extend period_end here.
            $isPlanChange = (bool) ($invoice->meta['plan_change'] ?? false);
            // Hoisted so it's available to the post-commit notifier branch
            // even when this is a plan-change path (where the else block
            // below wouldn't have set it).
            $isFirstActivation = is_null($sub->started_at);

            // Capture the OLD plan name BEFORE we flip plan_id below, so
            // the post-commit "you upgraded from X to Y" notifier wording
            // has both names. Reading $sub->plan AFTER update() returns
            // the new plan because Eloquent re-resolves the relation.
            $previousPlanName = null;
            if ($isPlanChange) {
                $previousPlanName = $sub->plan?->name;
                if (!$previousPlanName && !empty($invoice->meta['previous_plan_id'])) {
                    $previousPlanName = SubscriptionPlan::find($invoice->meta['previous_plan_id'])?->name;
                }
            }

            if ($isPlanChange) {
                $newPlanId = (int) ($invoice->meta['new_plan_id'] ?? 0);
                $updates = ['status' => PhotographerSubscription::STATUS_ACTIVE];
                if ($newPlanId && $newPlanId !== $sub->plan_id) {
                    $updates['plan_id'] = $newPlanId;
                }
                $sub->update($updates);
            } else {
                // First activation OR renewal: extend the period.

                $sub->update([
                    'status'               => PhotographerSubscription::STATUS_ACTIVE,
                    'started_at'           => $sub->started_at ?? $now,
                    'current_period_start' => $invoice->period_start ?? $now,
                    'current_period_end'   => $invoice->period_end ?? $now->copy()->addMonth(),
                    'last_renewed_at'      => $isFirstActivation ? null : $now,
                    'renewal_attempts'     => 0,
                    'next_retry_at'        => null,
                    'grace_ends_at'        => null,
                ]);
            }

            $profile = PhotographerProfile::where('user_id', $sub->photographer_id)->first();
            if ($profile) {
                $this->syncProfileCache($profile, $sub->fresh('plan'));
            }

            $fresh = $sub->fresh('plan');

            // Lifecycle notification — fire AFTER the transaction commits
            // (best-effort; never blocks activation). Distinguishes
            // first-time activation, renewal, and plan-change so the
            // photographer gets the right wording for each.
            try {
                $notifier = app(\App\Services\Notifications\PhotographerLifecycleNotifier::class);
                if ($isPlanChange) {
                    // Plan-change announces the new plan + perks
                    // explicitly. The previous plan name was captured
                    // pre-update so the message can say "Pro → Studio".
                    $notifier->subscriptionPlanChanged($fresh, $previousPlanName);
                } elseif ($isFirstActivation) {
                    $notifier->subscriptionStarted($fresh);
                } else {
                    $notifier->subscriptionRenewed($fresh);
                }
            } catch (\Throwable $e) {
                Log::debug('SubscriptionService: lifecycle notify skipped', [
                    'sub_id' => $sub->id, 'error' => $e->getMessage(),
                ]);
            }

            return $fresh;
        });
    }

    // ────────────────────────────────────────────────────────────────────
    // Renewals
    // ────────────────────────────────────────────────────────────────────

    /**
     * Attempt to renew a subscription — creates a new invoice + order.
     * The actual charge goes through the gateway; this method only sets
     * things up and returns the Order for the renewal cron to process.
     *
     * If the sub has `cancel_at_period_end`, this method does nothing
     * (the expire-grace job will downgrade it when period_end hits).
     */
    public function renew(PhotographerSubscription $sub): ?Order
    {
        if ($sub->cancel_at_period_end || !$sub->plan) {
            return null;
        }

        return DB::transaction(function () use ($sub) {
            $plan = $sub->plan;
            if ($plan->isFree()) return null;

            $now          = now();
            $periodStart  = $sub->current_period_end && $sub->current_period_end->isFuture()
                ? $sub->current_period_end
                : $now;
            $cycle        = $sub->meta['billing_cycle'] ?? 'monthly';
            $periodEnd    = $cycle === 'annual'
                ? $periodStart->copy()->addYear()
                : $periodStart->copy()->addMonth();
            $amount       = $cycle === 'annual' && $plan->price_annual_thb
                ? (float) $plan->price_annual_thb
                : (float) $plan->price_thb;

            // ─── Idempotency guard ──────────────────────────────────
            // Without this, the hourly `subscriptions:renew-due` cron
            // spawned a fresh duplicate invoice + Order every run for
            // any sub inside the 24h look-ahead window. Reuse an
            // existing pending invoice whose Order is still alive
            // (not cancelled/expired) instead of creating a duplicate.
            // The pending Order auto-cancels 30min after creation
            // (OrderExpireService); after that we DO create a new
            // invoice for the next cron tick — but at most one per
            // ~30min, not one per hour.
            $existing = SubscriptionInvoice::query()
                ->where('subscription_id', $sub->id)
                ->where('status', SubscriptionInvoice::STATUS_PENDING)
                ->whereDate('period_start', $periodStart)
                ->whereNotNull('order_id')
                ->latest('id')
                ->first();
            if ($existing) {
                $existingOrder = Order::find($existing->order_id);
                if ($existingOrder && in_array($existingOrder->status, ['pending', 'pending_payment'], true)) {
                    return $existingOrder;
                }
            }
            // ────────────────────────────────────────────────────────

            $invoice = SubscriptionInvoice::create([
                'subscription_id' => $sub->id,
                'photographer_id' => $sub->photographer_id,
                'invoice_number'  => SubscriptionInvoice::generateInvoiceNumber(),
                'period_start'    => $periodStart,
                'period_end'      => $periodEnd,
                'amount_thb'      => $amount,
                'status'          => SubscriptionInvoice::STATUS_PENDING,
                'meta'            => [
                    'plan_code'     => $plan->code,
                    'billing_cycle' => $cycle,
                    'renewal'       => true,
                ],
            ]);

            $order = Order::create([
                'user_id'                 => $sub->photographer_id,
                'order_number'            => 'SUB-'.$now->format('ymd').'-'.strtoupper(bin2hex(random_bytes(3))),
                'order_type'              => Order::TYPE_SUBSCRIPTION,
                'subscription_invoice_id' => $invoice->id,
                'total'                   => $amount,
                'status'                  => 'pending_payment',
                'note'                    => "Subscription renewal: {$plan->name}",
            ]);

            $invoice->update(['order_id' => $order->id]);

            $sub->increment('renewal_attempts');
            $sub->update(['next_retry_at' => $now->copy()->addDays(2)]);

            return $order;
        });
    }

    /**
     * Auto-charge a pending renewal Order via the photographer's saved
     * Omise card-on-file. Called by the `subscriptions:charge-pending`
     * hourly cron.
     *
     * Pre-conditions caller is responsible for:
     *   • $order->order_type === 'subscription'
     *   • $order->status === 'pending_payment'
     *   • $order->payment_expires_at is still in the future (not expired)
     *
     * Behaviour:
     *   • Resolves the Subscription via Order → SubscriptionInvoice →
     *     PhotographerSubscription. Skips silently if any link is missing.
     *   • Bails out with reason='no_customer' when the sub has no
     *     `omise_customer_id` (manual-pay user — they'll renew via the
     *     hosted checkout link instead).
     *   • Creates a PaymentTransaction for audit (status='pending').
     *   • Calls OmiseGateway::chargeCustomer.
     *   • On Omise charge.successful: completes the transaction +
     *     dispatches the paid-order side effects (= calls
     *     activateFromPaidInvoice via OrderFulfillmentService, same path
     *     the webhook uses). Order moves to 'paid'.
     *   • On Omise failure or unavailable: fails the transaction +
     *     calls markRenewalFailed() with the Omise reason.
     *   • On Omise pending (3DS challenge): we do nothing extra — the
     *     webhook will catch the eventual outcome. PaymentTransaction
     *     stays 'pending'.
     *
     * Returns ['ok' => bool, 'reason' => string, 'charge_id' => ?string].
     * Idempotent at the cron level: if called twice on the same Order
     * we return early on the second pass because the first pass moved
     * the order to 'paid' (or failed it).
     */
    public function chargeRenewal(Order $order): array
    {
        // Refresh status to dodge race with a concurrent webhook that
        // might have just paid this order.
        $order->refresh();
        if ($order->status !== 'pending_payment') {
            return ['ok' => false, 'reason' => 'order_not_pending'];
        }

        $invoice = SubscriptionInvoice::find($order->subscription_invoice_id);
        if (!$invoice) {
            return ['ok' => false, 'reason' => 'invoice_missing'];
        }

        $sub = PhotographerSubscription::with('plan')->find($invoice->subscription_id);
        if (!$sub) {
            return ['ok' => false, 'reason' => 'subscription_missing'];
        }

        if (empty($sub->omise_customer_id)) {
            return ['ok' => false, 'reason' => 'no_customer'];
        }

        $gateway = app(\App\Services\Payment\OmiseGateway::class);
        if (!$gateway->isAvailable()) {
            return ['ok' => false, 'reason' => 'omise_disabled'];
        }

        // Track the attempt as a PaymentTransaction so admin reporting
        // shows every charge we tried (matching the manual-pay flow).
        $transaction = \App\Models\PaymentTransaction::create([
            'transaction_id'    => 'TXN-' . strtoupper(\Illuminate\Support\Str::random(16)),
            'order_id'          => $order->id,
            'user_id'           => $order->user_id,
            'payment_method_id' => null,
            'payment_gateway'   => 'omise',
            'amount'            => $order->total,
            'currency'          => 'THB',
            'status'            => 'pending',
            'metadata'          => [
                'auto_charge'     => true,
                'customer_id'     => $sub->omise_customer_id,
                'subscription_id' => $sub->id,
            ],
        ]);

        $charge = $gateway->chargeCustomer(
            $sub->omise_customer_id,
            (float) $order->total,
            "Subscription renewal: " . ($sub->plan->name ?? 'Plan') . " — Order #{$order->order_number}",
            [
                'transaction_id' => $transaction->transaction_id,
                'order_id'       => $order->id,
                'subscription_id'=> $sub->id,
                'auto_charge'    => 'true',
            ]
        );

        $object = $charge['object'] ?? '';
        $status = $charge['status'] ?? '';

        // ── Successful charge ──────────────────────────────────────
        if ($object === 'charge' && $status === 'successful') {
            \App\Services\Payment\PaymentService::completeTransaction($transaction, $charge['id'] ?? null);

            // Reuse the existing post-pay routing: this is what the
            // webhook would do, just inlined since we know the charge
            // is already settled. activateFromPaidInvoice + storage
            // sync run inside fulfill().
            try {
                $orderRefreshed = Order::with(['user', 'items', 'event'])->find($order->id);
                if ($orderRefreshed) {
                    app(\App\Services\OrderFulfillmentService::class)->fulfill($orderRefreshed);
                }
            } catch (\Throwable $e) {
                Log::error('chargeRenewal: fulfill() failed', [
                    'order_id' => $order->id, 'error' => $e->getMessage(),
                ]);
                // The charge is already paid — don't bubble the error,
                // the next webhook arrival or admin reconcile will
                // re-trigger fulfilment.
            }

            return ['ok' => true, 'reason' => 'charged', 'charge_id' => $charge['id'] ?? null];
        }

        // ── 3DS / pending — let webhook resolve later ──────────────
        if ($object === 'charge' && $status === 'pending') {
            return ['ok' => false, 'reason' => 'charge_pending', 'charge_id' => $charge['id'] ?? null];
        }

        // ── Failed / declined / Omise unavailable ──────────────────
        $failureReason = $charge['failure_message'] ?? $charge['message'] ?? $charge['failure_code'] ?? $status ?: 'unknown';
        \App\Services\Payment\PaymentService::failTransaction($transaction, "omise_auto_charge: {$failureReason}");

        $this->markRenewalFailed($sub, "auto_charge: {$failureReason}");

        return ['ok' => false, 'reason' => 'charge_failed', 'charge_id' => $charge['id'] ?? null, 'message' => $failureReason];
    }

    /**
     * Mark a failed renewal attempt. If we've exceeded max attempts, drop
     * into grace. If grace has also expired elsewhere, caller should
     * trigger expireGrace() separately.
     */
    public function markRenewalFailed(PhotographerSubscription $sub, string $reason = ''): void
    {
        $max = $this->maxRenewalAttempts();
        $now = now();
        $enteredGrace = false;

        DB::transaction(function () use ($sub, $max, $reason, $now, &$enteredGrace) {
            $attempts = (int) $sub->renewal_attempts;
            $update = ['renewal_attempts' => $attempts];

            if ($attempts >= $max && $sub->status !== PhotographerSubscription::STATUS_GRACE) {
                $update['status']         = PhotographerSubscription::STATUS_GRACE;
                $update['grace_ends_at']  = $now->copy()->addDays($this->graceDays());
                $update['next_retry_at']  = null;
                $enteredGrace = true;
            } else {
                $update['next_retry_at']  = $now->copy()->addDays(2);
            }

            $sub->update($update);

            Log::info('Subscription renewal failed', [
                'subscription_id' => $sub->id,
                'attempts'        => $attempts,
                'status_after'    => $update['status'] ?? $sub->status,
                'reason'          => $reason,
            ]);
        });

        // Lifecycle notification — fires on EVERY failed attempt so the
        // photographer learns about each declined charge. The notifier
        // uses notifyOnce(refId based on attempt count) so the in-app
        // bell shows a fresh row per attempt without spamming the email.
        // Critical priority + WARN/CRITICAL severity → email is sent.
        try {
            app(\App\Services\Notifications\PhotographerLifecycleNotifier::class)
                ->subscriptionRenewalFailed($sub->fresh('plan'), $reason);
        } catch (\Throwable $e) {
            Log::debug('SubscriptionService: renewal-fail notify skipped', [
                'sub_id' => $sub->id, 'error' => $e->getMessage(),
            ]);
        }
    }

    // ────────────────────────────────────────────────────────────────────
    // Cancel / change plan
    // ────────────────────────────────────────────────────────────────────

    /**
     * Cancel a subscription. By default the subscription stays active until
     * the current period ends (Netflix-style). Pass $immediate=true to
     * rollback quota right now (admin hard-cancel).
     */
    public function cancel(PhotographerSubscription $sub, bool $immediate = false): PhotographerSubscription
    {
        return DB::transaction(function () use ($sub, $immediate) {
            $sub->refresh();
            $now = now();

            if ($immediate) {
                $sub->update([
                    'status'               => PhotographerSubscription::STATUS_CANCELLED,
                    'cancelled_at'         => $now,
                    'cancel_at_period_end' => true,
                    'current_period_end'   => $now,
                ]);

                $profile = PhotographerProfile::where('user_id', $sub->photographer_id)->first();
                if ($profile) {
                    $free = SubscriptionPlan::defaultFree();
                    if ($free) {
                        $this->syncProfileCache($profile, $this->ensureFreeSubscription($profile));
                    }
                }
            } else {
                $sub->update([
                    'cancel_at_period_end' => true,
                    'cancelled_at'         => $now,
                ]);
            }

            return $sub->fresh('plan');
        });
    }

    /**
     * Resume a cancelled subscription (if still within the current period).
     */
    public function resume(PhotographerSubscription $sub): PhotographerSubscription
    {
        if ($sub->status !== PhotographerSubscription::STATUS_ACTIVE) {
            return $sub;
        }
        $sub->update([
            'cancel_at_period_end' => false,
            'cancelled_at'         => null,
        ]);
        return $sub->fresh('plan');
    }

    /**
     * Change plan.
     *
     * Returns an array describing the outcome so the controller can decide
     * what to do next (redirect to payment vs. flash a message):
     *
     *   ['type' => 'noop',         'order' => null]  // already on this plan
     *   ['type' => 'deferred',     'order' => null]  // downgrade scheduled for period end
     *   ['type' => 'order',        'order' => Order] // upgrade pending — go pay the prorated charge
     *
     * Strategy:
     *   • Upgrade (new plan more expensive): create a SubscriptionInvoice +
     *     Order for the PRO-RATED DIFFERENCE only. Plan does NOT switch yet —
     *     activateFromPaidInvoice() flips plan_id when the order is paid.
     *     This keeps "free upgrades" impossible: no payment, no plan change.
     *   • Downgrade (new plan cheaper): no immediate charge. We stash the new
     *     plan code in sub.meta.pending_plan_code; the renewal job will pick
     *     it up at period end so the photographer keeps what they paid for.
     *   • Cycle (monthly/annual) is preserved from the current sub — to switch
     *     cycles users go through the full subscribe flow instead.
     */
    public function changePlan(
        PhotographerSubscription $sub,
        SubscriptionPlan $newPlan,
        bool $prorateImmediately = true
    ): array {
        $result = DB::transaction(function () use ($sub, $newPlan, $prorateImmediately) {
            $sub->refresh();
            if ($sub->plan_id === $newPlan->id) {
                return ['type' => 'noop', 'order' => null];
            }

            $currentPlan = $sub->plan;
            $isUpgrade   = (float) $newPlan->price_thb > (float) ($currentPlan->price_thb ?? 0);

            // Downgrade or user-deferred upgrade: schedule for period end.
            // No charge today; renewal logic reads pending_plan_code on rollover.
            if (!$isUpgrade || !$prorateImmediately) {
                $meta = $sub->meta ?? [];
                $meta['pending_plan_code'] = $newPlan->code;
                $sub->update(['meta' => $meta]);
                return ['type' => 'deferred', 'order' => null];
            }

            // ─── Upgrade path: prorated charge for the remainder of period ───
            $cycle = $sub->meta['billing_cycle'] ?? 'monthly';
            $now   = now();

            $periodStart = $sub->current_period_start ?? $sub->started_at ?? $now;
            $periodEnd   = $sub->current_period_end
                ?? $now->copy()->{$cycle === 'annual' ? 'addYear' : 'addMonth'}();

            // diffInDays returns 0 for same-day; floor at 1 to avoid div-by-zero.
            $daysInPeriod  = max(1, (int) Carbon::parse($periodStart)->diffInDays(Carbon::parse($periodEnd)));
            $daysRemaining = max(0, (int) $now->diffInDays(Carbon::parse($periodEnd), false));

            // Use the SAME billing cycle the sub is on — no cycle switching here.
            $oldPrice = $cycle === 'annual' && $currentPlan?->price_annual_thb
                ? (float) $currentPlan->price_annual_thb
                : (float) ($currentPlan?->price_thb ?? 0);
            $newPrice = $cycle === 'annual' && $newPlan->price_annual_thb
                ? (float) $newPlan->price_annual_thb
                : (float) $newPlan->price_thb;

            $proratedAmount = round(($newPrice - $oldPrice) * ($daysRemaining / $daysInPeriod), 2);
            // Omise minimum is 20 THB. If the prorated diff is microscopic
            // (e.g. < 1 day left), treat it as a free deferred upgrade so we
            // don't ship an unpayable order to the gateway.
            if ($proratedAmount < 20) {
                $meta = $sub->meta ?? [];
                $meta['pending_plan_code'] = $newPlan->code;
                $sub->update(['meta' => $meta]);
                return ['type' => 'deferred', 'order' => null];
            }

            // Void any prior unpaid plan-change invoice on this sub so a stale
            // half-paid Order doesn't sit around. Idempotent — safe to re-run.
            SubscriptionInvoice::where('subscription_id', $sub->id)
                ->where('status', SubscriptionInvoice::STATUS_PENDING)
                ->whereNotNull('order_id')
                ->get()
                ->each(function (SubscriptionInvoice $inv) {
                    $isPlanChange = (bool) ($inv->meta['plan_change'] ?? false);
                    if (!$isPlanChange) return;
                    if ($inv->order && $inv->order->status === 'pending_payment') {
                        $inv->order->update(['status' => 'cancelled']);
                    }
                    $inv->update(['status' => SubscriptionInvoice::STATUS_VOIDED]);
                });

            $invoice = SubscriptionInvoice::create([
                'subscription_id' => $sub->id,
                'photographer_id' => $sub->photographer_id,
                'invoice_number'  => SubscriptionInvoice::generateInvoiceNumber(),
                'period_start'    => $now,
                'period_end'      => $periodEnd,
                'amount_thb'      => $proratedAmount,
                'status'          => SubscriptionInvoice::STATUS_PENDING,
                'meta'            => [
                    'plan_code'        => $newPlan->code,
                    'billing_cycle'    => $cycle,
                    'plan_change'      => true,
                    'previous_plan_id' => $currentPlan?->id,
                    'new_plan_id'      => $newPlan->id,
                    'days_remaining'   => $daysRemaining,
                    'days_in_period'   => $daysInPeriod,
                ],
            ]);

            $order = Order::create([
                'user_id'                 => $sub->photographer_id,
                'order_number'            => 'CHG-'.$now->format('ymd').'-'.strtoupper(bin2hex(random_bytes(3))),
                'order_type'              => Order::TYPE_SUBSCRIPTION,
                'subscription_invoice_id' => $invoice->id,
                'total'                   => $proratedAmount,
                'status'                  => 'pending_payment',
                'note'                    => "เปลี่ยนแผน: {$currentPlan?->name} → {$newPlan->name} (ส่วนต่าง {$daysRemaining}/{$daysInPeriod} วัน)",
            ]);

            $invoice->update(['order_id' => $order->id]);

            return ['type' => 'order', 'order' => $order];
        });

        // ── Post-commit lifecycle notification ──
        // Two paths: 'deferred' (downgrade or sub-20฿ proration) →
        // photographer gets a "downgrade scheduled, you keep current plan
        // till period_end" reassurance. 'order' (paid upgrade) → notification
        // fires later from activateFromPaidInvoice via the webhook (so
        // the message only goes out AFTER the user actually pays). 'noop'
        // is silent (same plan as before).
        if (($result['type'] ?? '') === 'deferred') {
            try {
                $notifier = app(\App\Services\Notifications\PhotographerLifecycleNotifier::class);
                $notifier->subscriptionPlanDowngradeScheduled($sub->fresh('plan'), $newPlan);
            } catch (\Throwable $e) {
                Log::debug('SubscriptionService: downgrade-scheduled notify skipped', [
                    'sub_id' => $sub->id, 'error' => $e->getMessage(),
                ]);
            }
        }

        return $result;
    }

    /**
     * Expire an active subscription whose `current_period_end` has passed.
     *
     * This is the canonical "plan period ran out" path used by the
     * `subscriptions:expire-overdue` cron. Until auto-charge of the saved
     * payment method is implemented (Phase B), this is what enforces the
     * monthly/annual plan period — without this method a one-time payment
     * granted permanent paid-tier access because nothing else flipped
     * `status='active'` away from active after period_end.
     *
     * Behaviour:
     *   • Only acts on subs whose status is `active` AND whose
     *     `current_period_end` is in the past — no-op otherwise (so it's
     *     safe to call repeatedly from the hourly cron).
     *   • Status flips to `expired`, `cancelled_at` is stamped.
     *   • The photographer profile is reverted to the seeded free plan
     *     via `ensureFreeSubscription()` + `syncProfileCache()`, so
     *     `storage_quota_bytes`, `commission_rate`,
     *     `subscription_plan_code` etc. reflect free-tier limits within
     *     the same transaction (the storage middleware reads these).
     *   • Lifecycle notification (`subscriptionExpired`) fires AFTER
     *     commit so the photographer learns "your plan ended, please
     *     renew" — same notifier expireGrace() uses, so the email/LINE
     *     copy is consistent.
     *
     * Subs with `status='grace'` are deliberately ignored here — they
     * already took this transition and are waiting for `expire-grace`
     * to do the final downgrade.
     */
    public function expireOverdue(PhotographerSubscription $sub): void
    {
        if ($sub->status !== PhotographerSubscription::STATUS_ACTIVE) {
            return;
        }
        if (!$sub->current_period_end || $sub->current_period_end->isFuture()) {
            return;
        }

        $previousPlanName = null;

        DB::transaction(function () use ($sub, &$previousPlanName) {
            // Re-fetch with lock so a concurrent webhook (paid renewal)
            // can't slip through between our check and the update.
            $fresh = PhotographerSubscription::where('id', $sub->id)
                ->lockForUpdate()
                ->first();
            if (!$fresh || $fresh->status !== PhotographerSubscription::STATUS_ACTIVE) {
                return;
            }
            if (!$fresh->current_period_end || $fresh->current_period_end->isFuture()) {
                return;
            }

            $previousPlanName = $fresh->plan?->name ?? $sub->plan?->name;

            $fresh->update([
                'status'       => PhotographerSubscription::STATUS_EXPIRED,
                'cancelled_at' => now(),
            ]);

            $profile = PhotographerProfile::where('user_id', $fresh->photographer_id)->first();
            if ($profile) {
                $freeSub = $this->ensureFreeSubscription($profile);
                $this->syncProfileCache($profile, $freeSub);
            }
        });

        if ($previousPlanName) {
            try {
                app(\App\Services\Notifications\PhotographerLifecycleNotifier::class)
                    ->subscriptionExpired($sub->fresh('plan'), $previousPlanName);
            } catch (\Throwable $e) {
                Log::debug('SubscriptionService: expire-overdue notify skipped', [
                    'sub_id' => $sub->id, 'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * After a grace window expires, downgrade to free plan.
     * Called by scheduled job.
     */
    public function expireGrace(PhotographerSubscription $sub): void
    {
        $previousPlanName = null;

        DB::transaction(function () use ($sub, &$previousPlanName) {
            $sub->refresh();
            if (!$sub->isGrace()) return;

            // Capture plan name BEFORE downgrading — the notifier needs
            // it for the message ("Pro plan ended") and the relation
            // points to the free plan after the update.
            $previousPlanName = $sub->plan?->name;

            $sub->update([
                'status'       => PhotographerSubscription::STATUS_EXPIRED,
                'cancelled_at' => now(),
            ]);

            $profile = PhotographerProfile::where('user_id', $sub->photographer_id)->first();
            if ($profile) {
                $freeSub = $this->ensureFreeSubscription($profile);
                $this->syncProfileCache($profile, $freeSub);
            }
        });

        // Lifecycle notification — fires AFTER commit so the
        // photographer learns the downgrade is final. Critical severity
        // (email + LINE + in-app). Includes "ยังสมัครใหม่ได้" CTA.
        if ($previousPlanName) {
            try {
                app(\App\Services\Notifications\PhotographerLifecycleNotifier::class)
                    ->subscriptionExpired($sub->fresh('plan'), $previousPlanName);
            } catch (\Throwable $e) {
                Log::debug('SubscriptionService: expired notify skipped', [
                    'sub_id' => $sub->id, 'error' => $e->getMessage(),
                ]);
            }
        }
    }

    // ────────────────────────────────────────────────────────────────────
    // Profile cache sync
    // ────────────────────────────────────────────────────────────────────

    /**
     * Re-sync the denormalised quota columns from the photographer's
     * CURRENT plan, even when there's no active subscription row. Used
     * for admin "Fix drift" buttons, cron healers, and the migration
     * that backfills the column on existing profiles.
     *
     * Different from syncProfileCache() in that it doesn't need a
     * Subscription model — it resolves the plan from the profile's
     * cached subscription_plan_code (or falls back to the default-free
     * plan when that's empty).
     *
     * Returns true when a write happened (column changed), false if
     * already in sync.
     */
    public function resyncStorageQuotaFromPlan(PhotographerProfile $profile): bool
    {
        $plan = $this->currentPlan($profile);
        $newBytes = (int) ($plan->storage_bytes ?? 0);
        if ($newBytes <= 0) return false;

        $current = (int) ($profile->storage_quota_bytes ?? 0);
        if ($current === $newBytes) return false;

        $profile->forceFill([
            'storage_quota_bytes'    => $newBytes,
            'subscription_plan_code' => $plan->code,
            'commission_rate'        => max(0, 100 - (float) ($plan->commission_pct ?? 0)),
        ])->saveQuietly();
        return true;
    }

    /**
     * Refresh the denormalised columns on photographer_profiles so the
     * middleware / dashboard / quota queries don't have to JOIN.
     *
     * Writes:
     *   - current_subscription_id
     *   - subscription_plan_code
     *   - subscription_status
     *   - subscription_renews_at
     *   - storage_quota_bytes (the real thing middleware reads!)
     *   - commission_rate (photographer keep-share = 100 - plan.commission_pct)
     *   - ai_credits_used / ai_credits_period_start / ai_credits_period_end
     *     (reset to 0 whenever the billing period rolls over so
     *     monthly_ai_credits actually means "per period")
     */
    public function syncProfileCache(PhotographerProfile $profile, PhotographerSubscription $sub): void
    {
        $plan = $sub->plan ?? SubscriptionPlan::defaultFree();
        if (!$plan) return;

        // Plan-derived commission %. Default profile fallback is 80% keep
        // (= 20% platform), which happens to match the free plan, but for
        // paid plans we now correctly write 100% keep (= 0% platform).
        $photographerKeepPct = max(0, 100 - (float) ($plan->commission_pct ?? 0));

        $patch = [
            'current_subscription_id' => $sub->id,
            'subscription_plan_code'  => $plan->code,
            'subscription_status'     => $sub->status,
            'subscription_renews_at'  => $sub->current_period_end,
            'storage_quota_bytes'     => $plan->storage_bytes,
            'commission_rate'         => $photographerKeepPct,
        ];

        // Reset AI credit counter whenever the billing period boundary
        // shifts forward (new sub, renewal, plan-change). We compare on
        // current_period_start so plan-changes that keep the period
        // unchanged DON'T wipe the running counter.
        $newStart = $sub->current_period_start;
        $cachedStart = $profile->ai_credits_period_start;
        $periodRolled = $newStart && (
            !$cachedStart
            || $newStart->gt($cachedStart)
        );

        if ($periodRolled) {
            $patch['ai_credits_used']         = 0;
            $patch['ai_credits_period_start'] = $newStart;
            $patch['ai_credits_period_end']   = $sub->current_period_end;
        }

        $profile->forceFill($patch)->save();
    }

    /**
     * Ensure the photographer has a free subscription row to fall back to.
     * Used after grace expiry / hard-cancel. Idempotent — reuses an
     * existing free row if one happens to be active.
     */
    public function ensureFreeSubscription(PhotographerProfile $profile): PhotographerSubscription
    {
        $free = SubscriptionPlan::defaultFree();
        if (!$free) {
            throw new \RuntimeException('No default free plan configured; seed the plans table.');
        }

        $existing = PhotographerSubscription::where('photographer_id', $profile->user_id)
            ->where('plan_id', $free->id)
            ->where('status', PhotographerSubscription::STATUS_ACTIVE)
            ->first();
        if ($existing) return $existing;

        return $this->subscribe($profile, $free);
    }

    // ────────────────────────────────────────────────────────────────────
    // Dashboard helpers
    // ────────────────────────────────────────────────────────────────────

    public function dashboardSummary(PhotographerProfile $profile): array
    {
        $plan = $this->currentPlan($profile);
        $sub  = $this->currentSubscription($profile);

        // ── Self-heal: storage_quota drift ────────────────────────────────
        // profile.storage_quota_bytes (read by EnforceStorageQuota
        // middleware) sometimes drifts from plan.storage_bytes when a
        // photographer's plan changes through a path that bypasses
        // syncProfileCache (admin manual edit, legacy migration, etc.).
        // Heal it lazily on dashboard read so the display the photographer
        // sees and the value the upload gate enforces always agree.
        $planBytes    = (int) ($plan->storage_bytes ?? 0);
        $profileQuota = (int) ($profile->storage_quota_bytes ?? 0);
        if ($planBytes > 0 && $profileQuota !== $planBytes) {
            try {
                $profile->forceFill(['storage_quota_bytes' => $planBytes])->saveQuietly();
                \Illuminate\Support\Facades\Log::info('subscription.storage_quota_resynced', [
                    'user_id'   => $profile->user_id,
                    'old_bytes' => $profileQuota,
                    'new_bytes' => $planBytes,
                    'plan_code' => $plan->code,
                ]);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning(
                    'storage_quota_resync_failed: ' . $e->getMessage()
                );
            }
        }

        // ── Self-heal: storage_used drift ─────────────────────────────────
        // storage_used_bytes is maintained by delta updates on photo
        // upload/delete (see StorageQuotaService::adjust). If a photo row
        // gets removed by some path that skips the helper (raw DELETE,
        // event purge), the counter drifts. Reconcile from event_photos
        // when storage_recalculated_at is stale (>24h) OR when the value
        // looks suspiciously zero on a profile that has photos. Heavy
        // op (one COUNT/SUM join) so we cap by recompute interval.
        $needsRecalc = false;
        if (!$profile->storage_recalculated_at) {
            $needsRecalc = true;
        } elseif ($profile->storage_recalculated_at->diffInHours(now()) >= 24) {
            $needsRecalc = true;
        }
        if ($needsRecalc) {
            try {
                app(\App\Services\StorageQuotaService::class)->recalculate($profile);
                $profile->refresh();
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning(
                    'storage_used_recalc_failed: ' . $e->getMessage()
                );
            }
        }

        $used   = (int) ($profile->storage_used_bytes ?? 0);
        $quota  = (int) ($plan->storage_bytes ?? 0);
        $pct    = $quota > 0 ? min(100, round(($used / $quota) * 100, 1)) : 0;

        // Concurrent-event usage vs. cap.
        $eventsUsed = $this->activeEventCount($profile->user_id);
        $eventsCap  = $this->maxConcurrentEvents($profile);
        $eventsPct  = ($eventsCap !== null && $eventsCap > 0)
            ? min(100, round(($eventsUsed / $eventsCap) * 100, 1))
            : 0;

        // AI credit usage vs. cap.
        $aiUsed = $this->aiCreditsUsed($profile);
        $aiCap  = $this->monthlyAiCredits($profile);
        $aiPct  = $aiCap > 0 ? min(100, round(($aiUsed / $aiCap) * 100, 1)) : 0;

        return [
            'enabled'               => $this->systemEnabled(),
            'plan'                  => $plan,
            'subscription'          => $sub,

            // Storage
            'storage_used_bytes'    => $used,
            'storage_quota_bytes'   => $quota,
            'storage_used_gb'       => round($used / (1024 ** 3), 2),
            'storage_quota_gb'      => round($quota / (1024 ** 3), 2),
            'storage_used_pct'      => $pct,
            'storage_warn'          => $pct >= 85,
            'storage_critical'      => $pct >= 95,

            // Concurrent events (Free=0, Starter=2, Pro=5, Business/Studio=∞)
            'events_used'           => $eventsUsed,
            'events_cap'            => $eventsCap, // null = unlimited
            'events_unlimited'      => $eventsCap === null,
            'events_used_pct'       => $eventsPct,

            // Monthly AI credits
            'ai_credits_used'       => $aiUsed,
            'ai_credits_cap'        => $aiCap,
            'ai_credits_remaining'  => max(0, $aiCap - $aiUsed),
            'ai_credits_used_pct'   => $aiPct,
            'ai_credits_period_end' => $profile->ai_credits_period_end,

            // Commission %
            'commission_pct'        => $this->commissionPct($profile),
            'photographer_share_pct' => $this->photographerSharePct($profile),

            // Plan/period state
            'ai_features'           => $plan->ai_features ?? [],
            'is_free'               => $plan->isFree(),
            'has_active_paid'       => $sub && $sub->isUsable() && !$plan->isFree(),
            'cancel_at_period_end'  => (bool) ($sub?->cancel_at_period_end ?? false),
            'in_grace'              => $sub?->isGrace() ?? false,
            'grace_ends_at'         => $sub?->grace_ends_at,
            'current_period_end'    => $sub?->current_period_end,
            'days_until_renewal'    => $sub?->daysUntilRenewal(),
        ];
    }

    /* ═══════════════════════════════════════════════════════════════
     * Admin overrides — invoked from /admin/photographers/{p}/edit
     *
     * These bypass the buyer-facing payment flow so admins can comp
     * plans, extend periods (refund alternative), or hard-cancel
     * without invoicing. Every call writes a marker into meta + an
     * audit log so we can trace WHO comped WHAT and WHY.
     * ═══════════════════════════════════════════════════════════════ */

    /**
     * Grant a plan to a photographer immediately without charging —
     * use case: comped accounts (partners, beta testers, dispute
     * resolution), bulk migrations, customer-success make-good.
     *
     * Cancels any currently active/pending sub atomically + creates
     * a fresh active subscription that runs for $days (or one full
     * billing cycle if $days is null). No invoice / no payment_intent
     * is created — this isn't a sale, it's a grant.
     */
    public function adminAssign(
        PhotographerProfile $profile,
        SubscriptionPlan $plan,
        ?int $days = null,
        string $reason = '',
        ?int $adminId = null
    ): PhotographerSubscription {
        return DB::transaction(function () use ($profile, $plan, $days, $reason, $adminId) {
            // Tombstone the prior live sub so the photographer never has two.
            // Cancel reason captured in meta (no dedicated column on schema).
            PhotographerSubscription::where('photographer_id', $profile->user_id)
                ->whereIn('status', [
                    PhotographerSubscription::STATUS_ACTIVE,
                    PhotographerSubscription::STATUS_PENDING,
                    PhotographerSubscription::STATUS_GRACE,
                ])
                ->lockForUpdate()
                ->update([
                    'status'       => PhotographerSubscription::STATUS_CANCELLED,
                    'cancelled_at' => now(),
                    'updated_at'   => now(),
                ]);

            $now      = now();
            $endsAt   = $days !== null && $days > 0
                ? $now->copy()->addDays($days)
                : ($plan->price_annual_thb && $plan->price_annual_thb > 0
                    ? $now->copy()->addYear()
                    : $now->copy()->addMonth());

            $sub = PhotographerSubscription::create([
                'photographer_id'      => $profile->user_id,
                'plan_id'              => $plan->id,
                'status'               => PhotographerSubscription::STATUS_ACTIVE,
                'started_at'           => $now,
                'current_period_start' => $now,
                'current_period_end'   => $endsAt,
                'payment_method_type'  => 'admin_comp',
                'meta'                 => [
                    'admin_assigned' => true,
                    'admin_id'       => $adminId,
                    'reason'         => mb_substr($reason ?: 'admin grant', 0, 500),
                    'days'           => $days,
                    'assigned_at'    => $now->toIso8601String(),
                ],
            ]);

            $this->syncProfileCache($profile, $sub);
            return $sub->fresh('plan');
        });
    }

    /**
     * Hard-cancel the photographer's current subscription immediately
     * (vs. the buyer-facing cancel() which schedules cancel-at-period-end).
     *
     * Drops them to the free plan right now. Used when an admin needs
     * to revoke access mid-period (TOS violation, refund issued, etc).
     */
    public function adminCancel(
        PhotographerProfile $profile,
        string $reason = '',
        ?int $adminId = null
    ): bool {
        $sub = $this->currentSubscription($profile);
        if (!$sub || !$sub->isUsable()) return false;

        DB::transaction(function () use ($sub, $reason, $adminId) {
            $sub->update([
                'status'                  => PhotographerSubscription::STATUS_CANCELLED,
                'cancelled_at'            => now(),
                'cancel_at_period_end'    => false,
                // Cancel reason stored in meta — no dedicated column.
                'meta'                    => array_merge((array) $sub->meta, [
                    'admin_cancelled_by' => $adminId,
                    'admin_cancel_reason'=> mb_substr($reason ?: 'admin cancel', 0, 500),
                    'admin_cancelled_at' => now()->toIso8601String(),
                ]),
            ]);
        });

        // Drop them to free so feature gates re-evaluate against the
        // free plan immediately (don't leave them in limbo).
        $this->ensureFreeSubscription($profile);
        return true;
    }

    /**
     * Extend the current period_end by N days. Useful as a refund
     * alternative — instead of issuing money back, give them more time.
     *
     * No-ops if there's no active sub.
     */
    public function adminExtend(
        PhotographerProfile $profile,
        int $days,
        string $reason = '',
        ?int $adminId = null
    ): bool {
        if ($days <= 0) return false;

        $sub = $this->currentSubscription($profile);
        if (!$sub || !$sub->isUsable() || !$sub->current_period_end) return false;

        DB::transaction(function () use ($sub, $days, $reason, $adminId) {
            $extensionLog = (array) ($sub->meta['extensions'] ?? []);
            $extensionLog[] = [
                'days'      => $days,
                'reason'    => mb_substr($reason ?: 'admin extend', 0, 500),
                'admin_id'  => $adminId,
                'old_end'   => $sub->current_period_end?->toIso8601String(),
                'new_end'   => $sub->current_period_end->copy()->addDays($days)->toIso8601String(),
                'at'        => now()->toIso8601String(),
            ];

            $sub->update([
                'current_period_end' => $sub->current_period_end->copy()->addDays($days),
                'meta'               => array_merge((array) $sub->meta, [
                    'extensions' => $extensionLog,
                ]),
            ]);
        });
        return true;
    }

    public function platformKpis(): array
    {
        $activeSubs     = PhotographerSubscription::activeOrGrace()->count();
        $mrr            = (float) PhotographerSubscription::activeOrGrace()
            ->join('subscription_plans', 'subscription_plans.id', '=', 'photographer_subscriptions.plan_id')
            ->where('subscription_plans.billing_cycle', 'monthly')
            ->sum('subscription_plans.price_thb');
        $annualRevenue  = (float) PhotographerSubscription::activeOrGrace()
            ->join('subscription_plans', 'subscription_plans.id', '=', 'photographer_subscriptions.plan_id')
            ->where('subscription_plans.billing_cycle', 'annual')
            ->sum('subscription_plans.price_annual_thb');
        $last30Paid     = (float) SubscriptionInvoice::where('status', SubscriptionInvoice::STATUS_PAID)
            ->where('paid_at', '>=', now()->subDays(30))
            ->sum('amount_thb');
        $inGrace        = PhotographerSubscription::where('status', PhotographerSubscription::STATUS_GRACE)->count();
        $byPlan         = PhotographerSubscription::activeOrGrace()
            ->selectRaw('plan_id, COUNT(*) as cnt')
            ->groupBy('plan_id')
            ->pluck('cnt', 'plan_id')
            ->all();

        return [
            'active_subscribers'   => $activeSubs,
            'mrr'                  => $mrr,
            'annual_prepaid'       => $annualRevenue,
            'last30_paid'          => $last30Paid,
            'in_grace'             => $inGrace,
            'by_plan'              => $byPlan,
        ];
    }
}

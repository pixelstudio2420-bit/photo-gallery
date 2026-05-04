<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\Order;
use App\Models\StoragePlan;
use App\Models\User;
use App\Models\UserFile;
use App\Models\UserStorageInvoice;
use App\Models\UserStorageSubscription;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * UserStorageService
 * ──────────────────
 * Owns the lifecycle of CONSUMER cloud-storage subscriptions — the
 * "buy N GB of cloud space" business aimed at general users (not
 * photographers).
 *
 * Companion to SubscriptionService (photographer-side), but with a
 * separate data model:
 *   - storage_plans              (plan catalog)
 *   - user_storage_subscriptions (active/historical subs per user)
 *   - user_storage_invoices      (billing ledger)
 *   - auth_users.storage_*       (denormalised cache cols)
 *
 * Key operations:
 *   systemEnabled()         → master toggle (admin can flip off)
 *   subscribe()             → new sub + first invoice + order
 *   activateFromPaidInvoice → called by webhook when order paid
 *   renew()                 → create next-period invoice + order
 *   cancel()                → soft-cancel at period end (or immediate)
 *   changePlan()            → upgrade now / downgrade at period end
 *   expireGrace()           → downgrade to free after grace window
 *   syncUserCache()         → refresh auth_users.storage_* columns
 *   canUpload()             → quota / file-size / plan-feature check
 *
 * Design principles (match SubscriptionService):
 *   • Every mutation wrapped in DB::transaction so partial writes can't
 *     leave a user with a plan but no quota (or vice versa).
 *   • auth_users.storage_quota_bytes is the hot-path quota source; the
 *     service keeps it in sync.
 *   • Payment delegated to shared orders/transactions — one order per
 *     invoice, webhook fires back via activateFromPaidInvoice().
 *   • Free plan subscribes synchronously (no charge needed).
 */
class UserStorageService
{
    public const DEFAULT_GRACE_DAYS           = 7;
    public const DEFAULT_MAX_RENEWAL_ATTEMPTS = 3;

    // ────────────────────────────────────────────────────────────────────
    // System-wide toggles
    // ────────────────────────────────────────────────────────────────────

    /**
     * Master switch. When off, all consumer-storage routes return 404
     * via CheckStorageSystemEnabled middleware and the marketing pages
     * are hidden from the nav.
     */
    public function systemEnabled(): bool
    {
        return ((string) AppSetting::get('user_storage_enabled', '0')) === '1';
    }

    /**
     * Sales-mode toggle (distinct from system enabled — this one gates
     * the "accept new paid signups?" flow). The nav / pricing page
     * respect this; if off we still let existing subscribers use/manage
     * their storage, but the pricing page shows a "coming soon" notice.
     */
    public function salesModeEnabled(): bool
    {
        return ((string) AppSetting::get('sales_mode_storage_enabled', '0')) === '1';
    }

    public function graceDays(): int
    {
        return (int) AppSetting::get('user_storage_grace_period_days', self::DEFAULT_GRACE_DAYS);
    }

    public function maxRenewalAttempts(): int
    {
        return (int) AppSetting::get('user_storage_max_renewal_attempts', self::DEFAULT_MAX_RENEWAL_ATTEMPTS);
    }

    public function renewalReminderDays(): int
    {
        return (int) AppSetting::get('user_storage_renewal_reminder_days', 3);
    }

    // ────────────────────────────────────────────────────────────────────
    // Query helpers
    // ────────────────────────────────────────────────────────────────────

    public function currentSubscription(User $user): ?UserStorageSubscription
    {
        if ($user->current_storage_sub_id) {
            $sub = UserStorageSubscription::with('plan')->find($user->current_storage_sub_id);
            if ($sub) return $sub;
        }

        return UserStorageSubscription::with('plan')
            ->where('user_id', $user->id)
            ->activeOrGrace()
            ->latest('id')
            ->first();
    }

    public function currentPlan(User $user): StoragePlan
    {
        $sub = $this->currentSubscription($user);
        if ($sub && $sub->plan && $sub->isUsable()) {
            return $sub->plan;
        }

        $free = StoragePlan::defaultFree();
        if (!$free) {
            // Synthetic fallback — should only happen if admin deleted
            // the seeded free plan (which would be a bug).
            $free = new StoragePlan([
                'code'                => 'free',
                'name'                => 'Free',
                'storage_bytes'       => 5368709120, // 5 GB
                'max_file_size_bytes' => 104857600,  // 100 MB
                'features'            => ['sharing'],
            ]);
        }

        return $free;
    }

    public function canAccessFeature(User $user, string $feature): bool
    {
        return $this->currentPlan($user)->hasFeature($feature);
    }

    /**
     * Pre-upload gate used by FileManagerService.
     *
     * Returns [true, null] on success or [false, reason] on failure.
     * Reasons: 'system_disabled' | 'quota_exceeded' | 'file_too_large'
     *          | 'file_count_exceeded'
     */
    public function canUpload(User $user, int $sizeBytes): array
    {
        if (!$this->systemEnabled()) {
            return [false, 'system_disabled'];
        }

        $plan    = $this->currentPlan($user);
        $used    = (int) ($user->storage_used_bytes ?? 0);
        $quota   = (int) ($user->storage_quota_bytes ?? $plan->storage_bytes);

        if ($plan->max_file_size_bytes && $sizeBytes > $plan->max_file_size_bytes) {
            return [false, 'file_too_large'];
        }

        if ($quota > 0 && ($used + $sizeBytes) > $quota) {
            return [false, 'quota_exceeded'];
        }

        if ($plan->max_files) {
            $current = UserFile::where('user_id', $user->id)->whereNull('deleted_at')->count();
            if ($current >= $plan->max_files) {
                return [false, 'file_count_exceeded'];
            }
        }

        return [true, null];
    }

    // ────────────────────────────────────────────────────────────────────
    // Subscribe + first-time activation
    // ────────────────────────────────────────────────────────────────────

    /**
     * Create a new subscription + invoice + order.
     *
     * Free plan: activates immediately, no charge.
     * Paid plan: creates a pending sub + invoice + order. Caller routes
     *            the user to the existing checkout flow; webhook fires
     *            activateFromPaidInvoice() on successful payment.
     */
    public function subscribe(
        User $user,
        StoragePlan $plan,
        string $paymentMethodType = 'omise',
        bool $annual = false
    ): UserStorageSubscription {
        return DB::transaction(function () use ($user, $plan, $paymentMethodType, $annual) {
            // Cancel any prior active/pending/grace sub atomically.
            UserStorageSubscription::where('user_id', $user->id)
                ->whereIn('status', [
                    UserStorageSubscription::STATUS_ACTIVE,
                    UserStorageSubscription::STATUS_PENDING,
                    UserStorageSubscription::STATUS_GRACE,
                ])
                ->lockForUpdate()
                ->update([
                    'status'       => UserStorageSubscription::STATUS_CANCELLED,
                    'cancelled_at' => now(),
                    'updated_at'   => now(),
                ]);

            $cycle  = $annual ? 'annual' : 'monthly';
            $amount = $annual && $plan->price_annual_thb
                ? (float) $plan->price_annual_thb
                : (float) $plan->price_thb;

            $now         = now();
            $endOfPeriod = $annual ? $now->copy()->addYear() : $now->copy()->addMonth();

            $sub = UserStorageSubscription::create([
                'user_id'              => $user->id,
                'plan_id'              => $plan->id,
                'status'               => $plan->isFree()
                    ? UserStorageSubscription::STATUS_ACTIVE
                    : UserStorageSubscription::STATUS_PENDING,
                'started_at'           => $plan->isFree() ? $now : null,
                'current_period_start' => $plan->isFree() ? $now : null,
                'current_period_end'   => $plan->isFree() ? $endOfPeriod : null,
                'payment_method_type'  => $paymentMethodType,
                'meta'                 => [
                    'billing_cycle'     => $cycle,
                    'signup_plan_price' => $amount,
                ],
            ]);

            if ($plan->isFree()) {
                $this->syncUserCache($user, $sub);
                return $sub;
            }

            // Paid: create invoice + order.
            $invoice = UserStorageInvoice::create([
                'subscription_id' => $sub->id,
                'user_id'         => $user->id,
                'invoice_number'  => UserStorageInvoice::generateInvoiceNumber(),
                'period_start'    => $now,
                'period_end'      => $endOfPeriod,
                'amount_thb'      => $amount,
                'status'          => UserStorageInvoice::STATUS_PENDING,
                'meta'            => [
                    'plan_code'     => $plan->code,
                    'billing_cycle' => $cycle,
                ],
            ]);

            $order = Order::create([
                'user_id'                 => $user->id,
                'order_number'            => 'STR-'.$now->format('ymd').'-'.strtoupper(bin2hex(random_bytes(3))),
                'order_type'              => Order::TYPE_USER_STORAGE_SUBSCRIPTION,
                'user_storage_invoice_id' => $invoice->id,
                'total'                   => $amount,
                'status'                  => 'pending_payment',
                'note'                    => "Cloud storage: {$plan->name} ({$cycle})",
            ]);

            $invoice->update(['order_id' => $order->id]);

            return $sub->fresh('plan');
        });
    }

    /**
     * Called by the payment webhook when the order linked to the invoice
     * flips to paid. Promotes pending → active, extends the period, and
     * refreshes the user's cached quota columns.
     */
    public function activateFromPaidInvoice(Order $order): ?UserStorageSubscription
    {
        if (!$order->isUserStorageOrder() || !$order->user_storage_invoice_id) {
            return null;
        }

        return DB::transaction(function () use ($order) {
            $invoice = UserStorageInvoice::lockForUpdate()->find($order->user_storage_invoice_id);
            if (!$invoice || $invoice->status === UserStorageInvoice::STATUS_PAID) {
                // Idempotent — webhook retries land safely.
                return $invoice?->subscription;
            }

            $sub = UserStorageSubscription::with('plan')
                ->lockForUpdate()
                ->find($invoice->subscription_id);
            if (!$sub) return null;

            $now = now();

            $invoice->update([
                'status'  => UserStorageInvoice::STATUS_PAID,
                'paid_at' => $now,
            ]);

            $isFirstActivation = is_null($sub->started_at);

            $sub->update([
                'status'               => UserStorageSubscription::STATUS_ACTIVE,
                'started_at'           => $sub->started_at ?? $now,
                'current_period_start' => $invoice->period_start ?? $now,
                'current_period_end'   => $invoice->period_end ?? $now->copy()->addMonth(),
                'last_renewed_at'      => $isFirstActivation ? null : $now,
                'renewal_attempts'     => 0,
                'next_retry_at'        => null,
                'grace_ends_at'        => null,
                'last_failure_reason'  => null,
            ]);

            $user = User::find($sub->user_id);
            if ($user) {
                $this->syncUserCache($user, $sub->fresh('plan'));
            }

            return $sub->fresh('plan');
        });
    }

    // ────────────────────────────────────────────────────────────────────
    // Renewals
    // ────────────────────────────────────────────────────────────────────

    /**
     * Create a renewal invoice + order for the next period. Caller
     * (renewal cron) is responsible for charging via the gateway.
     */
    public function renew(UserStorageSubscription $sub): ?Order
    {
        if ($sub->cancel_at_period_end || !$sub->plan) {
            return null;
        }

        return DB::transaction(function () use ($sub) {
            $plan = $sub->plan;
            if ($plan->isFree()) return null;

            $now         = now();
            $periodStart = $sub->current_period_end && $sub->current_period_end->isFuture()
                ? $sub->current_period_end
                : $now;
            $cycle       = $sub->meta['billing_cycle'] ?? 'monthly';
            $periodEnd   = $cycle === 'annual'
                ? $periodStart->copy()->addYear()
                : $periodStart->copy()->addMonth();
            $amount      = $cycle === 'annual' && $plan->price_annual_thb
                ? (float) $plan->price_annual_thb
                : (float) $plan->price_thb;

            // ─── Idempotency guard ──────────────────────────────────
            // Mirrors SubscriptionService::renew(). Reuse the existing
            // pending Order rather than spawning a duplicate every time
            // the hourly `user-storage:renew-due` cron sees this sub.
            $existing = UserStorageInvoice::query()
                ->where('subscription_id', $sub->id)
                ->where('status', UserStorageInvoice::STATUS_PENDING)
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

            $invoice = UserStorageInvoice::create([
                'subscription_id' => $sub->id,
                'user_id'         => $sub->user_id,
                'invoice_number'  => UserStorageInvoice::generateInvoiceNumber(),
                'period_start'    => $periodStart,
                'period_end'      => $periodEnd,
                'amount_thb'      => $amount,
                'status'          => UserStorageInvoice::STATUS_PENDING,
                'meta'            => [
                    'plan_code'     => $plan->code,
                    'billing_cycle' => $cycle,
                    'renewal'       => true,
                ],
            ]);

            $order = Order::create([
                'user_id'                 => $sub->user_id,
                'order_number'            => 'STR-'.$now->format('ymd').'-'.strtoupper(bin2hex(random_bytes(3))),
                'order_type'              => Order::TYPE_USER_STORAGE_SUBSCRIPTION,
                'user_storage_invoice_id' => $invoice->id,
                'total'                   => $amount,
                'status'                  => 'pending_payment',
                'note'                    => "Storage renewal: {$plan->name}",
            ]);

            $invoice->update(['order_id' => $order->id]);

            $sub->increment('renewal_attempts');
            $sub->update(['next_retry_at' => $now->copy()->addDays(2)]);

            return $order;
        });
    }

    /**
     * Record a failed renewal attempt. Drops into grace once max attempts
     * exceeded. The expire-grace scheduled job handles the final downgrade.
     */
    public function markRenewalFailed(UserStorageSubscription $sub, string $reason = ''): void
    {
        $max = $this->maxRenewalAttempts();
        $now = now();

        DB::transaction(function () use ($sub, $max, $reason, $now) {
            $attempts = (int) $sub->renewal_attempts;
            $update   = ['renewal_attempts' => $attempts, 'last_failure_reason' => substr($reason, 0, 240)];

            if ($attempts >= $max && $sub->status !== UserStorageSubscription::STATUS_GRACE) {
                $update['status']        = UserStorageSubscription::STATUS_GRACE;
                $update['grace_ends_at'] = $now->copy()->addDays($this->graceDays());
                $update['next_retry_at'] = null;
            } else {
                $update['next_retry_at'] = $now->copy()->addDays(2);
            }

            $sub->update($update);

            Log::info('User storage renewal failed', [
                'subscription_id' => $sub->id,
                'attempts'        => $attempts,
                'status_after'    => $update['status'] ?? $sub->status,
                'reason'          => $reason,
            ]);
        });
    }

    // ────────────────────────────────────────────────────────────────────
    // Cancel / change plan
    // ────────────────────────────────────────────────────────────────────

    /**
     * Cancel a subscription. Default: stays active until period end
     * (user keeps paid-tier features until then). $immediate=true is
     * the admin hard-cancel escape hatch.
     */
    public function cancel(UserStorageSubscription $sub, bool $immediate = false): UserStorageSubscription
    {
        return DB::transaction(function () use ($sub, $immediate) {
            $sub->refresh();
            $now = now();

            if ($immediate) {
                $sub->update([
                    'status'               => UserStorageSubscription::STATUS_CANCELLED,
                    'cancelled_at'         => $now,
                    'cancel_at_period_end' => true,
                    'current_period_end'   => $now,
                ]);

                $user = User::find($sub->user_id);
                if ($user) {
                    $freeSub = $this->ensureFreeSubscription($user);
                    $this->syncUserCache($user, $freeSub);
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
     * Resume a not-yet-expired cancelled subscription (still in its
     * current period).
     */
    public function resume(UserStorageSubscription $sub): UserStorageSubscription
    {
        if ($sub->status !== UserStorageSubscription::STATUS_ACTIVE) {
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
     * Returns one of:
     *   ['type' => 'noop',      'subscription' => ..., 'order' => null]
     *      Same plan as current.
     *   ['type' => 'deferred',  'subscription' => ..., 'order' => null]
     *      Downgrade scheduled for renewal — renew() reads
     *      meta.pending_plan_code at the period boundary.
     *   ['type' => 'order',     'subscription' => ..., 'order' => Order]
     *      Upgrade — pro-rated invoice + Order created. The plan does
     *      NOT switch until the Order is paid; activateFromPaidInvoice
     *      flips plan_id when fulfillment runs.
     *
     * Pro-ration math (mirrors SubscriptionService::changePlan):
     *   prorated = (newPrice - oldPrice) × (daysRemaining / daysInPeriod)
     *   If the result is < 20 THB (Omise minimum), the upgrade is
     *   silently treated as deferred to avoid creating an unpayable
     *   invoice. End-of-period renewal will pick up the new plan.
     */
    public function changePlan(
        UserStorageSubscription $sub,
        StoragePlan $newPlan,
        bool $applyImmediately = true
    ) {
        return DB::transaction(function () use ($sub, $newPlan, $applyImmediately) {
            $sub->refresh();
            if ($sub->plan_id === $newPlan->id) {
                return ['type' => 'noop', 'subscription' => $sub, 'order' => null];
            }

            $currentPlan = $sub->plan;
            $isUpgrade   = (float) $newPlan->price_thb > (float) ($currentPlan->price_thb ?? 0);

            // Downgrade or user-deferred upgrade → schedule for period end.
            if (!$isUpgrade || !$applyImmediately) {
                $meta = $sub->meta ?? [];
                $meta['pending_plan_code'] = $newPlan->code;
                $sub->update(['meta' => $meta]);
                return ['type' => 'deferred', 'subscription' => $sub->fresh('plan'), 'order' => null];
            }

            // ─── Upgrade path: prorated charge for the remainder ───
            $cycle = $sub->meta['billing_cycle'] ?? 'monthly';
            $now   = now();

            $periodStart = $sub->current_period_start ?? $sub->started_at ?? $now;
            $periodEnd   = $sub->current_period_end
                ?? $now->copy()->{$cycle === 'annual' ? 'addYear' : 'addMonth'}();

            $daysInPeriod  = max(1, (int) \Carbon\Carbon::parse($periodStart)->diffInDays(\Carbon\Carbon::parse($periodEnd)));
            $daysRemaining = max(0, (int) $now->diffInDays(\Carbon\Carbon::parse($periodEnd), false));

            $oldPrice = $cycle === 'annual' && $currentPlan?->price_annual_thb
                ? (float) $currentPlan->price_annual_thb
                : (float) ($currentPlan?->price_thb ?? 0);
            $newPrice = $cycle === 'annual' && $newPlan->price_annual_thb
                ? (float) $newPlan->price_annual_thb
                : (float) $newPlan->price_thb;

            $proratedAmount = round(($newPrice - $oldPrice) * ($daysRemaining / max(1, $daysInPeriod)), 2);

            // Below gateway minimum → treat as deferred (free upgrade
            // for the few remaining days, picked up at next renewal).
            if ($proratedAmount < 20) {
                $meta = $sub->meta ?? [];
                $meta['pending_plan_code'] = $newPlan->code;
                $sub->update(['meta' => $meta]);
                return ['type' => 'deferred', 'subscription' => $sub->fresh('plan'), 'order' => null];
            }

            // Void any prior pending plan-change invoice on this sub
            // to prevent stale Orders from sitting around.
            \App\Models\UserStorageInvoice::where('subscription_id', $sub->id)
                ->where('status', 'pending')
                ->whereNotNull('order_id')
                ->get()
                ->each(function ($inv) {
                    $isPlanChange = (bool) ($inv->meta['plan_change'] ?? false);
                    if (!$isPlanChange) return;
                    if ($inv->order && $inv->order->status === 'pending_payment') {
                        $inv->order->update(['status' => 'cancelled']);
                    }
                    $inv->update(['status' => 'voided']);
                });

            $invoice = \App\Models\UserStorageInvoice::create([
                'subscription_id' => $sub->id,
                'user_id'         => $sub->user_id,
                'invoice_number'  => method_exists(\App\Models\UserStorageInvoice::class, 'generateInvoiceNumber')
                    ? \App\Models\UserStorageInvoice::generateInvoiceNumber()
                    : 'STG-' . $now->format('ymd') . '-' . strtoupper(bin2hex(random_bytes(3))),
                'period_start'    => $now,
                'period_end'      => $periodEnd,
                'amount_thb'      => $proratedAmount,
                'status'          => 'pending',
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

            $order = \App\Models\Order::create([
                'user_id'                  => $sub->user_id,
                'order_number'             => 'STG-' . $now->format('ymd') . '-' . strtoupper(bin2hex(random_bytes(3))),
                'order_type'               => \App\Models\Order::TYPE_USER_STORAGE_SUBSCRIPTION,
                'user_storage_invoice_id'  => $invoice->id,
                'total'                    => $proratedAmount,
                'status'                   => 'pending_payment',
                'note'                     => "Storage plan change: {$currentPlan?->name} → {$newPlan->name} "
                                            . "(prorated {$daysRemaining}/{$daysInPeriod} days)",
            ]);

            $invoice->update(['order_id' => $order->id]);

            // NB: plan_id is NOT switched here — `activateFromPaidInvoice`
            // flips it after the buyer pays. Same pattern as
            // SubscriptionService.

            return ['type' => 'order', 'subscription' => $sub->fresh('plan'), 'order' => $order];
        });
    }

    /**
     * Expire an active storage subscription whose `current_period_end`
     * has passed. Mirror of `SubscriptionService::expireOverdue()` for
     * the consumer-side storage plans.
     *
     * Until auto-charge is implemented (Phase B), this is what enforces
     * the consumer plan period — without it a one-time-paid Personal/
     * Plus/Pro/Max plan kept the elevated quota permanently because no
     * other code transitioned `status='active'` away from active after
     * `current_period_end`.
     *
     * Idempotent: only acts on `active` subs whose period_end is past.
     * Files are NOT deleted even if usage now exceeds the free quota —
     * the file manager's per-upload guard will block new uploads until
     * the user frees space.
     */
    public function expireOverdue(UserStorageSubscription $sub): void
    {
        if ($sub->status !== UserStorageSubscription::STATUS_ACTIVE) {
            return;
        }
        if (!$sub->current_period_end || $sub->current_period_end->isFuture()) {
            return;
        }

        DB::transaction(function () use ($sub) {
            $fresh = UserStorageSubscription::where('id', $sub->id)
                ->lockForUpdate()
                ->first();
            if (!$fresh || $fresh->status !== UserStorageSubscription::STATUS_ACTIVE) {
                return;
            }
            if (!$fresh->current_period_end || $fresh->current_period_end->isFuture()) {
                return;
            }

            $fresh->update([
                'status'       => UserStorageSubscription::STATUS_EXPIRED,
                'cancelled_at' => now(),
            ]);

            $user = User::find($fresh->user_id);
            if ($user) {
                $freeSub = $this->ensureFreeSubscription($user);
                $this->syncUserCache($user, $freeSub);
            }
        });
    }

    /**
     * Grace window expired → downgrade to Free. Runs on schedule.
     *
     * Note: we don't DELETE user files even if they're over the Free cap
     * — that belongs in a follow-up "over-quota dunning" job. For MVP
     * we just flip the plan; the file manager will block new uploads
     * until they free space.
     */
    public function expireGrace(UserStorageSubscription $sub): void
    {
        DB::transaction(function () use ($sub) {
            $sub->refresh();
            if (!$sub->isGrace()) return;

            $sub->update([
                'status'       => UserStorageSubscription::STATUS_EXPIRED,
                'cancelled_at' => now(),
            ]);

            $user = User::find($sub->user_id);
            if ($user) {
                $freeSub = $this->ensureFreeSubscription($user);
                $this->syncUserCache($user, $freeSub);
            }
        });
    }

    // ────────────────────────────────────────────────────────────────────
    // User cache sync
    // ────────────────────────────────────────────────────────────────────

    /**
     * Refresh the denormalised columns on auth_users that the hot-path
     * quota enforcement + dashboard reads.
     *
     * Writes:
     *   - current_storage_sub_id
     *   - storage_plan_code
     *   - storage_plan_status
     *   - storage_renews_at
     *   - storage_quota_bytes   (the number middleware reads!)
     *
     * `storage_used_bytes` is NOT touched here — it's maintained by
     * FileManagerService on upload/delete.
     */
    public function syncUserCache(User $user, UserStorageSubscription $sub): void
    {
        $plan = $sub->plan ?? StoragePlan::defaultFree();
        if (!$plan) return;

        $user->forceFill([
            'current_storage_sub_id' => $sub->id,
            'storage_plan_code'      => $plan->code,
            'storage_plan_status'    => $sub->status,
            'storage_renews_at'      => $sub->current_period_end,
            'storage_quota_bytes'    => $plan->storage_bytes,
        ])->save();
    }

    /**
     * Fully recalc storage_used_bytes from the user_files table. Called
     * after bulk operations or manually via an admin tool if the cache
     * drifts. Not used on the hot path (too expensive for every upload).
     */
    public function recalcUsedBytes(User $user): int
    {
        $bytes = (int) UserFile::where('user_id', $user->id)
            ->whereNull('deleted_at')
            ->sum('size_bytes');
        $user->forceFill(['storage_used_bytes' => $bytes])->save();
        return $bytes;
    }

    /**
     * Ensure the user has a free subscription row to fall back to. Used
     * after grace expiry / hard-cancel. Idempotent — reuses an existing
     * active free sub if one exists.
     */
    public function ensureFreeSubscription(User $user): UserStorageSubscription
    {
        $free = StoragePlan::defaultFree();
        if (!$free) {
            throw new \RuntimeException('No default free storage plan configured; seed storage_plans.');
        }

        $existing = UserStorageSubscription::where('user_id', $user->id)
            ->where('plan_id', $free->id)
            ->where('status', UserStorageSubscription::STATUS_ACTIVE)
            ->first();
        if ($existing) return $existing;

        return $this->subscribe($user, $free);
    }

    // ────────────────────────────────────────────────────────────────────
    // Dashboard helpers
    // ────────────────────────────────────────────────────────────────────

    /**
     * Compact dashboard payload — everything a consumer's storage widget
     * needs in one array. Mirrors SubscriptionService::dashboardSummary.
     */
    public function dashboardSummary(User $user): array
    {
        $plan = $this->currentPlan($user);
        $sub  = $this->currentSubscription($user);

        $used  = (int) ($user->storage_used_bytes ?? 0);
        $quota = (int) ($user->storage_quota_bytes ?? $plan->storage_bytes);
        $pct   = $quota > 0 ? min(100, round(($used / $quota) * 100, 1)) : 0;

        return [
            'enabled'              => $this->systemEnabled(),
            'sales_open'           => $this->salesModeEnabled(),
            'plan'                 => $plan,
            'subscription'         => $sub,
            'storage_used_bytes'   => $used,
            'storage_quota_bytes'  => $quota,
            'storage_used_gb'      => round($used / (1024 ** 3), 3),
            'storage_quota_gb'     => round($quota / (1024 ** 3), 2),
            'storage_used_pct'     => $pct,
            'storage_warn'         => $pct >= 85,
            'storage_critical'     => $pct >= 95,
            'features'             => $plan->features ?? [],
            'is_free'              => $plan->isFree(),
            'has_active_paid'      => $sub && $sub->isUsable() && !$plan->isFree(),
            'cancel_at_period_end' => (bool) ($sub?->cancel_at_period_end ?? false),
            'in_grace'             => $sub?->isGrace() ?? false,
            'grace_ends_at'        => $sub?->grace_ends_at,
            'current_period_end'   => $sub?->current_period_end,
            'days_until_renewal'   => $sub?->daysUntilRenewal(),
        ];
    }

    /**
     * Admin-facing platform KPIs: active subscribers, MRR, grace count,
     * per-plan distribution.
     */
    public function platformKpis(): array
    {
        $activeSubs = UserStorageSubscription::activeOrGrace()->count();

        $mrr = (float) UserStorageSubscription::activeOrGrace()
            ->join('storage_plans', 'storage_plans.id', '=', 'user_storage_subscriptions.plan_id')
            ->where('storage_plans.billing_cycle', 'monthly')
            ->sum('storage_plans.price_thb');

        $annualPrepaid = (float) UserStorageSubscription::activeOrGrace()
            ->join('storage_plans', 'storage_plans.id', '=', 'user_storage_subscriptions.plan_id')
            ->where('storage_plans.billing_cycle', 'annual')
            ->sum('storage_plans.price_annual_thb');

        $last30Paid = (float) UserStorageInvoice::where('status', UserStorageInvoice::STATUS_PAID)
            ->where('paid_at', '>=', now()->subDays(30))
            ->sum('amount_thb');

        $inGrace = UserStorageSubscription::where('status', UserStorageSubscription::STATUS_GRACE)->count();

        $byPlan = UserStorageSubscription::activeOrGrace()
            ->selectRaw('plan_id, COUNT(*) as cnt')
            ->groupBy('plan_id')
            ->pluck('cnt', 'plan_id')
            ->all();

        // Storage usage totals for admin capacity planning
        $totalUsedBytes = (int) DB::table('auth_users')->sum('storage_used_bytes');
        $totalFiles     = (int) UserFile::whereNull('deleted_at')->count();

        return [
            'active_subscribers' => $activeSubs,
            'mrr'                => $mrr,
            'annual_prepaid'     => $annualPrepaid,
            'last30_paid'        => $last30Paid,
            'in_grace'           => $inGrace,
            'by_plan'            => $byPlan,
            'total_used_bytes'   => $totalUsedBytes,
            'total_used_gb'      => round($totalUsedBytes / (1024 ** 3), 2),
            'total_files'        => $totalFiles,
        ];
    }
}

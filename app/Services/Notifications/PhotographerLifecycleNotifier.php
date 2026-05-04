<?php

namespace App\Services\Notifications;

use App\Models\PhotographerProfile;
use App\Models\PhotographerSubscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\LineNotifyService;
use App\Services\MailService;
use Illuminate\Support\Facades\Log;

/**
 * Single entrypoint for every photographer-billing lifecycle notification.
 *
 * Why a notifier (vs scattered notify-from-anywhere)
 * ─────────────────────────────────────────────────
 * Before this class, lifecycle events were either NOT notified at all
 * (most of them) or were notified inconsistently across channels — the
 * in-app would say one thing and the email another. By funnelling
 * everything through one method per event kind, we get:
 *
 *   • One source of truth for wording (via LifecycleMessageFormatter).
 *   • Channel-fanout in one place — easy to mute/redirect channels.
 *   • Idempotency through UserNotification::notifyOnce(refId) so a
 *     cron that runs every 6h doesn't generate 4 identical T-7 alerts.
 *   • Best-effort discipline: a LINE outage never blocks the email,
 *     a mail failure never blocks the in-app — every channel is
 *     wrapped in try/catch.
 *
 * Channel matrix
 * ──────────────
 *   Severity   In-app   LINE   Email
 *   info       ✓        ✓      ✗ (info events stay in app + push)
 *   warn       ✓        ✓      ✓
 *   critical   ✓        ✓      ✓
 *
 * Email is suppressed for INFO-level events to avoid inbox fatigue —
 * "we activated your add-on" is fine as a notification but doesn't
 * need an email. WARN/CRITICAL always emails.
 */
class PhotographerLifecycleNotifier
{
    public function __construct(
        private readonly LifecycleMessageFormatter $formatter,
        private readonly LineNotifyService $line,
        private readonly MailService $mail,
    ) {}

    /* ───────────── Public entrypoints (one per event kind) ───────────── */

    public function subscriptionStarted(PhotographerSubscription $sub): void
    {
        $this->dispatch($sub->photographer_id, $this->formatter->subscriptionStarted($sub));
    }

    public function subscriptionRenewed(PhotographerSubscription $sub): void
    {
        $this->dispatch($sub->photographer_id, $this->formatter->subscriptionRenewed($sub));
    }

    public function subscriptionRenewalFailed(PhotographerSubscription $sub, string $reason = ''): void
    {
        $this->dispatch($sub->photographer_id, $this->formatter->subscriptionRenewalFailed($sub, $reason));
    }

    public function subscriptionExpiringSoon(PhotographerSubscription $sub, int $daysLeft): void
    {
        $this->dispatch($sub->photographer_id, $this->formatter->subscriptionExpiringSoon($sub, $daysLeft));
    }

    public function subscriptionExpired(PhotographerSubscription $sub, ?string $previousPlanName = null): void
    {
        $this->dispatch($sub->photographer_id, $this->formatter->subscriptionExpired($sub, $previousPlanName));
    }

    /**
     * Pre-charge reminder for buyers with auto-renew armed (saved Omise
     * card-on-file). Distinct from `subscriptionExpiringSoon` which
     * targets manual-pay buyers — see the formatter for the wording
     * difference. Caller is `subscriptions:notify-expiring` cron, which
     * branches on `omise_customer_id`.
     */
    public function subscriptionAutoChargeReminder(PhotographerSubscription $sub, int $daysLeft): void
    {
        $this->dispatch($sub->photographer_id, $this->formatter->subscriptionAutoChargeReminder($sub, $daysLeft));
    }

    /**
     * Plan upgrade just took effect (prorated charge paid → new plan
     * active). Fires from `activateFromPaidInvoice()` when the invoice
     * meta flags it as a plan_change. The previous plan name comes
     * from the invoice meta (we capture it BEFORE flipping the
     * subscription's plan_id to the new one).
     */
    public function subscriptionPlanChanged(PhotographerSubscription $sub, ?string $previousPlanName = null): void
    {
        $this->dispatch($sub->photographer_id, $this->formatter->subscriptionPlanChanged($sub, $previousPlanName));
    }

    /**
     * Plan downgrade was scheduled (no immediate charge — takes effect
     * at period_end). Fires from `changePlan()` when the new plan is
     * cheaper than the current plan or the user opted out of immediate
     * proration. Reassures the photographer that they keep their
     * paid-tier perks until the end of the current period.
     */
    public function subscriptionPlanDowngradeScheduled(PhotographerSubscription $sub, SubscriptionPlan $pendingPlan): void
    {
        $this->dispatch($sub->photographer_id, $this->formatter->subscriptionPlanDowngradeScheduled($sub, $pendingPlan));
    }

    public function subscriptionCancelled(PhotographerSubscription $sub): void
    {
        $this->dispatch($sub->photographer_id, $this->formatter->subscriptionCancelled($sub));
    }

    public function subscriptionResumed(PhotographerSubscription $sub): void
    {
        $this->dispatch($sub->photographer_id, $this->formatter->subscriptionResumed($sub));
    }

    public function addonActivated(int $photographerId, int $purchaseId, array $snapshot, ?\Carbon\CarbonInterface $expiresAt): void
    {
        $this->dispatch($photographerId, $this->formatter->addonActivated($purchaseId, $snapshot, $expiresAt));
    }

    public function addonExpiringSoon(int $photographerId, int $purchaseId, array $snapshot, \Carbon\CarbonInterface $expiresAt): void
    {
        $this->dispatch($photographerId, $this->formatter->addonExpiringSoon($purchaseId, $snapshot, $expiresAt));
    }

    public function addonExpired(int $photographerId, int $purchaseId, array $snapshot): void
    {
        $this->dispatch($photographerId, $this->formatter->addonExpired($purchaseId, $snapshot));
    }

    public function storageWarning(int $photographerId, int $usedBytes, int $quotaBytes, bool $critical = false): void
    {
        $this->dispatch($photographerId, $this->formatter->storageUsageWarning($usedBytes, $quotaBytes, $critical));
    }

    public function aiCreditsDepleted(int $photographerId, int $used, int $cap, ?\Carbon\CarbonInterface $resetAt): void
    {
        $this->dispatch($photographerId, $this->formatter->aiCreditsDepleted($used, $cap, $resetAt));
    }

    /* ───────────── Channel fanout ───────────── */

    /**
     * Dispatch a built LifecycleMessage to every channel. Each channel
     * call is wrapped so a failure in one (e.g. LINE outage) never
     * blocks the rest. The in-app notification dedups on refId so cron
     * re-runs don't spam.
     */
    private function dispatch(int $photographerId, LifecycleMessage $message): void
    {
        // ── 1. In-app notification — primary surface, always fires ──
        // notifyOnce dedups on (user_id, type, ref_id) so cron re-runs
        // don't generate duplicate rows for the same expiring-soon event.
        try {
            UserNotification::notifyOnce(
                userId:    $photographerId,
                type:      $message->kind,
                title:     $message->headline,
                message:   $message->shortBody,
                actionUrl: $message->cta['url'] ?? null,
                refId:     $message->refId,
            );
        } catch (\Throwable $e) {
            Log::warning('LifecycleNotifier: in-app notify failed', [
                'photographer_id' => $photographerId,
                'kind'            => $message->kind,
                'error'           => $e->getMessage(),
            ]);
        }

        // ── 2. LINE push — flex bubble + plain-text fallback ──
        try {
            $this->line->pushLifecycleMessage($photographerId, $message);
        } catch (\Throwable $e) {
            Log::debug('LifecycleNotifier: LINE push skipped', [
                'photographer_id' => $photographerId,
                'kind'            => $message->kind,
                'error'           => $e->getMessage(),
            ]);
        }

        // ── 3. Email — only for warn/critical severity ──
        // INFO events (started, renewed, activated, resumed) stay in
        // app + LINE so the inbox doesn't fill up with happy-path
        // confirmations. WARN/CRITICAL always emails because those
        // require photographer action.
        if (in_array($message->severity, [
            LifecycleMessage::SEVERITY_WARN,
            LifecycleMessage::SEVERITY_CRITICAL,
        ], true)) {
            try {
                $user    = User::find($photographerId);
                $profile = PhotographerProfile::where('user_id', $photographerId)->first();
                if ($user && $user->email && $profile) {
                    $this->mail->sendTemplate(
                        $user->email,
                        $message->subject,
                        'emails.photographer.lifecycle',
                        [
                            'name'     => $profile->display_name
                                       ?? trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')),
                            'message'  => $message,
                            // Convenience aliases for templates that need
                            // top-level fields without reaching into the
                            // PayoutMessage object.
                            'headline' => $message->headline,
                            'body'     => $message->body,
                            'bullets'  => $message->bullets,
                            'cta'      => $message->cta,
                            'severity' => $message->severity,
                        ],
                        'photographer_lifecycle',
                    );
                }
            } catch (\Throwable $e) {
                Log::debug('LifecycleNotifier: email send skipped', [
                    'photographer_id' => $photographerId,
                    'kind'            => $message->kind,
                    'error'           => $e->getMessage(),
                ]);
            }
        }
    }

}

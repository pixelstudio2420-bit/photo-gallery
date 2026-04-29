<?php

namespace Tests\Feature\Notifications;

use App\Models\PhotographerSubscription;
use App\Models\SubscriptionPlan;
use App\Services\Notifications\LifecycleMessage;
use App\Services\Notifications\LifecycleMessageFormatter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Locks down the canonical wording for every photographer-lifecycle
 * event. The notifier is dumb fan-out — these tests pin the actual
 * COPY each photographer sees so accidental re-wording in a refactor
 * gets caught.
 */
class LifecycleMessageFormatterTest extends TestCase
{
    use RefreshDatabase;

    private LifecycleMessageFormatter $f;

    protected function setUp(): void
    {
        parent::setUp();
        $this->f = new LifecycleMessageFormatter();
    }

    private function makePlan(array $overrides = []): SubscriptionPlan
    {
        // The seeded default 'pro' plan already exists from migrations,
        // so each test gets a fresh plan with a unique code.
        return SubscriptionPlan::create(array_merge([
            'code'                => 'test-' . uniqid(),
            'name'                => 'Pro',
            'price_thb'           => 299,
            'billing_cycle'       => 'monthly',
            'storage_bytes'       => 100 * 1024 * 1024 * 1024,
            'monthly_ai_credits'  => 5000,
            'commission_pct'      => 8,
            'is_active'           => true,
            'is_default_free'     => false,
            'is_public'           => true,
            'sort_order'          => 99,
        ], $overrides));
    }

    private function makeSub(?SubscriptionPlan $plan = null, array $overrides = []): PhotographerSubscription
    {
        $plan ??= $this->makePlan();
        return PhotographerSubscription::create(array_merge([
            'photographer_id' => 99,
            'plan_id'         => $plan->id,
            'status'          => 'pending',
            'started_at'      => null,
            'current_period_start' => now(),
            'current_period_end'   => now()->addMonth(),
            'cancel_at_period_end' => false,
            'renewal_attempts'     => 0,
        ], $overrides));
    }

    /* ───────────────── Subscription started ───────────────── */

    public function test_started_message_has_required_fields(): void
    {
        $sub = $this->makeSub();
        $m = $this->f->subscriptionStarted($sub);

        $this->assertSame(LifecycleMessage::KIND_SUBSCRIPTION_STARTED, $m->kind);
        $this->assertSame(LifecycleMessage::SEVERITY_INFO, $m->severity);
        $this->assertStringContainsString('🎉', $m->headline);
        $this->assertStringContainsString('Pro', $m->headline);
        $this->assertStringContainsString('photographer/store/status', $m->cta['url']);
        $this->assertStringContainsString("฿\xC2\xA0299", $m->bullets[0]);   // nbsp baht
        $this->assertSame("sub.{$sub->id}.started", $m->refId);
    }

    /* ───────────────── Renewal succeeded ───────────────── */

    public function test_renewed_message_includes_period_end(): void
    {
        $sub = $this->makeSub(null, [
            'current_period_end' => now()->addMonth()->setTime(0, 0),
            'last_renewed_at'    => now(),
        ]);
        $m = $this->f->subscriptionRenewed($sub);

        $this->assertSame(LifecycleMessage::KIND_SUBSCRIPTION_RENEWED, $m->kind);
        $this->assertStringContainsString('🔄', $m->headline);
        $this->assertStringContainsString($sub->current_period_end->format('d/m/Y'), $m->shortBody);
        // refId carries the renewal date so two renewals in different
        // months get distinct dedup keys
        $this->assertStringContainsString($sub->last_renewed_at->format('Ymd'), $m->refId);
    }

    /* ───────────────── Renewal failed (grace) ───────────────── */

    public function test_renewal_failed_is_critical_and_includes_reason(): void
    {
        $sub = $this->makeSub(null, [
            'status'          => 'grace',
            'grace_ends_at'   => now()->addDays(7),
            'renewal_attempts' => 3,
        ]);
        $m = $this->f->subscriptionRenewalFailed($sub, 'card declined');

        $this->assertSame(LifecycleMessage::SEVERITY_CRITICAL, $m->severity);
        $this->assertStringContainsString('⚠️', $m->headline);
        // Reason ends up in the bullets list (with the "เหตุผล:" prefix),
        // not in the body — body is the wrap-up copy.
        $this->assertStringContainsString('card declined', implode("\n", $m->bullets));
        // refId carries renewal_attempts so retries 1/2/3 each fire
        // their own in-app notification, not just the first
        $this->assertStringContainsString('renewal_failed.3', $m->refId);
    }

    /* ───────────────── Expiring soon — T-7 vs T-1 ───────────────── */

    public function test_expiring_T7_is_warn_and_T1_is_critical(): void
    {
        $sub = $this->makeSub();
        $t7 = $this->f->subscriptionExpiringSoon($sub, 7);
        $t1 = $this->f->subscriptionExpiringSoon($sub, 1);

        $this->assertSame(LifecycleMessage::SEVERITY_WARN,     $t7->severity);
        $this->assertSame(LifecycleMessage::SEVERITY_CRITICAL, $t1->severity);
        $this->assertStringContainsString('7 วัน', $t7->headline);
        $this->assertStringContainsString('พรุ่งนี้', $t1->headline);
        // Distinct refIds — both must be free to fire independently
        $this->assertNotSame($t7->refId, $t1->refId);
    }

    public function test_expiring_warns_when_auto_renew_off(): void
    {
        $sub = $this->makeSub(null, ['cancel_at_period_end' => true]);
        $m = $this->f->subscriptionExpiringSoon($sub, 7);

        $this->assertStringContainsString('ไม่ได้ตั้งต่ออายุ', implode("\n", $m->bullets));
        $this->assertSame('ต่ออายุเลย', $m->cta['label']);
    }

    /* ───────────────── Expired (downgraded) ───────────────── */

    public function test_expired_message_says_downgraded_to_free(): void
    {
        $sub = $this->makeSub();
        $m = $this->f->subscriptionExpired($sub, 'Pro');

        $this->assertStringContainsString('Pro', $m->headline);
        $this->assertStringContainsString('สิ้นสุด', $m->headline);
        $this->assertStringContainsString('แผนฟรี', $m->body);
        $this->assertSame(LifecycleMessage::SEVERITY_CRITICAL, $m->severity);
    }

    /* ───────────────── Cancelled / Resumed ───────────────── */

    public function test_cancelled_message_states_period_end(): void
    {
        $end = now()->addDays(15);
        $sub = $this->makeSub(null, ['current_period_end' => $end]);
        $m = $this->f->subscriptionCancelled($sub);

        $this->assertStringContainsString($end->format('d/m/Y'), implode("\n", $m->bullets));
        $this->assertStringContainsString('ใช้แผนต่อ', $m->cta['label']);
    }

    /* ───────────────── Add-on activated ───────────────── */

    public function test_addon_activated_includes_label_and_expiry(): void
    {
        $expires = now()->addDays(30);
        $m = $this->f->addonActivated(
            purchaseId: 42,
            snapshot:   ['label' => 'Boost · 1 เดือน', 'price_thb' => 299, 'tagline' => 'คุ้มสุด'],
            expiresAt:  $expires,
        );

        $this->assertStringContainsString('Boost · 1 เดือน', $m->headline);
        $this->assertStringContainsString('คุ้มสุด', implode("\n", $m->bullets));
        $this->assertStringContainsString($expires->format('d/m/Y'), implode("\n", $m->bullets));
        $this->assertSame('addon.42.activated', $m->refId);
    }

    public function test_addon_activated_lifetime_when_no_expiry(): void
    {
        $m = $this->f->addonActivated(
            purchaseId: 1,
            snapshot:   ['label' => 'Custom Watermark', 'price_thb' => 990],
            expiresAt:  null,
        );

        $bullets = implode("\n", $m->bullets);
        $this->assertStringContainsString('ตลอดชีพ', $bullets);
    }

    /* ───────────────── Add-on expiring / expired ───────────────── */

    public function test_addon_expiring_is_warn_severity(): void
    {
        $m = $this->f->addonExpiringSoon(
            purchaseId: 1,
            snapshot:   ['label' => 'Boost'],
            expiresAt:  now()->addDays(3),
        );

        $this->assertSame(LifecycleMessage::SEVERITY_WARN, $m->severity);
        $this->assertStringContainsString('3 วัน', $m->headline);
    }

    /* ───────────────── Storage warnings ───────────────── */

    public function test_storage_warning_critical_uses_red_flex(): void
    {
        $m = $this->f->storageUsageWarning(
            usedBytes:  95 * 1024 * 1024 * 1024,
            quotaBytes: 100 * 1024 * 1024 * 1024,
            critical:   true,
        );

        $this->assertSame(LifecycleMessage::SEVERITY_CRITICAL, $m->severity);
        $this->assertSame('#dc2626', $m->flexBubble['header']['backgroundColor']);
        $this->assertStringContainsString('95', $m->headline);
    }

    public function test_storage_warning_warn_uses_amber_flex(): void
    {
        $m = $this->f->storageUsageWarning(
            usedBytes:  82 * 1024 * 1024 * 1024,
            quotaBytes: 100 * 1024 * 1024 * 1024,
            critical:   false,
        );

        $this->assertSame(LifecycleMessage::SEVERITY_WARN, $m->severity);
        $this->assertSame('#f59e0b', $m->flexBubble['header']['backgroundColor']);
    }

    public function test_storage_warning_refid_separates_warn_and_critical(): void
    {
        // Same photographer same month — warn and critical must have
        // DISTINCT refIds so a 80% warn doesn't suppress a later 95%
        // critical via the dedup key.
        $warn = $this->f->storageUsageWarning(
            80 * 1024 * 1024 * 1024,
            100 * 1024 * 1024 * 1024,
            critical: false,
        );
        $crit = $this->f->storageUsageWarning(
            95 * 1024 * 1024 * 1024,
            100 * 1024 * 1024 * 1024,
            critical: true,
        );

        $this->assertNotSame($warn->refId, $crit->refId,
            'Warn and critical must have distinct refIds — otherwise critical alert would be suppressed by an earlier warn.');
    }

    /* ───────────────── AI credits depleted ───────────────── */

    public function test_ai_credits_depleted_shows_used_and_cap(): void
    {
        $m = $this->f->aiCreditsDepleted(
            used:    5200,
            cap:     5000,
            resetAt: now()->addDays(10),
        );

        $this->assertSame(LifecycleMessage::SEVERITY_CRITICAL, $m->severity);
        $bullets = implode("\n", $m->bullets);
        $this->assertStringContainsString('5,200', $bullets);
        $this->assertStringContainsString('5,000', $bullets);
    }

    /* ───────────────── plainText fallback ───────────────── */

    public function test_plain_text_includes_headline_body_bullets_cta(): void
    {
        $sub = $this->makeSub();
        $m = $this->f->subscriptionStarted($sub);
        $text = $m->plainText();

        $this->assertStringContainsString($m->headline, $text);
        $this->assertStringContainsString($m->body, $text);
        $this->assertStringContainsString('• ราคา', $text);
        $this->assertStringContainsString($m->cta['url'], $text);
    }
}

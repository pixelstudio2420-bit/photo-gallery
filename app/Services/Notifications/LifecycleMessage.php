<?php

namespace App\Services\Notifications;

/**
 * Immutable structured payload for a photographer-lifecycle event.
 *
 * Built by {@see LifecycleMessageFormatter}, consumed by every channel
 * (in-app via UserNotification, LINE via LineNotifyService, email via
 * MailService). Treat as a DTO — channels read what they need; never
 * mutate.
 *
 * Same design as PayoutMessage: ONE source of truth for wording so a
 * photographer comparing the LINE push to their email inbox sees the
 * same numbers, same headline, same CTA. Drift between channels was
 * the recurring complaint pre-formatter.
 */
final class LifecycleMessage
{
    /* Event kinds. The notifier dispatches in-app type strings keyed off
     * these so the photographer's preference center can mute categories. */

    public const KIND_SUBSCRIPTION_STARTED      = 'subscription.started';
    public const KIND_SUBSCRIPTION_RENEWED      = 'subscription.renewed';
    public const KIND_SUBSCRIPTION_RENEWAL_FAIL = 'subscription.renewal_failed';
    public const KIND_SUBSCRIPTION_EXPIRING     = 'subscription.expiring';
    public const KIND_SUBSCRIPTION_GRACE        = 'subscription.in_grace';
    public const KIND_SUBSCRIPTION_EXPIRED      = 'subscription.expired';
    public const KIND_SUBSCRIPTION_CANCELLED    = 'subscription.cancelled';
    public const KIND_SUBSCRIPTION_RESUMED      = 'subscription.resumed';
    public const KIND_SUBSCRIPTION_CHANGED      = 'subscription.plan_changed';
    public const KIND_ADDON_ACTIVATED           = 'addon.activated';
    public const KIND_ADDON_EXPIRING            = 'addon.expiring';
    public const KIND_ADDON_EXPIRED             = 'addon.expired';
    public const KIND_USAGE_STORAGE_WARNING     = 'usage.storage_warning';
    public const KIND_USAGE_STORAGE_CRITICAL    = 'usage.storage_critical';
    public const KIND_USAGE_AI_CREDITS_DEPLETED = 'usage.ai_credits_depleted';

    /* Severity affects in-app icon + LINE flex header colour. */
    public const SEVERITY_INFO     = 'info';
    public const SEVERITY_WARN     = 'warn';
    public const SEVERITY_CRITICAL = 'critical';

    public function __construct(
        public readonly string $kind,
        public readonly string $severity,
        public readonly string $headline,        // "🎉 เริ่มต้นแผน Pro แล้ว"
        public readonly string $shortBody,       // 1-line preview ≤ 80 chars
        public readonly string $body,            // multi-line full text
        public readonly array  $bullets,         // ["ราคา: ฿299/เดือน", "ต่ออายุ: 28/05/2026", ...]
        public readonly array  $cta,             // ['label' => 'ดูแผนของฉัน', 'url' => 'https://...']
        public readonly string $subject,         // email subject
        public readonly array  $flexBubble,      // LINE Flex bubble JSON
        // Stable identifier so duplicate-prevention works ("don't fire the
        // same warning twice today"). E.g. "sub.42.expiring.7d" — the
        // "%notification%::%photographerId%::%kind%::%refId%" tuple is
        // dedup'd by the notifier via UserNotification::notifyOnce().
        public readonly string $refId,
    ) {}

    public function isCritical(): bool
    {
        return $this->severity === self::SEVERITY_CRITICAL;
    }

    /**
     * Plain-text body for LINE push fallback or email plain-part.
     * Stitches headline → body → bullets → CTA url with line breaks.
     */
    public function plainText(): string
    {
        $parts = [$this->headline, $this->body];
        if (!empty($this->bullets)) {
            $parts[] = '';
            foreach ($this->bullets as $b) {
                $parts[] = "• {$b}";
            }
        }
        if (!empty($this->cta['url'] ?? '')) {
            $parts[] = '';
            $parts[] = "{$this->cta['label']}: {$this->cta['url']}";
        }
        return implode("\n", $parts);
    }
}

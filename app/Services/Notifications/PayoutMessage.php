<?php

namespace App\Services\Notifications;

/**
 * Immutable structured payload representing a single payout-event message.
 *
 * Constructed by {@see PayoutMessageFormatter}, consumed by every
 * notification channel (in-app, LINE text/flex, email). Treat it like a
 * DTO — channels read what they need; never mutate.
 */
final class PayoutMessage
{
    public const KIND_SUCCESS         = 'success';
    public const KIND_FAILURE_GENERIC = 'failure_generic';
    public const KIND_FAILURE_NAME    = 'failure_name_mismatch';

    public function __construct(
        public readonly string $kind,        // success | failure_generic | failure_name_mismatch
        public readonly string $headline,    // "✅ เงินรายได้โอนแล้ว"
        public readonly string $amount,      // pre-formatted "฿ 1,234.56"
        public readonly string $shortBody,   // 1-line preview ≤ 80 chars
        public readonly string $body,        // multi-line full text
        public readonly array  $bullets,     // list of "label: value" strings
        public readonly array  $cta,         // ['label' => string, 'url' => string]
        public readonly string $subject,     // email subject
        public readonly array  $flexBubble,  // LINE Flex bubble JSON
    ) {}

    /** Convenience: is this a success message? */
    public function isSuccess(): bool
    {
        return $this->kind === self::KIND_SUCCESS;
    }

    /** Convenience: is this any failure variant? */
    public function isFailure(): bool
    {
        return $this->kind === self::KIND_FAILURE_GENERIC
            || $this->kind === self::KIND_FAILURE_NAME;
    }

    /**
     * Plain-text body suitable for LINE push or fallback email.
     * Joins headline + body + bullets + CTA URL into a single string with
     * line breaks — channels that don't support rich formatting still
     * deliver a complete, scannable message.
     */
    public function plainText(): string
    {
        $parts = [
            $this->headline,
            $this->body,
        ];
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

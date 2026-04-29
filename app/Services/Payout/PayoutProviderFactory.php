<?php

namespace App\Services\Payout;

use App\Models\AppSetting;
use App\Services\Payout\Providers\MockPayoutProvider;
use App\Services\Payout\Providers\OmisePayoutProvider;

/**
 * Resolve the active payout provider based on the `payout_provider` AppSetting.
 *
 * Mock is the default — a fresh install can run the full payout pipeline
 * against the mock (so engineers see the UX end-to-end) before Ops finishes
 * the real provider contract. Swap via Admin → Payout Settings.
 */
class PayoutProviderFactory
{
    /**
     * @param string|null $forceName Bypass AppSetting and resolve a specific provider
     *                                by name. Used by the admin health-check page.
     */
    public function make(?string $forceName = null): PayoutProviderInterface
    {
        $name = $forceName ?? (string) AppSetting::get('payout_provider', 'mock');

        return match (strtolower($name)) {
            'omise' => new OmisePayoutProvider(),
            default => new MockPayoutProvider(),
        };
    }

    /** All provider names the admin can pick — order reflects maturity. */
    public function available(): array
    {
        return [
            'mock'  => 'Mock (dev / staging — no real money)',
            'omise' => 'Omise Transfers (PromptPay — production ready)',
        ];
    }
}

<?php

namespace App\Finance;

use App\Finance\Models\FinancialAccount;

/**
 * Chart of Accounts — codes + the helper that gets-or-creates them.
 *
 * Codes follow GAAP-style hierarchy:
 *   1xxx asset
 *   2xxx liability
 *   3xxx equity
 *   4xxx revenue
 *   5xxx expense
 *
 * Per-user accounts use a suffix scheme so each (user × type) pair
 * gets exactly one row. The canonical accounts are seeded on first
 * boot via this service's `ensure*` helpers — there is no separate
 * seeder migration so the chart can evolve without DB migrations.
 */
final class ChartOfAccounts
{
    // ── Platform-level accounts (singletons, currency-scoped) ──────
    public const PLATFORM_CASH                  = '1000';
    public const STRIPE_RECEIVABLE              = '1100';
    public const OMISE_RECEIVABLE               = '1200';
    public const PAYPAL_RECEIVABLE              = '1300';
    public const PROMPTPAY_RECEIVABLE           = '1400';

    public const COMMISSION_REVENUE             = '4000';
    public const SUBSCRIPTION_REVENUE           = '4100';
    public const STORAGE_SUB_REVENUE            = '4200';
    public const DIGITAL_PRODUCT_REVENUE        = '4300';

    public const VAT_COLLECTED_PAYABLE          = '2200';   // owed to govt
    public const REFUNDS_PAYABLE                = '2300';

    public const AI_COST_EXPENSE                = '5000';
    public const STORAGE_COST_EXPENSE           = '5100';
    public const BANDWIDTH_COST_EXPENSE         = '5200';
    public const REFUND_EXPENSE                 = '5300';
    public const PAYMENT_GATEWAY_FEE_EXPENSE    = '5400';

    public const RETAINED_EARNINGS              = '3000';

    /**
     * Get-or-create a system account.
     * Idempotent — repeated calls return the same row.
     */
    public function platform(string $code, string $currency = 'THB'): FinancialAccount
    {
        return FinancialAccount::firstOrCreate(
            ['account_code' => "{$code}-{$currency}"],
            $this->platformDefaultsFor($code, $currency),
        );
    }

    /**
     * Get-or-create a per-photographer payable account. Liability —
     * platform owes the photographer their share until disbursed.
     */
    public function photographerPayable(int $photographerUserId, string $currency = 'THB'): FinancialAccount
    {
        return FinancialAccount::firstOrCreate(
            ['account_code' => "P-{$photographerUserId}-PAYABLE-{$currency}"],
            [
                'account_type' => FinancialAccount::TYPE_LIABILITY,
                'name'         => "Payable to photographer #{$photographerUserId}",
                'currency'     => $currency,
                'owner_type'   => 'photographer',
                'owner_id'     => $photographerUserId,
                'is_active'    => true,
            ],
        );
    }

    /**
     * Get-or-create a per-customer credit balance account. Liability
     * because we owe the customer the balance until they spend it
     * (e.g. refunds-as-credit, prepaid wallet).
     */
    public function customerCredit(int $customerUserId, string $currency = 'THB'): FinancialAccount
    {
        return FinancialAccount::firstOrCreate(
            ['account_code' => "C-{$customerUserId}-CREDIT-{$currency}"],
            [
                'account_type' => FinancialAccount::TYPE_LIABILITY,
                'name'         => "Customer #{$customerUserId} credit balance",
                'currency'     => $currency,
                'owner_type'   => 'customer',
                'owner_id'     => $customerUserId,
                'is_active'    => true,
            ],
        );
    }

    /** @return array<string, mixed> */
    private function platformDefaultsFor(string $code, string $currency): array
    {
        // (account_type, name) lookup table for the canonical codes.
        $map = [
            self::PLATFORM_CASH                => [FinancialAccount::TYPE_ASSET,     'Platform cash'],
            self::STRIPE_RECEIVABLE            => [FinancialAccount::TYPE_ASSET,     'Stripe receivable (in transit)'],
            self::OMISE_RECEIVABLE             => [FinancialAccount::TYPE_ASSET,     'Omise receivable (in transit)'],
            self::PAYPAL_RECEIVABLE            => [FinancialAccount::TYPE_ASSET,     'PayPal receivable (in transit)'],
            self::PROMPTPAY_RECEIVABLE         => [FinancialAccount::TYPE_ASSET,     'PromptPay receivable (in transit)'],
            self::COMMISSION_REVENUE           => [FinancialAccount::TYPE_REVENUE,   'Commission revenue'],
            self::SUBSCRIPTION_REVENUE         => [FinancialAccount::TYPE_REVENUE,   'Subscription revenue'],
            self::STORAGE_SUB_REVENUE          => [FinancialAccount::TYPE_REVENUE,   'Consumer storage subscription revenue'],
            self::DIGITAL_PRODUCT_REVENUE      => [FinancialAccount::TYPE_REVENUE,   'Digital product revenue'],
            self::VAT_COLLECTED_PAYABLE        => [FinancialAccount::TYPE_LIABILITY, 'VAT collected (owed to government)'],
            self::REFUNDS_PAYABLE              => [FinancialAccount::TYPE_LIABILITY, 'Refunds payable'],
            self::AI_COST_EXPENSE              => [FinancialAccount::TYPE_EXPENSE,   'AI / Rekognition cost'],
            self::STORAGE_COST_EXPENSE         => [FinancialAccount::TYPE_EXPENSE,   'R2 storage cost'],
            self::BANDWIDTH_COST_EXPENSE       => [FinancialAccount::TYPE_EXPENSE,   'CDN / bandwidth cost'],
            self::REFUND_EXPENSE               => [FinancialAccount::TYPE_EXPENSE,   'Refund expense'],
            self::PAYMENT_GATEWAY_FEE_EXPENSE  => [FinancialAccount::TYPE_EXPENSE,   'Payment gateway fees'],
            self::RETAINED_EARNINGS            => [FinancialAccount::TYPE_EQUITY,    'Retained earnings'],
        ];

        if (!isset($map[$code])) {
            throw new \InvalidArgumentException("Unknown platform account code: {$code}");
        }
        [$type, $name] = $map[$code];

        return [
            'account_type' => $type,
            'name'         => $name,
            'currency'     => $currency,
            'owner_type'   => 'platform',
            'owner_id'     => null,
            'is_active'    => true,
        ];
    }
}

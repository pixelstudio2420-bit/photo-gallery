<?php

namespace App\Finance\UseCases;

use App\Finance\Calculators\CommissionCalculator;
use App\Finance\Calculators\TaxCalculator;
use App\Finance\ChartOfAccounts;
use App\Finance\JournalDraft;
use App\Finance\LedgerService;
use App\Finance\Money;
use App\Finance\Models\JournalEntry;

/**
 * The canonical example use case — record a paid order in the
 * double-entry ledger.
 *
 * Money flow (the calculation engine, end-to-end)
 * -----------------------------------------------
 *   gross_paid_by_customer       (e.g. ฿1,000.00)
 *     = net_revenue + vat                         (Phase 6, inclusive VAT)
 *   net_revenue                  (e.g. ฿934.58)
 *     - commission_to_photographer                (Phase 5)
 *     = platform_take             (e.g. ฿186.92)
 *   platform_take
 *     - infra_cost (AI, R2, …)                    (Phase 7, recorded separately)
 *     = net_profit  (Phase 4 final line)
 *
 * Journal entries posted (one atomic group per order)
 * ---------------------------------------------------
 *   DR  gateway_receivable                        gross
 *      CR  commission_revenue                     platform_fee_excl_vat
 *      CR  photographer_payable[user]             photographer_net_excl_vat
 *      CR  vat_collected_payable                  vat
 *
 * ALL amounts in integer minor units (satang).
 */
final class RecordOrderPaid
{
    public function __construct(
        private readonly LedgerService        $ledger,
        private readonly ChartOfAccounts      $chart,
        private readonly CommissionCalculator $commission,
        private readonly TaxCalculator        $tax,
    ) {}

    /**
     * @param  string  $orderId             Stable identifier for idempotency.
     * @param  Money   $grossPaid           What the customer paid (VAT-inclusive when $vatBps > 0).
     * @param  int     $platformFeeBps      Of the NET (post-VAT) revenue.
     * @param  int     $vatBps              0 if the platform isn't VAT-registered.
     * @param  int     $photographerUserId
     * @param  string  $gatewayCode         e.g. 'OMISE_RECEIVABLE' from ChartOfAccounts
     * @param  array<string, mixed>  $metadata  Free-form trace context.
     */
    public function record(
        string $orderId,
        Money $grossPaid,
        int $platformFeeBps,
        int $vatBps,
        int $photographerUserId,
        string $gatewayCode,
        array $metadata = [],
    ): JournalEntry {
        // 1. Split gross into net + VAT (VAT-inclusive convention — matches Thai pricing).
        $taxSplit = $this->tax->inclusive($grossPaid, $vatBps);
        // After this:
        //   net   = revenue minus VAT
        //   tax   = VAT slice
        //   gross = $grossPaid (sanity)
        $net = $taxSplit['net'];
        $vat = $taxSplit['tax'];

        // 2. Split net into platform fee + photographer share.
        $split           = $this->commission->split($net, $platformFeeBps);
        $platformFee     = $split['platform_fee'];
        $photographerNet = $split['photographer_net'];

        // 3. Resolve the accounts we'll touch.
        $gateway          = $this->chart->platform($gatewayCode, $grossPaid->currency);
        $commissionAcct   = $this->chart->platform(ChartOfAccounts::COMMISSION_REVENUE, $grossPaid->currency);
        $photographerAcct = $this->chart->photographerPayable($photographerUserId, $grossPaid->currency);
        $vatAcct          = $this->chart->platform(ChartOfAccounts::VAT_COLLECTED_PAYABLE, $grossPaid->currency);

        // 4. Build a balanced draft.
        // DR side (asset increases): gateway receivable rises by the gross.
        // CR side: commission revenue + photographer payable + vat payable.
        // Sum must equal gross.
        $draft = (new JournalDraft(
            type:           'order.paid',
            idempotencyKey: "order.{$orderId}.paid",
            description:    "Order #{$orderId} paid via {$gatewayCode}",
            postedBy:       'system',
            metadata:       $metadata + [
                'order_id'           => $orderId,
                'platform_fee_bps'   => $platformFeeBps,
                'vat_bps'            => $vatBps,
                'photographer_user'  => $photographerUserId,
            ],
        ))
            ->debit ($gateway,          $grossPaid)
            ->credit($commissionAcct,   $platformFee)
            ->credit($photographerAcct, $photographerNet);

        // VAT line is conditional — don't pollute the ledger when not VAT-registered.
        if ($vat->isPositive()) {
            $draft->credit($vatAcct, $vat);
        }

        return $this->ledger->post($draft);
    }
}

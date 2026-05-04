<?php

namespace App\Services\Payment;

use App\Models\AppSetting;
use App\Models\BankAccount;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\PaymentTransaction;
use Illuminate\Support\Str;

class PaymentService
{
    // ----------------------------------------------------------------
    // Gateway factory
    // ----------------------------------------------------------------

    /**
     * Create a gateway instance by method_type string.
     */
    public static function createGateway(string $type): PaymentGatewayInterface
    {
        return match ($type) {
            'promptpay'     => new PromptPayGateway(),
            'bank_transfer' => new BankTransferGateway(),
            'stripe'        => new StripeGateway(),
            'omise'         => new OmiseGateway(),
            'paypal'        => new PayPalGateway(),
            'line_pay'      => new LinePayGateway(),
            'truemoney'     => new TrueMoneyGateway(),
            'two_c_two_p'   => new TwoCTwoPGateway(),
            'manual'        => new ManualGateway(),
            default         => throw new \InvalidArgumentException("Unknown payment gateway: {$type}"),
        };
    }

    // ----------------------------------------------------------------
    // Active gateways
    // ----------------------------------------------------------------

    /**
     * Map of method_type → AppSetting key that admin uses to enable/
     * disable that gateway. method_types not listed here are always
     * enabled (e.g. bank_transfer is gated by BankAccount rows
     * existing rather than a flag, and manual is the always-on
     * fallback).
     */
    private const ENABLED_FLAG_MAP = [
        'stripe'      => 'stripe_enabled',
        'omise'       => 'omise_enabled',
        'paypal'      => 'paypal_enabled',
        'line_pay'    => 'line_pay_enabled',
        'promptpay'   => 'promptpay_enabled',
        'truemoney'   => 'truemoney_enabled',
        'two_c_two_p' => '2c2p_enabled',
    ];

    /**
     * Is this gateway turned on by the admin AND configured (creds set)?
     *
     * Two independent checks, both must pass:
     *
     *   1. The admin toggle in /admin/settings/payment-gateways. We
     *      treat "no setting recorded yet" as ON for backward compat
     *      — installations that had this gateway working before the
     *      enabled-flag was respected shouldn't suddenly hide their
     *      configured method on first deploy of this fix.
     *
     *   2. The gateway's own isAvailable() — credentials/keys present.
     *
     * Both gates are necessary because the previous behaviour ignored
     * the toggle entirely: an admin could untick "Stripe Enabled" but
     * the secret key was still in AppSettings, so isAvailable() said
     * "yes" and the method showed up at checkout.
     */
    public static function isGatewayEnabled(string $methodType): bool
    {
        // Step 1 — admin toggle. Default '1' (enabled) when no row
        // exists so legacy configurations don't suddenly disappear.
        $flagKey = self::ENABLED_FLAG_MAP[$methodType] ?? null;
        if ($flagKey !== null) {
            if ((string) AppSetting::get($flagKey, '1') !== '1') {
                return false;
            }
        }

        // Step 2 — gateway-specific configuration check.
        try {
            return self::createGateway($methodType)->isAvailable();
        } catch (\InvalidArgumentException) {
            return false;
        }
    }

    /**
     * Return active payment methods from DB, enriched with gateway availability.
     *
     * @return \Illuminate\Support\Collection<PaymentMethod>
     */
    public static function getActiveGateways()
    {
        return PaymentMethod::active()->get()
            ->filter(fn (PaymentMethod $m) => self::isGatewayEnabled($m->method_type))
            ->values();
    }

    // ----------------------------------------------------------------
    // Process payment
    // ----------------------------------------------------------------

    /**
     * Route an order to the correct gateway and return the initiate() result.
     *
     * Server-side check on the enabled flag — without this, a user could
     * craft a POST to /payment/process with a method_type the admin has
     * disabled in the UI (e.g. Stripe is unticked) and still get a charge
     * link out of us. The client-side filter at /payment/checkout/{order}
     * is for UX; this is the actual gate.
     */
    public static function processPayment(Order $order, string $methodType, array $extras = []): array
    {
        if (!self::isGatewayEnabled($methodType)) {
            throw new \InvalidArgumentException("ช่องทาง '{$methodType}' ไม่เปิดใช้งานอยู่");
        }

        $gateway     = self::createGateway($methodType);
        $transaction = self::createTransaction($order, $methodType);

        $amount = $order->net_amount ?? $order->total_amount ?? $order->total;

        // Omise card-on-file path for subscription orders:
        // When Omise.js gave us a card token AND this is a subscription
        // order AND the buyer opted INTO auto-renewal (save_card=true,
        // controlled by the "บันทึกบัตรเพื่อต่ออายุอัตโนมัติ" checkbox
        // on the checkout page), create an Omise Customer FIRST so the
        // card is retained on Omise's side for the
        // `subscriptions:charge-pending` cron to use on subsequent
        // periods. The created customer's id is saved on the
        // PhotographerSubscription / UserStorageSubscription row.
        //
        // When the buyer UNCHECKED the box (save_card=false), we treat
        // the payment as a one-time charge: no customer is created,
        // the token is consumed by the single charge, and the next
        // period's renewal cron skips this sub (no omise_customer_id),
        // letting the period-end safety net expire it normally. This
        // matches the manual-renewal experience offered by PromptPay
        // and bank transfer for buyers who explicitly want
        // month-by-month / one-shot purchases without binding a card.
        $omiseToken = $extras['omise_token'] ?? null;
        $saveCard   = (bool) ($extras['save_card'] ?? false);
        $useCustomerForCharge = false;
        $customerId = null;

        $isSubscriptionOrder = $order->isSubscriptionOrder()
            || $order->order_type === Order::TYPE_USER_STORAGE_SUBSCRIPTION;

        if ($methodType === 'omise'
            && !empty($omiseToken)
            && $isSubscriptionOrder
            && $saveCard
        ) {
            try {
                $customerId = self::ensureOmiseCustomerForSubscriptionOrder($order, $omiseToken);
                $useCustomerForCharge = !empty($customerId);
            } catch (\Throwable $e) {
                Log::warning('PaymentService: Omise customer creation failed, falling back to one-shot token charge', [
                    'order_id' => $order->id,
                    'error'    => $e->getMessage(),
                ]);
                // Falls through — we still try to charge with the token
                // directly. User pays this period; future renewal will
                // require manual re-subscribe (no customer saved).
            }
        }

        $initiatePayload = [
            'amount'         => $amount,
            'description'    => "Order #{$order->order_number}",
            'transaction_id' => $transaction->transaction_id,
            'order_id'       => $order->id,
            'success_url'    => route('payment.success') . "?txn={$transaction->transaction_id}",
            'cancel_url'     => route('orders.show', $order->id),
        ];

        // For Omise: prefer customer (when we just saved one) over raw
        // token. Both still flow through OmiseGateway::initiate which
        // creates a charge — `customer` and `card` are mutually exclusive
        // in Omise's API but we always pass at most one.
        if ($methodType === 'omise') {
            if ($useCustomerForCharge && $customerId) {
                $initiatePayload['customer'] = $customerId;
            } elseif (!empty($omiseToken)) {
                $initiatePayload['token'] = $omiseToken;
            }
        }

        $result = $gateway->initiate($initiatePayload);

        return array_merge($result, ['transaction' => $transaction]);
    }

    /**
     * Create (or reuse) an Omise Customer for a subscription Order's
     * underlying subscription row, then persist `omise_customer_id` on
     * the subscription so future renewals can charge without prompting.
     *
     * Returns the customer_id, or null when the subscription / invoice
     * link couldn't be resolved (caller should fall back to one-shot
     * token charge in that case).
     *
     * Idempotent: if the subscription already has an `omise_customer_id`
     * we reuse it (consuming the token still — but the new card replaces
     * the old default on the customer if desired).
     */
    protected static function ensureOmiseCustomerForSubscriptionOrder(Order $order, string $cardToken): ?string
    {
        $sub = null;
        $userEmail = optional(\App\Models\User::find($order->user_id))->email;

        if ($order->isSubscriptionOrder() && $order->subscription_invoice_id) {
            $invoice = \App\Models\SubscriptionInvoice::find($order->subscription_invoice_id);
            if ($invoice) {
                $sub = \App\Models\PhotographerSubscription::find($invoice->subscription_id);
            }
        } elseif ($order->order_type === Order::TYPE_USER_STORAGE_SUBSCRIPTION
                  && $order->user_storage_invoice_id) {
            $invoice = \App\Models\UserStorageInvoice::find($order->user_storage_invoice_id);
            if ($invoice) {
                $sub = \App\Models\UserStorageSubscription::find($invoice->subscription_id);
            }
        }

        if (!$sub) return null;

        // Reuse existing customer if already saved. Skipping the
        // customers.create call avoids consuming the new token, but we
        // can't add the new card to the existing customer either —
        // user just continues paying with whichever card is on file.
        if (!empty($sub->omise_customer_id)) {
            return $sub->omise_customer_id;
        }

        $gateway = app(\App\Services\Payment\OmiseGateway::class);
        $resp = $gateway->createCustomer(
            $cardToken,
            "Subscription: order_id={$order->id} user_id={$order->user_id}",
            $userEmail,
            [
                'order_id'        => (string) $order->id,
                'user_id'         => (string) $order->user_id,
                'subscription_id' => (string) $sub->id,
            ]
        );

        if (($resp['object'] ?? '') !== 'customer' || empty($resp['id'])) {
            $errMsg = $resp['message'] ?? $resp['failure_message'] ?? 'unknown';
            throw new \RuntimeException("Omise customers.create failed: {$errMsg}");
        }

        $sub->update(['omise_customer_id' => $resp['id']]);
        return $resp['id'];
    }

    // ----------------------------------------------------------------
    // Transaction helpers
    // ----------------------------------------------------------------

    /**
     * Create a payment_transactions record (status = pending).
     */
    public static function createTransaction(Order $order, string $gateway, ?int $methodId = null): PaymentTransaction
    {
        // Try to resolve methodId from gateway type if not provided
        if ($methodId === null) {
            $method    = PaymentMethod::where('method_type', $gateway)->first();
            $methodId  = $method?->id;
        }

        return PaymentTransaction::create([
            'transaction_id'    => 'TXN-' . strtoupper(Str::random(16)),
            'order_id'          => $order->id,
            'user_id'           => $order->user_id,
            'payment_method_id' => $methodId,
            'payment_gateway'   => $gateway,
            'amount'            => $order->net_amount ?? $order->total_amount ?? $order->total,
            'currency'          => 'THB',
            'status'            => 'pending',
        ]);
    }

    /**
     * Mark a transaction (and its order) as completed.
     */
    public static function completeTransaction(PaymentTransaction $transaction, ?string $gatewayTxnId = null): void
    {
        $transaction->update([
            'status'                 => 'completed',
            'paid_at'                => now(),
            'gateway_transaction_id' => $gatewayTxnId,
        ]);

        $transaction->order->update(['status' => 'paid']);
    }

    /**
     * Mark a transaction as failed.
     */
    public static function failTransaction(PaymentTransaction $transaction, ?string $reason = null): void
    {
        $transaction->update([
            'status'   => 'failed',
            'metadata' => array_merge($transaction->metadata ?? [], ['failure_reason' => $reason]),
        ]);
    }

    // ----------------------------------------------------------------
    // Convenience loaders for views
    // ----------------------------------------------------------------

    /**
     * Get the PromptPay number from app_settings (or null).
     */
    public static function getPromptPayNumber(): ?string
    {
        $number = AppSetting::get('promptpay_number', '');
        return !empty($number) ? $number : null;
    }

    /**
     * Load active bank accounts ordered by sort_order.
     */
    public static function getBankAccounts()
    {
        return BankAccount::active()->get();
    }

    /**
     * Icon map for known method_type values (Bootstrap Icons).
     */
    public static function getMethodIcon(string $type): string
    {
        return match ($type) {
            'promptpay'     => 'bi-qr-code',
            'bank_transfer' => 'bi-bank',
            'stripe'        => 'bi-credit-card-2-front',
            'omise'         => 'bi-credit-card',
            'paypal'        => 'bi-paypal',
            'line_pay'      => 'bi-chat-dots',
            'truemoney'     => 'bi-wallet2',
            'two_c_two_p'   => 'bi-shield-check',
            'manual'        => 'bi-person-check',
            default         => 'bi-cash-coin',
        };
    }
}

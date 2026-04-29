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
    public static function processPayment(Order $order, string $methodType): array
    {
        if (!self::isGatewayEnabled($methodType)) {
            throw new \InvalidArgumentException("ช่องทาง '{$methodType}' ไม่เปิดใช้งานอยู่");
        }

        $gateway     = self::createGateway($methodType);
        $transaction = self::createTransaction($order, $methodType);

        $result = $gateway->initiate([
            'amount'         => $order->net_amount ?? $order->total_amount ?? $order->total,
            'description'    => "Order #{$order->order_number}",
            'transaction_id' => $transaction->transaction_id,
            'order_id'       => $order->id,
            'success_url'    => route('payment.success') . "?txn={$transaction->transaction_id}",
            'cancel_url'     => route('orders.show', $order->id),
        ]);

        return array_merge($result, ['transaction' => $transaction]);
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

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
     * Return active payment methods from DB, enriched with gateway availability.
     *
     * @return \Illuminate\Support\Collection<PaymentMethod>
     */
    public static function getActiveGateways()
    {
        $methods = PaymentMethod::active()->get();

        return $methods->filter(function (PaymentMethod $method) {
            try {
                $gateway = self::createGateway($method->method_type);
                return $gateway->isAvailable();
            } catch (\InvalidArgumentException) {
                return false;
            }
        })->values();
    }

    // ----------------------------------------------------------------
    // Process payment
    // ----------------------------------------------------------------

    /**
     * Route an order to the correct gateway and return the initiate() result.
     */
    public static function processPayment(Order $order, string $methodType): array
    {
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

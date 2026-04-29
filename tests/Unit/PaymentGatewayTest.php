<?php

namespace Tests\Unit;

use App\Services\Payment\PaymentGatewayInterface;
use App\Services\Payment\StripeGateway;
use App\Services\Payment\PayPalGateway;
use App\Services\Payment\LinePayGateway;
use App\Services\Payment\TrueMoneyGateway;
use Tests\TestCase;

class PaymentGatewayTest extends TestCase
{
    // ─── Interface Implementation Tests ───

    public function test_stripe_gateway_implements_interface(): void
    {
        $gateway = new StripeGateway();

        $this->assertInstanceOf(PaymentGatewayInterface::class, $gateway);
        $this->assertEquals('stripe', $gateway->getMethodType());
    }

    public function test_paypal_gateway_implements_interface(): void
    {
        $gateway = new PayPalGateway();

        $this->assertInstanceOf(PaymentGatewayInterface::class, $gateway);
        $this->assertEquals('paypal', $gateway->getMethodType());
    }

    public function test_line_pay_gateway_implements_interface(): void
    {
        $gateway = new LinePayGateway();

        $this->assertInstanceOf(PaymentGatewayInterface::class, $gateway);
        $this->assertEquals('line_pay', $gateway->getMethodType());
    }

    public function test_truemoney_gateway_implements_interface(): void
    {
        $gateway = new TrueMoneyGateway();

        $this->assertInstanceOf(PaymentGatewayInterface::class, $gateway);
        $this->assertEquals('truemoney', $gateway->getMethodType());
    }

    // ─── Availability Without Credentials ───

    public function test_gateway_is_not_available_without_credentials(): void
    {
        // In a test environment with no real API keys configured, all gateways
        // should report as unavailable.
        $gateways = [
            new StripeGateway(),
            new PayPalGateway(),
            new LinePayGateway(),
            new TrueMoneyGateway(),
        ];

        foreach ($gateways as $gateway) {
            $this->assertFalse(
                $gateway->isAvailable(),
                sprintf('%s should not be available without credentials', $gateway->getName())
            );
        }
    }
}

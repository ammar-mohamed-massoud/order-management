<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Payments\PaymentGatewayManager;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PaymentGatewayManagerTest extends TestCase
{
    public function test_manager_selects_the_configured_gateway_strategy(): void
    {
        $result = app(PaymentGatewayManager::class)->process(
            'credit_card',
            new Order(['total_amount' => '12.00']),
        );

        $this->assertSame('successful', $result->status);
        $this->assertStringStartsWith('cc_', $result->transactionReference);
    }

    public function test_manager_rejects_unconfigured_gateway(): void
    {
        $this->expectException(ValidationException::class);

        app(PaymentGatewayManager::class)->process(
            'wire_transfer',
            new Order(['total_amount' => '12.00']),
        );
    }
}

<?php

namespace App\Payments;

use App\Models\Order;
use Illuminate\Validation\ValidationException;
use LogicException;

class PaymentGatewayManager
{
    public function process(string $method, Order $order): PaymentGatewayResult
    {
        $configuration = config("payment.gateways.{$method}");

        if (! is_array($configuration) || ! isset($configuration['driver'])) {
            throw ValidationException::withMessages([
                'payment_method' => ['The selected payment method is not supported.'],
            ]);
        }

        $gateway = app($configuration['driver']);

        if (! $gateway instanceof PaymentGateway) {
            throw new LogicException("Payment gateway [{$configuration['driver']}] must implement PaymentGateway.");
        }

        $result = $gateway->process($order, $configuration['credentials'] ?? []);

        if (! in_array($result->status, ['successful', 'failed'], true)) {
            throw new LogicException('Payment gateway returned an invalid payment status.');
        }

        return $result;
    }
}

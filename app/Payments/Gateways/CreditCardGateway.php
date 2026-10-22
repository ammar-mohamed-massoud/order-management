<?php

namespace App\Payments\Gateways;

use App\Models\Order;
use App\Payments\PaymentGateway;
use App\Payments\PaymentGatewayResult;
use Illuminate\Support\Str;

class CreditCardGateway implements PaymentGateway
{
    public function process(Order $order, array $credentials): PaymentGatewayResult
    {
        return new PaymentGatewayResult('successful', 'cc_'.Str::uuid());
    }
}

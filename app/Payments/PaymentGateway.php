<?php

namespace App\Payments;

use App\Models\Order;

interface PaymentGateway
{
    /**
     * @param  array<string, mixed>  $credentials
     */
    public function process(Order $order, array $credentials): PaymentGatewayResult;
}

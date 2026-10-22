<?php

namespace App\Payments;

final readonly class PaymentGatewayResult
{
    public function __construct(
        public string $status,
        public string $transactionReference,
    ) {}
}

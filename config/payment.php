<?php

use App\Payments\Gateways\CreditCardGateway;
use App\Payments\Gateways\PayPalGateway;

return [
    'gateways' => [
        'credit_card' => [
            'driver' => CreditCardGateway::class,
            'credentials' => [
                'api_key' => env('CREDIT_CARD_GATEWAY_API_KEY'),
            ],
        ],
        'paypal' => [
            'driver' => PayPalGateway::class,
            'credentials' => [
                'client_id' => env('PAYPAL_CLIENT_ID'),
                'client_secret' => env('PAYPAL_CLIENT_SECRET'),
            ],
        ],
    ],
];

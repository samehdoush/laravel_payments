<?php

// config for Samehdoush/LaravelPayments
return [
    'tables' => [


        'webhook_history' => 'webhookhistory',
        'gateway_products' => 'gateway_products',
        'old_gateway_products' => 'old_gateway_products',
    ],

    // Subscriptions Models
    'models' => [

        'webhook_history' => \Samehdoush\Subscriptions\Models\WebhookHistory::class,
        'gateway_products' => \Samehdoush\Subscriptions\Models\GatewayProducts::class,
        'old_gateway_products' => \Samehdoush\Subscriptions\Models\OldGatewayProducts::class,

    ],
    'paypal' => [
        'enable' => true,
        'mode' => 'sandbox', // 'sandbox' or 'live'
        'client_id' => env('PAYPAL_CLIENT_ID', ''),
        'secret' => env('PAYPAL_SECRET', ''),
        'app_id' => env('PAYPAL_APP_ID', ''),
        'currency' => env('PAYPAL_CURRENCY', 'USD'),
    ],
    'stripe' => [
        'enable' => true,
        'mode' => 'sandbox', // 'sandbox' or 'live'
        'secret' => env('STRIPE_SECRET', ''),
        'publishable' => env('STRIPE_PUBLISHABLE', ''),
        'currency' => env('STRIPE_CURRENCY', 'USD'),
    ],
];

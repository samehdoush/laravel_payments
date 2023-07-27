<?php

// config for Samehdoush/LaravelPayments
return [
    // Tables name
    'tables' => [

        'webhook_history' => 'webhookhistory',
        'gateway_products' => 'gateway_products',
        'old_gateway_products' => 'old_gateway_products',
        'orders' => 'orders',
    ],

    //  Models
    'models' => [

        'webhook_history' => \Samehdoush\LaravelPayments\Models\WebhookHistory::class,
        'gateway_products' => \Samehdoush\LaravelPayments\Models\GatewayProducts::class,
        'old_gateway_products' => \Samehdoush\LaravelPayments\Models\OldGatewayProducts::class,
        'order' => \Samehdoush\LaravelPayments\Models\Order::class,
        // if Samehdoush\Subscriptions package is installed
        'plan' =>  \Samehdoush\Subscriptions\Models\Plan::class,

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

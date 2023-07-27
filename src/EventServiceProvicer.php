<?php

namespace Samehdoush\LaravelPayments;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Samehdoush\LaravelPayments\Events\PaypalWebhookEvent;
use Samehdoush\LaravelPayments\Listeners\PaypalWebhookListener;

class EventServiceProvider extends ServiceProvider
{

    protected $listen = [
        PaypalWebhookEvent::class => [
            PaypalWebhookListener::class
        ]

    ];

    public function boot()
    {
        parent::boot();
    }
}

<?php

namespace Samehdoush\LaravelPayments;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use App\Events\PaypalWebhookEvent;
use App\Listeners\PaypalWebhookListener;

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

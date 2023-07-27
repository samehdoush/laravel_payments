<?php

namespace Samehdoush\LaravelPayments;


use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Samehdoush\LaravelPayments\Commands\LaravelPaymentsCommand;
use Samehdoush\LaravelPayments\EventServiceProvider as LaravelPaymentsEventServiceProvider;

class LaravelPaymentsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('laravel-payments')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_laravel-payments_table')
            ->hasCommand(LaravelPaymentsCommand::class);
    }

    // public function boot()
    // {
    //     parent::boot();
    //     \Illuminate\Support\Facades\Event::listen(
    //         PaypalWebhookEvent::class,
    //         PaypalWebhookListener::class
    //     );
    // }

    public function registeringPackage()
    {
        $this->app->register(LaravelPaymentsEventServiceProvider::class);
    }
}

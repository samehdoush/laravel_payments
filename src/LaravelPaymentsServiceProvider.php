<?php

namespace Samehdoush\LaravelPayments;


use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Samehdoush\LaravelPayments\Commands\LaravelPaymentsCommand;
use Samehdoush\LaravelPayments\EventServiceProvider as LaravelPaymentsEventServiceProvider;
use Spatie\LaravelPackageTools\Commands\InstallCommand;

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
            ->hasRoute('webhooks')
            // ->hasViews()
            ->hasMigrations(['2023_03_01_134013_create_orders_table', '2023_07_21_124125_create_gatewayproducts_table', '2023_07_21_134503_create_oldgatewayproducts_table', '2023_07_21_141415_create_webhookhistory_table'])
            // ->hasCommand(LaravelPaymentsCommand::class)
            ->hasInstallCommand(function (InstallCommand $command) {
                $command
                    ->startWith(function (InstallCommand $command) {
                        $command->info('Hello, and welcome to my great new package!');
                    })
                    ->publishConfigFile()
                    ->publishMigrations()
                    ->askToRunMigrations()
                    // ->copyAndRegisterServiceProviderInApp()
                    ->askToStarRepoOnGitHub('samehdoush/laravel-payments')
                    ->endWith(function (InstallCommand $command) {
                        $command->info('Have a great day!');
                    });
            });
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

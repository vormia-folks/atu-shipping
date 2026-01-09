<?php

namespace Vormia\ATUShipping;

use Vormia\ATUShipping\ATUShipping;
use Vormia\ATUShipping\Console\Commands\ATUShippingHelpCommand;
use Vormia\ATUShipping\Console\Commands\ATUShippingInstallCommand;
use Vormia\ATUShipping\Console\Commands\ATUShippingUninstallCommand;
use Vormia\ATUShipping\Console\Commands\ATUShippingUpdateCommand;
use Vormia\ATUShipping\Support\FeeCalculator;
use Vormia\ATUShipping\Support\Installer;
use Vormia\ATUShipping\Support\RuleEvaluator;
use Vormia\ATUShipping\Support\ShippingService;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\ServiceProvider;

class ATUShippingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register version instance
        $this->app->instance('atushipping.version', ATUShipping::VERSION);

        // Register Installer as singleton
        $this->app->singleton(Installer::class, function (Application $app) {
            return new Installer(
                new Filesystem(),
                ATUShipping::stubsPath(),
                $app->basePath()
            );
        });

        // Register RuleEvaluator as singleton
        $this->app->singleton(RuleEvaluator::class);

        // Register FeeCalculator as singleton
        $this->app->singleton(FeeCalculator::class);

        // Register ShippingService as singleton
        $this->app->singleton(ShippingService::class, function (Application $app) {
            return new ShippingService(
                $app->make(RuleEvaluator::class),
                $app->make(FeeCalculator::class)
            );
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                ATUShippingInstallCommand::class,
                ATUShippingUpdateCommand::class,
                ATUShippingUninstallCommand::class,
                ATUShippingHelpCommand::class,
            ]);
        }
    }
}

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
        $this->app->instance('atushipping.version', ATUShipping::VERSION);

        // Merge the package config so config('atu-shipping.*') works even before
        // the user runs atushipping:install (standard Laravel package convention).
        $this->mergeConfigFrom(
            ATUShipping::stubsPath('config/atu-shipping.php'),
            'atu-shipping'
        );

        $this->app->singleton(Installer::class, function (Application $app) {
            return new Installer(
                new Filesystem(),
                ATUShipping::stubsPath(),
                $app->basePath()
            );
        });

        $this->app->singleton(RuleEvaluator::class);
        $this->app->singleton(FeeCalculator::class);

        $this->app->singleton(ShippingService::class, function (Application $app) {
            return new ShippingService(
                $app->make(RuleEvaluator::class),
                $app->make(FeeCalculator::class)
            );
        });
    }

    public function boot(): void
    {
        // Load migrations from the package itself; no need to copy them into
        // the host app's database/migrations folder.
        $this->loadMigrationsFrom(ATUShipping::stubsPath('migrations'));

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

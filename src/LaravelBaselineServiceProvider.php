<?php

namespace Limenet\LaravelBaseline;

use Limenet\LaravelBaseline\Commands\CheckCommand;
use Limenet\LaravelBaseline\Commands\PeriodicCheckCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelBaselineServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('laravel-baseline')
            ->hasConfigFile()
            ->hasCommands([
                CheckCommand::class,
                PeriodicCheckCommand::class,
            ]);
    }
}

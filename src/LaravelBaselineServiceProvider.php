<?php

namespace Limenet\LaravelBaseline;

use Limenet\LaravelBaseline\Commands\LaravelBaselineCommand;
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
            ->hasCommand(LaravelBaselineCommand::class);
    }
}

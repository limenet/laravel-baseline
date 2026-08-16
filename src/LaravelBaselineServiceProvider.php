<?php

namespace Limenet\LaravelBaseline;

use Limenet\LaravelBaseline\Commands\CheckCommand;
use Limenet\LaravelBaseline\Commands\PeriodicCheckCommand;
use Limenet\LaravelBaseline\Policy\Policy;
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

    public function packageRegistered(): void
    {
        // Singleton so the shipped policy.json is read once per run; tests swap
        // in a Policy::fromArray() instance via app()->instance().
        $this->app->singleton(Policy::class, static fn (): Policy => Policy::fromDirectory());
    }
}

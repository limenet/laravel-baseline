<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractPeriodicCheck;

class RunsBoostUpdateCheck extends AbstractPeriodicCheck
{
    public function isApplicable(): bool
    {
        return $this->checkComposerPackages('laravel/boost');
    }

    public function promptDescription(): string
    {
        return "Run 'ddev artisan boost:update --discover' to keep Boost definitions up to date.";
    }
}

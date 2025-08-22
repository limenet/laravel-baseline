<?php

namespace Limenet\LaravelBaseline\Tests;

use Limenet\LaravelBaseline\LaravelBaselineServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [
            LaravelBaselineServiceProvider::class,
        ];
    }
}

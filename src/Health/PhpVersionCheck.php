<?php

namespace Limenet\LaravelBaseline\Health;

use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;

class PhpVersionCheck extends Check
{
    public function run(): Result
    {
        $version = PHP_MAJOR_VERSION * 100 + PHP_MINOR_VERSION;

        if ($version >= 804) {
            return Result::make()->ok(sprintf('PHP %s.%s is supported', PHP_MAJOR_VERSION, PHP_MINOR_VERSION));
        }

        if ($version === 803) {
            return Result::make()->warning(sprintf('PHP %s.%s is supported but 8.4+ is recommended', PHP_MAJOR_VERSION, PHP_MINOR_VERSION));
        }

        return Result::make()->failed(sprintf('PHP %s.%s is not supported, upgrade to 8.3+', PHP_MAJOR_VERSION, PHP_MINOR_VERSION));
    }
}

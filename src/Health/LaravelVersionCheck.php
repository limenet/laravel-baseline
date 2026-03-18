<?php

namespace Limenet\LaravelBaseline\Health;

use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;

class LaravelVersionCheck extends Check
{
    public function run(): Result
    {
        $major = (int) str(app()->version())->before('.')->toString();

        if ($major >= 12) {
            return Result::make()->ok(sprintf('Laravel %s is supported', app()->version()));
        }

        if ($major === 11) {
            return Result::make()->warning(sprintf('Laravel %s is supported but 12+ is recommended', app()->version()));
        }

        return Result::make()->failed(sprintf('Laravel %s is not supported, upgrade to 11+', app()->version()));
    }
}

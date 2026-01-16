<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

class UsesSpatieHealthCheck extends AbstractCheck
{
    public function check(): CheckResult
    {
        return $this->checkPackageWithSchedule(
            'spatie/laravel-health',
            ['health:check', 'health:schedule-check-heartbeat'],
        );
    }
}

<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

class UsesSpatieHealthHasReleaseAgeCheckCheck extends AbstractUsesSpatieHealthChecksCheck
{
    protected function requiredHealthCheckClasses(): array
    {
        return ['ReleaseAgeCheck'];
    }

    protected function healthCheckFqns(): array
    {
        return [
            'Spatie\\Health\\Facades\\Health',
            'Limenet\\LaravelBaseline\\Health\\ReleaseAgeCheck',
        ];
    }
}

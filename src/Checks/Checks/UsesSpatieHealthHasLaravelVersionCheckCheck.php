<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

class UsesSpatieHealthHasLaravelVersionCheckCheck extends AbstractUsesSpatieHealthChecksCheck
{
    protected function requiredHealthCheckClasses(): array
    {
        return ['LaravelVersionCheck'];
    }

    protected function healthCheckFqns(): array
    {
        return [
            'Spatie\\Health\\Facades\\Health',
            'Limenet\\LaravelBaseline\\Health\\LaravelVersionCheck',
        ];
    }
}

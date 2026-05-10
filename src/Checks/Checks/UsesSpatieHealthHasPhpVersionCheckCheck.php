<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

class UsesSpatieHealthHasPhpVersionCheckCheck extends AbstractUsesSpatieHealthChecksCheck
{
    protected function requiredHealthCheckClasses(): array
    {
        return ['PhpVersionCheck'];
    }

    protected function healthCheckFqns(): array
    {
        return [
            'Spatie\\Health\\Facades\\Health',
            'Limenet\\LaravelBaseline\\Health\\PhpVersionCheck',
        ];
    }
}

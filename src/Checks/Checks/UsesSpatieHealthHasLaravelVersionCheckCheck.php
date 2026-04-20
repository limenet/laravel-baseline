<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

class UsesSpatieHealthHasLaravelVersionCheckCheck extends AbstractUsesSpatieHealthChecksCheck
{
    protected function requiredHealthCheckClasses(): array
    {
        return ['LaravelVersionCheck'];
    }
}

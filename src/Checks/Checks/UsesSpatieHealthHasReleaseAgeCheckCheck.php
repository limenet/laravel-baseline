<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

class UsesSpatieHealthHasReleaseAgeCheckCheck extends AbstractUsesSpatieHealthChecksCheck
{
    protected function requiredHealthCheckClasses(): array
    {
        return ['ReleaseAgeCheck'];
    }
}

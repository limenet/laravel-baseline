<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

class UsesSpatieHealthHasPhpVersionCheckCheck extends AbstractUsesSpatieHealthChecksCheck
{
    protected function requiredHealthCheckClasses(): array
    {
        return ['PhpVersionCheck'];
    }
}

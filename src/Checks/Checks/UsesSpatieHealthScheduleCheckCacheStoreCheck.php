<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

class UsesSpatieHealthScheduleCheckCacheStoreCheck extends AbstractUsesSpatieHealthCheckCacheStoreCheck
{
    protected function healthCheckClassName(): string
    {
        return 'ScheduleCheck';
    }
}

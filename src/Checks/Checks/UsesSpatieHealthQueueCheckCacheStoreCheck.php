<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

class UsesSpatieHealthQueueCheckCacheStoreCheck extends AbstractUsesSpatieHealthCheckCacheStoreCheck
{
    protected function healthCheckClassName(): string
    {
        return 'QueueCheck';
    }
}

<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

class UsesSpatieHealthCacheCheckCacheStoreCheck extends AbstractUsesSpatieHealthCheckCacheStoreCheck
{
    protected function healthCheckClassName(): string
    {
        return 'CacheCheck';
    }

    protected function cacheStoreMethod(): string
    {
        return 'driver';
    }
}

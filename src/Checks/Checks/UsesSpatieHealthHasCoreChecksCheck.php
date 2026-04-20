<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

class UsesSpatieHealthHasCoreChecksCheck extends AbstractUsesSpatieHealthChecksCheck
{
    protected function requiredHealthCheckClasses(): array
    {
        return [
            'CacheCheck',
            'CpuLoadCheck',
            'DatabaseCheck',
            'DebugModeCheck',
            'EnvironmentCheck',
            'HorizonCheck',
            'RedisCheck',
            'ScheduleCheck',
            'UsedDiskSpaceCheck',
        ];
    }

    protected function requiredComposerPackages(): array
    {
        return ['spatie/laravel-health', 'spatie/cpu-load-health-check'];
    }
}

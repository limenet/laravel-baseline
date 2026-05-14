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
            'DatabaseConnectionCountCheck',
            'DebugModeCheck',
            'EnvironmentCheck',
            'HorizonCheck',
            'QueueCheck',
            'RedisCheck',
            'ScheduleCheck',
            'UsedDiskSpaceCheck',
        ];
    }

    protected function requiredComposerPackages(): array
    {
        return ['spatie/laravel-health', 'spatie/cpu-load-health-check', 'doctrine/dbal'];
    }

    protected function healthCheckFqns(): array
    {
        return [
            'Spatie\\Health\\Facades\\Health',
            'Spatie\\Health\\Checks\\Checks\\CacheCheck',
            'Spatie\\CpuLoadHealthCheck\\CpuLoadCheck',
            'Spatie\\Health\\Checks\\Checks\\DatabaseCheck',
            'Spatie\\Health\\Checks\\Checks\\DatabaseConnectionCountCheck',
            'Spatie\\Health\\Checks\\Checks\\DebugModeCheck',
            'Spatie\\Health\\Checks\\Checks\\EnvironmentCheck',
            'Spatie\\Health\\Checks\\Checks\\HorizonCheck',
            'Spatie\\Health\\Checks\\Checks\\QueueCheck',
            'Spatie\\Health\\Checks\\Checks\\RedisCheck',
            'Spatie\\Health\\Checks\\Checks\\ScheduleCheck',
            'Spatie\\Health\\Checks\\Checks\\UsedDiskSpaceCheck',
        ];
    }
}

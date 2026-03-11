<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

class UsesSpatieHealthCheck extends AbstractCheck
{
    public function check(): CheckResult
    {
        if (!$this->checkComposerPackages(['spatie/laravel-health', 'spatie/cpu-load-health-check'])) {
            $this->addComment('Missing packages: Install spatie/laravel-health and spatie/cpu-load-health-check');

            return CheckResult::FAIL;
        }

        if (!$this->hasScheduleEntry('health:check')) {
            $this->addComment('Missing schedule: Add RunHealthChecksCommand::class scheduled everyThirtyMinutes() in your scheduler');

            return CheckResult::FAIL;
        }

        if (!$this->hasScheduleEntry('health:schedule-check-heartbeat')) {
            $this->addComment('Missing schedule: Add ScheduleCheckHeartbeatCommand::class scheduled everyMinute() in your scheduler');

            return CheckResult::FAIL;
        }

        if (!$this->hasHealthChecksRegistered()) {
            $this->addComment('Health checks not registered: Add Health::checks([CacheCheck, CpuLoadCheck, DatabaseCheck, DebugModeCheck, EnvironmentCheck, HorizonCheck, RedisCheck, ScheduleCheck, UsedDiskSpaceCheck]) in AppServiceProvider');

            return CheckResult::FAIL;
        }

        if (!$this->hasS3HealthDisk()) {
            $this->addComment('Missing s3_health disk: Add s3_health disk definition to config/filesystems.php');

            return CheckResult::FAIL;
        }

        if (!$this->hasHealthResultStoreConfig()) {
            $this->addComment('Missing health result store: Configure JsonFileHealthResultStore with disk s3_health and path health.json in config/health.php');

            return CheckResult::FAIL;
        }

        return CheckResult::PASS;
    }

    private function hasHealthChecksRegistered(): bool
    {
        $file = base_path('app/Providers/AppServiceProvider.php');

        if (!file_exists($file)) {
            return false;
        }

        $content = file_get_contents($file) ?: '';
        $required = [
            'Health::checks(',
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

        foreach ($required as $token) {
            if (!str_contains($content, $token)) {
                return false;
            }
        }

        return true;
    }

    private function hasS3HealthDisk(): bool
    {
        $file = base_path('config/filesystems.php');

        if (!file_exists($file)) {
            return false;
        }

        return str_contains(file_get_contents($file) ?: '', 's3_health');
    }

    private function hasHealthResultStoreConfig(): bool
    {
        $file = base_path('config/health.php');

        if (!file_exists($file)) {
            return false;
        }

        $content = file_get_contents($file) ?: '';

        return str_contains($content, 'JsonFileHealthResultStore')
            && str_contains($content, 's3_health')
            && str_contains($content, 'health.json');
    }
}

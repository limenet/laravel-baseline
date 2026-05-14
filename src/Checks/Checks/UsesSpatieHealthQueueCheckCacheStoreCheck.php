<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Enums\CheckResult;

class UsesSpatieHealthQueueCheckCacheStoreCheck extends AbstractUsesSpatieHealthCheckCacheStoreCheck
{
    protected function healthCheckClassName(): string
    {
        return 'QueueCheck';
    }

    public function check(): CheckResult
    {
        if (!$this->checkComposerPackages('spatie/laravel-health')) {
            return CheckResult::WARN;
        }

        if (!$this->hasScheduleEntry('health:queue-check-heartbeat')) {
            $this->addComment('Missing schedule: add DispatchQueueCheckJobsCommand::class scheduled everyMinute() in your scheduler');

            return CheckResult::FAIL;
        }

        if (!$this->checkUsesCacheStore('QueueCheck')) {
            $this->addComment("QueueCheck must use the dedicated cache store: change QueueCheck::new() to QueueCheck::new()->useCacheStore('health-checks') in AppServiceProvider");

            return CheckResult::FAIL;
        }

        if (!$this->hasHealthChecksCacheStore()) {
            $this->addComment("Missing health-checks cache store: add a 'health-checks' entry with driver 'file' under 'stores' in config/cache.php");

            return CheckResult::FAIL;
        }

        return CheckResult::PASS;
    }
}

<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Enums\CheckResult;

class UsesSpatieHealthQueueCheckCacheStoreCheck extends AbstractUsesSpatieHealthCheckCacheStoreCheck
{
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

        if (!$this->hasHealthChecksCacheStorePath()) {
            $this->addComment("Incorrect path in health-checks cache store in config/cache.php: set 'path' to storage_path('...')");

            return CheckResult::FAIL;
        }

        if (!$this->hasHealthChecksCacheStoreGitignore()) {
            $this->addComment("Missing or invalid .gitignore at the health-checks cache store path: create the file with '*' on the first line and '!.gitignore' on the second");

            return CheckResult::FAIL;
        }

        return CheckResult::PASS;
    }

    protected function healthCheckClassName(): string
    {
        return 'QueueCheck';
    }
}

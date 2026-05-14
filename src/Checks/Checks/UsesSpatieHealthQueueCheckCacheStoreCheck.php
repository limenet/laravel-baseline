<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Enums\CheckResult;

class UsesSpatieHealthQueueCheckCacheStoreCheck extends AbstractUsesSpatieHealthCheckCacheStoreCheck
{
    protected function healthCheckClassName(): string
    {
        return 'QueueCheck';
    }

    protected function extraChecks(): ?CheckResult
    {
        if (!$this->hasScheduleEntry('health:queue-check-heartbeat')) {
            $this->addComment('Missing schedule: add DispatchQueueCheckJobsCommand::class scheduled everyMinute() in your scheduler');

            return CheckResult::FAIL;
        }

        return null;
    }
}

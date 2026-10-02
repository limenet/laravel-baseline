<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractCiJobCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;
use Limenet\LaravelBaseline\Project\Profile;

class HasCiJobsCheck extends AbstractCiJobCheck
{
    public static function profiles(): array
    {
        return Profile::cases();
    }

    public function check(): CheckResult
    {
        return $this->checkRequiredCiJobs();
    }

    protected function requiredCiJobs(): array
    {
        return $this->policy()->stringListMap('ci.requiredJobs.'.$this->profile()->value);
    }
}

<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractCiJobCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

class HasCiJobsCheck extends AbstractCiJobCheck
{
    public function check(): CheckResult
    {
        return $this->checkRequiredCiJobs();
    }

    protected function requiredCiJobs(): array
    {
        return [
            'build' => '.build',
            'php' => '.lint_php',
            'js' => '.lint_js',
            'test' => '.test',
        ];
    }
}

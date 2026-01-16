<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

class HasCiJobsCheck extends AbstractCheck
{
    public function check(): CheckResult
    {
        $data = $this->getGitlabCiData();
        $jobs = [
            'build' => '.build',
            'php' => '.lint_php',
            'js' => '.lint_js',
            'test' => '.test',
        ];
        foreach ($jobs as $jobName => $extends) {
            if (!isset($data[$jobName]['extends']) || $data[$jobName]['extends'] !== [$extends]) {
                $this->addComment("Missing or misconfigured CI job in .gitlab-ci.yml: Add job '$jobName' with 'extends: [$extends]'");

                return CheckResult::FAIL;
            }
        }

        return CheckResult::PASS;
    }
}

<?php

namespace Limenet\LaravelBaseline\Checks;

use Limenet\LaravelBaseline\Enums\CheckResult;

abstract class AbstractCiJobCheck extends AbstractCheck
{
    /**
     * @return array<string, string> jobName => extends template
     */
    abstract protected function requiredCiJobs(): array;

    protected function checkRequiredCiJobs(): CheckResult
    {
        $data = $this->getGitlabCiData();

        if ($data === null) {
            return CheckResult::FAIL;
        }

        foreach ($this->requiredCiJobs() as $jobName => $extends) {
            if (!isset($data[$jobName]['extends']) || $data[$jobName]['extends'] !== [$extends]) {
                $this->addComment("Missing or misconfigured CI job in .gitlab-ci.yml: Add job '$jobName' with 'extends: [$extends]'");

                return CheckResult::FAIL;
            }
        }

        return CheckResult::PASS;
    }
}

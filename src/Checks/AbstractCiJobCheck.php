<?php

namespace Limenet\LaravelBaseline\Checks;

use Limenet\LaravelBaseline\Enums\CheckResult;

abstract class AbstractCiJobCheck extends AbstractCheck
{
    /**
     * @return array<string, string|list<string>> jobName => extends template (or a list of accepted templates)
     */
    abstract protected function requiredCiJobs(): array;

    protected function checkRequiredCiJobs(): CheckResult
    {
        $data = $this->getGitlabCiData();

        if ($data === null) {
            return CheckResult::FAIL;
        }

        foreach ($this->requiredCiJobs() as $jobName => $extends) {
            $accepted = is_array($extends) ? $extends : [$extends];

            if (!isset($data[$jobName]['extends']) || !in_array($data[$jobName]['extends'], array_map(fn (string $template): array => [$template], $accepted), true)) {
                $rendered = implode(' or ', array_map(fn (string $template): string => "[$template]", $accepted));
                $this->addComment("Missing or misconfigured CI job in .gitlab-ci.yml: Add job '$jobName' with 'extends: $rendered'");

                return CheckResult::FAIL;
            }
        }

        return CheckResult::PASS;
    }
}

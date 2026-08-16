<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractFixableCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

class CiSetsNodeVersionCheck extends AbstractFixableCheck
{
    public function fix(bool $dry = false): CheckResult
    {
        $data = $this->getGitlabCiData();

        if ($data === null) {
            return CheckResult::FAIL;
        }

        $name = $this->policy()->string('ci.nodeVersionVariable.name');
        $value = $this->policy()->string('ci.nodeVersionVariable.value');

        $current = $data['variables'][$name] ?? null;

        if (is_scalar($current) && (string) $current === $value) {
            return CheckResult::PASS;
        }

        $this->addComment(sprintf(
            '.gitlab-ci.yml must set variables.%s to "%s" so the shared CI template resolves the Node version',
            $name,
            $value,
        ));

        if ($dry) {
            return CheckResult::FAIL;
        }

        $this->setYamlMappingScalar(base_path('.gitlab-ci.yml'), 'variables', $name, $value);

        return $this->fix(dry: true);
    }
}

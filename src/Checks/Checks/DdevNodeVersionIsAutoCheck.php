<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractFixableCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

class DdevNodeVersionIsAutoCheck extends AbstractFixableCheck
{
    public function fix(bool $dry = false): CheckResult
    {
        $ddevConfig = $this->getDdevConfig();

        if ($ddevConfig === null) {
            return CheckResult::FAIL;
        }

        if (($ddevConfig['nodejs_version'] ?? null) === 'auto') {
            return CheckResult::PASS;
        }

        $this->addComment('DDEV nodejs_version should be "auto" to derive the Node version from the project: set "nodejs_version: auto" in .ddev/config.yaml');

        if ($dry) {
            return CheckResult::FAIL;
        }

        $this->setYamlScalarKey(base_path('.ddev/config.yaml'), 'nodejs_version', 'auto');

        return $this->fix(dry: true);
    }
}

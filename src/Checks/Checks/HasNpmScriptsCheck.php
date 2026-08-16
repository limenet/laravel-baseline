<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

class HasNpmScriptsCheck extends AbstractCheck
{
    public function check(): CheckResult
    {
        $packageJson = $this->getPackageJson();

        if ($packageJson === null) {
            return CheckResult::FAIL;
        }

        foreach ($this->policy()->strings('npmScripts.required') as $script) {
            if (!isset($packageJson['scripts'][$script])) {
                $this->addComment("Missing {$script} script in package.json: Add \"{$script}\" to scripts section");

                return CheckResult::FAIL;
            }
        }

        return CheckResult::PASS;
    }
}

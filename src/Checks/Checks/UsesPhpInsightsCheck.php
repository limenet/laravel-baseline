<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractFixableCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

class UsesPhpInsightsCheck extends AbstractFixableCheck
{
    public function fix(bool $dry = false): CheckResult
    {
        if (!$this->checkComposerPackages('nunomaduro/phpinsights')) {
            return CheckResult::FAIL;
        }

        $scriptsOk = $this->checkComposerScript('ci-lint', 'insights --summary --no-interaction')
            && $this->checkComposerScript('ci-lint', 'insights -n --ansi --format=codeclimate > codeclimate-report.json 2>/dev/null');

        $configOk = $this->hasDisableSecurityCheck();

        if ($scriptsOk && $configOk) {
            return CheckResult::PASS;
        }

        if ($dry) {
            return CheckResult::FAIL;
        }

        if (!$scriptsOk) {
            $this->addToComposerScript('ci-lint', '@php artisan insights --summary --no-interaction');
            $this->addToComposerScript('ci-lint', '@php artisan insights -n --ansi --format=codeclimate > codeclimate-report.json 2>/dev/null');
        }

        // PHP config file cannot be auto-fixed; comment added by hasDisableSecurityCheck()
        return $this->fix(dry: true);
    }

    private function hasDisableSecurityCheck(): bool
    {
        $config = $this->parsePhpConfigFile('config/insights.php');

        if ($config === null) {
            return false;
        }

        if (($config['requirements']['disable-security-check'] ?? null) !== true) {
            $this->addComment("Set 'disable-security-check' => true in the requirements section of config/insights.php");

            return false;
        }

        return true;
    }
}

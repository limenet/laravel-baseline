<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractFixableCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

class DoesNotUsePhpInsightsCheck extends AbstractFixableCheck
{
    public function fix(bool $dry = false): CheckResult
    {
        $packageClean = !$this->checkComposerPackages('nunomaduro/phpinsights');
        $scriptClean = !$this->checkComposerScript('ci-lint', 'insights');
        $configFile = base_path('config/insights.php');
        $configClean = !file_exists($configFile);

        if ($packageClean && $scriptClean && $configClean) {
            return CheckResult::PASS;
        }

        if (!$packageClean) {
            $this->addComment('Remove nunomaduro/phpinsights from composer.json (run `composer update` afterward to sync composer.lock)');
        }

        if (!$scriptClean) {
            $this->addComment("Remove leftover 'insights' entries from the ci-lint script in composer.json");
        }

        if (!$configClean) {
            $this->addComment('Remove config/insights.php — PHP Insights is no longer used');
        }

        if ($dry) {
            return CheckResult::FAIL;
        }

        if (!$packageClean) {
            $this->removeComposerPackage('nunomaduro/phpinsights');
        }

        if (!$scriptClean) {
            $this->removeFromComposerScript('ci-lint', 'insights');
        }

        if (!$configClean) {
            unlink($configFile);
        }

        return $this->fix(dry: true);
    }
}

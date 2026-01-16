<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;
use Symfony\Component\Yaml\Yaml;

class PhpstanLevelAtLeastEightCheck extends AbstractCheck
{
    public function check(): CheckResult
    {
        $phpstanConfigFile = base_path('phpstan.neon');

        if (!file_exists($phpstanConfigFile)) {
            $this->addComment('PHPStan configuration missing: Create phpstan.neon in project root');

            return CheckResult::FAIL;
        }

        $phpstanConfig = Yaml::parseFile($phpstanConfigFile);

        $level = $phpstanConfig['parameters']['level'] ?? null;

        if ($level === null) {
            $this->addComment('PHPStan level not configured: Add "level" parameter to phpstan.neon');

            return CheckResult::FAIL;
        }

        // Handle both numeric and string levels (e.g., 8 or "8" or "max")
        if ($level === 'max') {
            return CheckResult::PASS;
        }

        $levelInt = is_numeric($level) ? (int) $level : null;

        if ($levelInt === null) {
            $this->addComment('PHPStan level must be a number or "max": Found "'.$level.'" in phpstan.neon');

            return CheckResult::FAIL;
        }

        if ($levelInt < 8) {
            $this->addComment('PHPStan level must be at least 8: Found level '.$levelInt.' in phpstan.neon (set to 8 or higher)');

            return CheckResult::FAIL;
        }

        return CheckResult::PASS;
    }
}

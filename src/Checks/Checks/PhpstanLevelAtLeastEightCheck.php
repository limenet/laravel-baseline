<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;
use Limenet\LaravelBaseline\Project\Profile;

class PhpstanLevelAtLeastEightCheck extends AbstractCheck
{
    public static function profiles(): array
    {
        return Profile::cases();
    }

    public function check(): CheckResult
    {
        $phpstanConfigFile = $this->path('phpstan.neon');

        if (!file_exists($phpstanConfigFile)) {
            $this->addComment('PHPStan configuration missing: Create phpstan.neon in project root');

            return CheckResult::FAIL;
        }

        // Anything that still is not valid YAML becomes a finding rather than
        // an exception that aborts the whole run.
        $phpstanConfig = $this->loadNeonConfig('phpstan.neon');

        if ($phpstanConfig === null) {
            return CheckResult::FAIL;
        }

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

<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

class HasDailyLoggingCheck extends AbstractCheck
{
    public function check(): CheckResult
    {
        $loggingConfig = $this->getLoggingConfig();

        if ($loggingConfig === null) {
            return CheckResult::FAIL;
        }

        $default = $loggingConfig['default'] ?? null;

        if ($default !== 'daily') {
            $this->addComment('Logging default channel should be "daily": Update config/logging.php to set \'default\' => \'daily\'');

            return CheckResult::FAIL;
        }

        return CheckResult::PASS;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function getLoggingConfig(): ?array
    {
        $loggingFile = base_path('config/logging.php');

        if (!file_exists($loggingFile)) {
            $this->addComment('Logging configuration missing: config/logging.php not found');

            return null;
        }

        return require $loggingFile;
    }
}

<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Backup\BackupConfigVisitor;
use Limenet\LaravelBaseline\Backup\FuncCallInfo;
use Limenet\LaravelBaseline\Checks\AbstractCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;

class HasDailyLoggingCheck extends AbstractCheck
{
    public function check(): CheckResult
    {
        $loggingConfig = $this->getLoggingConfig();

        if ($loggingConfig === null) {
            return CheckResult::FAIL;
        }

        $default = $loggingConfig['default'] ?? null;

        // Direct 'daily' channel is valid
        if ($default === 'daily') {
            return CheckResult::PASS;
        }

        // env('LOG_CHANNEL', 'daily') is valid
        if ($default instanceof FuncCallInfo && $default->isCall('env', 'LOG_CHANNEL')) {
            $fallback = $default->getSecondArg();

            if ($fallback === 'daily') {
                return CheckResult::PASS;
            }

            // env('LOG_CHANNEL', 'stack') - check stack channels
            if ($fallback === 'stack') {
                return $this->validateStackChannels($loggingConfig);
            }
        }

        // Direct 'stack' channel - check stack channels
        if ($default === 'stack') {
            return $this->validateStackChannels($loggingConfig);
        }

        $this->addComment('Logging default channel should be "daily" or "stack" (with daily in stack channels), or env(\'LOG_CHANNEL\', \'daily\'|\'stack\'): Update config/logging.php');

        return CheckResult::FAIL;
    }

    private function validateStackChannels(array $loggingConfig): CheckResult
    {
        $stackChannels = $loggingConfig['channels']['stack']['channels'] ?? [];

        if (is_array($stackChannels) && in_array('daily', $stackChannels, true)) {
            return CheckResult::PASS;
        }

        $this->addComment('Stack channel must include "daily": Update config/logging.php channels.stack.channels to include \'daily\'');

        return CheckResult::FAIL;
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

        $code = file_get_contents($loggingFile);

        if ($code === false) {
            $this->addComment('Logging configuration unreadable: Unable to read config/logging.php');

            return null;
        }

        return $this->parseConfig($code);
    }

    /**
     * Parse the config file using PHP Parser.
     *
     * @return array<string,mixed>|null
     */
    private function parseConfig(string $code): ?array
    {
        $parser = (new ParserFactory())->createForNewestSupportedVersion();

        try {
            $ast = $parser->parse($code);
        } catch (\Throwable) {
            $this->addComment('Logging configuration invalid: Unable to parse config/logging.php');

            return null;
        }

        if ($ast === null) {
            $this->addComment('Logging configuration invalid: Unable to parse config/logging.php');

            return null;
        }

        $visitor = new BackupConfigVisitor();
        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        return $visitor->getConfig();
    }
}

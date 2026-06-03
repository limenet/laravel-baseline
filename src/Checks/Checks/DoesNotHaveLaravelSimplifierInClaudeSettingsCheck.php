<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractClaudeSettingsCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

class DoesNotHaveLaravelSimplifierInClaudeSettingsCheck extends AbstractClaudeSettingsCheck
{
    public function fix(bool $dry = false): CheckResult
    {
        $settings = $this->readClaudeSettings();

        if ($settings === null) {
            return CheckResult::PASS;
        }

        if (($settings['enabledPlugins']['laravel-simplifier@laravel'] ?? false) !== true) {
            return CheckResult::PASS;
        }

        $this->addComment('Remove "laravel-simplifier@laravel" from enabledPlugins in .claude/settings.json — the plugin no longer exists');

        if ($dry) {
            return CheckResult::FAIL;
        }

        unset($settings['enabledPlugins']['laravel-simplifier@laravel']);

        $this->writeClaudeSettings($settings);

        return CheckResult::PASS;
    }
}

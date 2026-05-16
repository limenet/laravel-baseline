<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractFixableCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

class DoesNotHaveLaravelSimplifierInClaudeSettingsCheck extends AbstractFixableCheck
{
    public function fix(bool $dry = false): CheckResult
    {
        $settingsFile = base_path('.claude/settings.json');

        if (!file_exists($settingsFile)) {
            return CheckResult::PASS;
        }

        $content = file_get_contents($settingsFile);

        if ($content === false || trim($content) === '') {
            return CheckResult::PASS;
        }

        $settings = json_decode($content, true, flags: JSON_THROW_ON_ERROR) ?? [];

        if (($settings['enabledPlugins']['laravel-simplifier@laravel'] ?? false) !== true) {
            return CheckResult::PASS;
        }

        $this->addComment('Remove "laravel-simplifier@laravel" from enabledPlugins in .claude/settings.json — the plugin no longer exists');

        if ($dry) {
            return CheckResult::FAIL;
        }

        unset($settings['enabledPlugins']['laravel-simplifier@laravel']);

        file_put_contents($settingsFile, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");

        return CheckResult::PASS;
    }
}

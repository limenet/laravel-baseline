<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

class HasClaudeSettingsWithLaravelSimplifierCheck extends AbstractCheck
{
    public function check(): CheckResult
    {
        $settingsFile = base_path('.claude/settings.json');

        if (!file_exists($settingsFile)) {
            $this->addComment('Claude settings missing: Create .claude/settings.json with enabledPlugins configuration');

            return CheckResult::FAIL;
        }

        $settings = json_decode(
            file_get_contents($settingsFile) ?: throw new \RuntimeException(),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $enabledPlugins = $settings['enabledPlugins'] ?? null;

        if ($enabledPlugins === null) {
            $this->addComment('Claude settings incomplete: Add "enabledPlugins" section to .claude/settings.json');

            return CheckResult::FAIL;
        }

        $laravelSimplifierKey = 'laravel-simplifier@laravel';

        if (!isset($enabledPlugins[$laravelSimplifierKey]) || $enabledPlugins[$laravelSimplifierKey] !== true) {
            $this->addComment('Claude settings incomplete: Add "laravel-simplifier@laravel": true to enabledPlugins in .claude/settings.json');

            return CheckResult::FAIL;
        }

        return CheckResult::PASS;
    }
}

<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractClaudeSettingsCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

/**
 * `permissions.ask`, not `permissions.deny`: dropping the local database is a
 * legitimate thing to do, it just must never happen because an agent decided
 * on its own that it would be convenient. `ask` keeps the approval path and
 * costs a keystroke; `deny` would push developers to disable the rule instead.
 */
class AsksBeforeDestructiveDbCommandsInClaudeSettingsCheck extends AbstractClaudeSettingsCheck
{
    public function fix(bool $dry = false): CheckResult
    {
        $required = $this->policy()->strings('claude.ask.php');

        $settings = $this->readClaudeSettings() ?? [];

        /** @var list<string> $ask */
        $ask = $settings['permissions']['ask'] ?? [];

        $missing = array_values(array_diff($required, $ask));

        if ($missing === []) {
            return CheckResult::PASS;
        }

        foreach ($missing as $entry) {
            $this->addComment("Claude settings: add \"{$entry}\" to permissions.ask in .claude/settings.json — destroys database contents, so it must be confirmed every time");
        }

        if ($dry) {
            return CheckResult::FAIL;
        }

        $settings['permissions']['ask'] = $this->mergeMissing($ask, $required);

        $this->writeClaudeSettings($settings);

        return CheckResult::PASS;
    }
}

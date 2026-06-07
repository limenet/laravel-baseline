<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractClaudeSettingsCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

class AllowsToolingInClaudeSettingsCheck extends AbstractClaudeSettingsCheck
{
    /**
     * DDEV-prefixed to match the tool's convention (see LaravelBoostMcpUsesDdevCheck).
     * artisan rules are scoped to read-only / dev-loop commands only — a blanket
     * `ddev artisan:*` would auto-allow destructive commands (migrate:fresh, db:wipe,
     * tinker, …), so each safe command is listed individually instead.
     *
     * composer/npm rules cover read-only package inspection (show, outdated, why,
     * info, view, ls) — these query metadata and never mutate the project.
     *
     * Skill rules allow code-review without a permission prompt (both the bare form
     * and the parameterised `code-review:<effort/flags>` form).
     */
    private const array REQUIRED_ALLOW = [
        'Bash(ddev composer run ci-lint:*)',
        'Bash(ddev composer test:*)',
        'Bash(ddev artisan test:*)',
        'Bash(ddev artisan make:*)',
        'Bash(ddev artisan route:list:*)',
        'Bash(ddev artisan about:*)',
        'Bash(ddev artisan config:show:*)',
        'Bash(ddev artisan ide-helper:*)',
        'Bash(ddev artisan optimize:clear:*)',
        'Bash(ddev artisan cache:clear:*)',
        'Bash(ddev artisan config:clear:*)',
        'Bash(ddev artisan route:clear:*)',
        'Bash(ddev artisan view:clear:*)',
        'Bash(ddev composer show:*)',
        'Bash(ddev composer outdated:*)',
        'Bash(ddev composer why:*)',
        'Bash(npm info:*)',
        'Bash(npm view:*)',
        'Bash(npm ls:*)',
        'Bash(npm outdated:*)',
        'Skill(code-review)',
        'Skill(code-review:*)',
    ];

    public function fix(bool $dry = false): CheckResult
    {
        $settings = $this->readClaudeSettings() ?? [];

        /** @var list<string> $allow */
        $allow = $settings['permissions']['allow'] ?? [];

        $missing = array_values(array_diff(self::REQUIRED_ALLOW, $allow));

        if ($missing === []) {
            return CheckResult::PASS;
        }

        foreach ($missing as $entry) {
            $this->addComment("Claude settings: add \"{$entry}\" to permissions.allow in .claude/settings.json");
        }

        if ($dry) {
            return CheckResult::FAIL;
        }

        $settings['permissions']['allow'] = $this->mergeMissing($allow, self::REQUIRED_ALLOW);

        $this->writeClaudeSettings($settings);

        return CheckResult::PASS;
    }
}

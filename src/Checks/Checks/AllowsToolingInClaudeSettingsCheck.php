<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractClaudeSettingsCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

class AllowsToolingInClaudeSettingsCheck extends AbstractClaudeSettingsCheck
{
    public function fix(bool $dry = false): CheckResult
    {
        $required = $this->requiredAllowEntries();

        $settings = $this->readClaudeSettings() ?? [];

        /** @var list<string> $allow */
        $allow = $settings['permissions']['allow'] ?? [];

        $missing = array_values(array_diff($required, $allow));

        if ($missing === []) {
            return CheckResult::PASS;
        }

        foreach ($missing as $entry) {
            $this->addComment("Claude settings: add \"{$entry}\" to permissions.allow in .claude/settings.json");
        }

        if ($dry) {
            return CheckResult::FAIL;
        }

        $settings['permissions']['allow'] = $this->mergeMissing($allow, $required);

        $this->writeClaudeSettings($settings);

        return CheckResult::PASS;
    }

    /**
     * The shared entries (read-only npm inspection, code-review skill) plus the
     * Laravel/DDEV ones. Both halves live in policy/policy.json so the npm runner
     * can require the shared half without inheriting the DDEV half.
     *
     * DDEV-prefixed to match the tool's convention (see LaravelBoostMcpUsesDdevCheck).
     * artisan rules are scoped to read-only / dev-loop commands only — a blanket
     * `ddev artisan:*` would auto-allow destructive commands (migrate:fresh, db:wipe,
     * tinker, …), so each safe command is listed individually instead.
     *
     * @return list<string>
     */
    private function requiredAllowEntries(): array
    {
        return [
            ...$this->policy()->strings('claude.allow.php'),
            ...$this->policy()->strings('claude.allow.shared'),
        ];
    }
}

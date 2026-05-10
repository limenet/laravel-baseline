<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractFixableCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

class UsesLaravelBoostCheck extends AbstractFixableCheck
{
    public function fix(bool $dry = false): CheckResult
    {
        if (!$this->checkComposerPackages('laravel/boost')) {
            return $dry ? CheckResult::FAIL : CheckResult::FAIL;
        }

        if (!$this->hasPostUpdateScript('boost:update')) {
            if ($dry) {
                return CheckResult::FAIL;
            }

            $this->addToComposerScript('post-update-cmd', '@php artisan boost:update');
        }

        $boostJsonFile = base_path('boost.json');

        if (!file_exists($boostJsonFile)) {
            $this->addComment('Laravel Boost configuration missing: Create boost.json in project root');

            if ($dry) {
                return CheckResult::FAIL;
            }

            $boostConfig = [];
        } else {
            $boostConfig = json_decode(
                file_get_contents($boostJsonFile) ?: throw new \RuntimeException(),
                true,
                flags: JSON_THROW_ON_ERROR,
            ) ?? [];
        }

        $requiredAgents = ['claude_code', 'copilot', 'junie'];
        $missingAgents = array_diff($requiredAgents, $boostConfig['agents'] ?? []);

        if ($missingAgents !== []) {
            $this->addComment('Laravel Boost v2 configuration incomplete: boost.json must include agents: '.implode(', ', $requiredAgents));

            if ($dry) {
                return CheckResult::FAIL;
            }
        }

        if (($boostConfig['guidelines'] ?? null) !== true) {
            $this->addComment('Laravel Boost v2 configuration incomplete: boost.json must set "guidelines": true');

            if ($dry) {
                return CheckResult::FAIL;
            }
        }

        if (($boostConfig['mcp'] ?? null) !== true) {
            $this->addComment('Laravel Boost v2 configuration incomplete: boost.json must set "mcp": true');

            if ($dry) {
                return CheckResult::FAIL;
            }
        }

        if ($dry) {
            return CheckResult::PASS;
        }

        // Apply boost.json fix
        $boostConfig['agents'] = array_values(array_unique(array_merge($boostConfig['agents'] ?? [], $requiredAgents)));
        $boostConfig['guidelines'] = true;
        $boostConfig['mcp'] = true;

        file_put_contents($boostJsonFile, json_encode($boostConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");

        return $this->fix(dry: true);
    }
}

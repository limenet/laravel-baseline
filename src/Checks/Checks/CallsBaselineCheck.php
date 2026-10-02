<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractFixableCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;
use Limenet\LaravelBaseline\Project\Profile;

class CallsBaselineCheck extends AbstractFixableCheck
{
    public static function profiles(): array
    {
        return Profile::cases();
    }

    public function fix(bool $dry = false): CheckResult
    {
        $runner = 'baseline.runner.'.$this->profile()->runner();
        $match = $this->policy()->string($runner.'.checkMatch');

        if ($this->hasPostUpdateScript($match.' --fix')) {
            return CheckResult::PASS;
        }

        if ($dry) {
            return CheckResult::FAIL;
        }

        $composerJson = $this->getComposerJson();

        if ($composerJson === null) {
            return CheckResult::FAIL;
        }

        // Upgrade existing entry (without --fix) to include --fix. Composer
        // also accepts a single command as a plain string.
        $scripts = (array) ($composerJson['scripts']['post-update-cmd'] ?? []);

        foreach ($scripts as $i => $script) {
            if (is_string($script) && str_contains($script, $match) && !str_contains($script, '--fix')) {
                $scripts[$i] = rtrim($script).' --fix';
                $composerJson['scripts']['post-update-cmd'] = $scripts;
                $this->writeComposerJson($composerJson);

                return $this->fix(dry: true);
            }
        }

        $this->addToComposerScript('post-update-cmd', $this->policy()->string($runner.'.postUpdateScript'));

        return $this->fix(dry: true);
    }
}

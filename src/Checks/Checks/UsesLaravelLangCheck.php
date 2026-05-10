<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractFixableCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

class UsesLaravelLangCheck extends AbstractFixableCheck
{
    public function fix(bool $dry = false): CheckResult
    {
        $composerJson = $this->getComposerJson();

        if ($composerJson === null) {
            return CheckResult::FAIL;
        }

        if (!isset($composerJson['require-dev']['laravel-lang/lang'])) {
            $this->addComment('Missing dev dependency in composer.json: Add "laravel-lang/lang" to require-dev');

            return CheckResult::FAIL;
        }

        if ($this->hasPostUpdateScript('lang:update') && $this->hasPostUpdateScript('pint --dirty')) {
            return CheckResult::PASS;
        }

        if (!$this->hasPostUpdateScript('lang:update')) {
            $this->addComment('Missing script in composer.json: Add "php artisan lang:update" to post-update-cmd section');
        }

        if (!$this->hasPostUpdateScript('pint --dirty')) {
            $this->addComment('Missing script in composer.json: Add "./vendor/bin/pint --dirty" to post-update-cmd section');
        }

        if ($dry) {
            return CheckResult::FAIL;
        }

        $this->addToComposerScript('post-update-cmd', '@php artisan lang:update');
        $this->addToComposerScript('post-update-cmd', '@php vendor/bin/pint --dirty');

        return $this->fix(dry: true);
    }
}

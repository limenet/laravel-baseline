<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

class UsesLaravelLangCheck extends AbstractCheck
{
    public function check(): CheckResult
    {
        $composerJson = $this->getComposerJson();

        if ($composerJson === null) {
            return CheckResult::FAIL;
        }

        if (!isset($composerJson['require-dev']['laravel-lang/lang'])) {
            $this->addComment('Missing dev dependency in composer.json: Add "laravel-lang/lang" to require-dev');

            return CheckResult::FAIL;
        }

        if (!$this->hasPostUpdateScript('lang:update')) {
            $this->addComment('Missing script in composer.json: Add "php artisan lang:update" to post-update-cmd section');

            return CheckResult::FAIL;
        }

        return CheckResult::PASS;
    }
}

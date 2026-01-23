<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Adminer\AdminerConfigValidator;
use Limenet\LaravelBaseline\Checks\AbstractCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

class UsesLaravelAdminerCheck extends AbstractCheck
{
    public function check(): CheckResult
    {
        // Check if laravel-adminer is installed
        if (!$this->checkComposerPackages('onecentlin/laravel-adminer')) {
            return CheckResult::WARN;
        }

        // Check if TFA confirmation package is installed
        if (!$this->checkComposerPackages('wnx/laravel-tfa-confirmation')) {
            $this->addComment('Missing package: Install wnx/laravel-tfa-confirmation via "composer require wnx/laravel-tfa-confirmation"');

            return CheckResult::FAIL;
        }

        // Validate the adminer configuration
        $validator = new AdminerConfigValidator();
        $errors = $validator->validate(
            base_path('config/adminer.php'),
            base_path('app/Http/Kernel.php'),
        );

        foreach ($errors as $error) {
            $this->addComment($error);
        }

        return $errors === [] ? CheckResult::PASS : CheckResult::FAIL;
    }
}

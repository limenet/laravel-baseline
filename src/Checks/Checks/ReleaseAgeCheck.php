<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

class ReleaseAgeCheck extends AbstractCheck
{
    public function check(): CheckResult
    {
        if (!$this->checkComposerPackages('spatie/laravel-health')) {
            return CheckResult::WARN;
        }

        $composerFile = base_path('composer.json');

        if (!file_exists($composerFile)) {
            $this->addComment('Release age check failed: composer.json not found');

            return CheckResult::FAIL;
        }

        $mtime = filemtime($composerFile);

        if ($mtime === false) {
            $this->addComment('Release age check failed: could not read modification time of composer.json');

            return CheckResult::FAIL;
        }

        $ageInDays = (time() - $mtime) / 86400;

        if ($ageInDays < 42) { // < 6 weeks
            return CheckResult::PASS;
        }

        if ($ageInDays < 84) { // < 3 months
            $this->addComment(sprintf(
                'Release is getting old: composer.json was last modified %.0f days ago (should be updated within 6 weeks)',
                $ageInDays,
            ));

            return CheckResult::WARN;
        }

        $this->addComment(sprintf(
            'Release is too old: composer.json was last modified %.0f days ago (must be updated within 3 months)',
            $ageInDays,
        ));

        return CheckResult::FAIL;
    }
}

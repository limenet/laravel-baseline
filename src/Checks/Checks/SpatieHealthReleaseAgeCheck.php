<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

class SpatieHealthReleaseAgeCheck extends AbstractCheck
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

        if ($ageInDays >= 84) { // 12 weeks = ~3 months
            return CheckResult::PASS;
        }

        if ($ageInDays >= 42) { // 6 weeks
            $this->addComment(sprintf(
                'Release is recent: composer.json was last modified %.0f days ago (OK at 6 weeks, good at 3 months)',
                $ageInDays,
            ));

            return CheckResult::WARN;
        }

        $this->addComment(sprintf(
            'Release is too recent: composer.json was last modified %.0f days ago (must be at least 6 weeks old)',
            $ageInDays,
        ));

        return CheckResult::FAIL;
    }
}

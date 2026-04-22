<?php

namespace Limenet\LaravelBaseline\Health;

use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;

class ReleaseAgeCheck extends Check
{
    public function run(): Result
    {
        $composerFile = base_path('composer.json');

        if (!file_exists($composerFile)) {
            return Result::make()->failed('Release age check failed: composer.json not found');
        }

        $mtime = filemtime($composerFile);

        if ($mtime === false) {
            return Result::make()->failed('Release age check failed: could not read modification time of composer.json');
        }

        $ageInDays = (int) ((time() - $mtime) / 86400);

        if ($ageInDays < 42) { // < 6 weeks
            return Result::make()->ok(sprintf('Last released %d days ago', $ageInDays));
        }

        if ($ageInDays < 84) { // < 3 months
            return Result::make()->warning(sprintf('Release is getting old: last released %d days ago (should be updated within 6 weeks)', $ageInDays));
        }

        return Result::make()->failed(sprintf('Release is too old: last released %d days ago (must be updated within 3 months)', $ageInDays));
    }
}

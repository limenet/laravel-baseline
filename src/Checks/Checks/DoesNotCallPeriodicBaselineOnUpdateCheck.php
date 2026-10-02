<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;
use Limenet\LaravelBaseline\Project\Profile;

class DoesNotCallPeriodicBaselineOnUpdateCheck extends AbstractCheck
{
    public static function profiles(): array
    {
        return Profile::cases();
    }

    public function check(): CheckResult
    {
        $match = $this->policy()->string('baseline.runner.'.$this->profile()->runner().'.periodicMatch');

        if ($this->hasPostUpdateScript($match)) {
            $this->addComment('Remove `'.$match.'` from post-update-cmd in composer.json — periodic checks fail CI automatically when expired, so running it on every composer update is unnecessary');

            return CheckResult::FAIL;
        }

        return CheckResult::PASS;
    }
}

<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;
use Limenet\LaravelBaseline\Project\Profile;

class UsesPestCheck extends AbstractCheck
{
    public static function profiles(): array
    {
        return Profile::cases();
    }

    public function check(): CheckResult
    {
        // A Laravel app is expected to have a test suite; elsewhere tests are
        // optional, but the ones that exist must still run on plain Pest.
        $required = $this->profile() === Profile::Laravel
            ? ['pestphp/pest', 'pestphp/pest-plugin-laravel']
            : ['pestphp/pest'];

        if (!$this->checkComposerPackages($required)) {
            return $this->profile() === Profile::Laravel ? CheckResult::FAIL : CheckResult::WARN;
        }

        foreach (['pestphp/pest-plugin-drift', 'spatie/phpunit-watcher'] as $forbidden) {
            if ($this->project->hasComposerPackage($forbidden)) {
                $this->addComment("Remove {$forbidden} from composer.json: it is not used alongside Pest");

                return CheckResult::FAIL;
            }
        }

        return CheckResult::PASS;
    }
}

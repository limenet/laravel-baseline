<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

class HasGuidelinesUpdateScriptCheck extends AbstractCheck
{
    public function check(): CheckResult
    {
        if (!$this->hasPostUpdateScript('limenet:laravel-baseline:guidelines')) {
            $this->addComment('Missing guidelines update script in composer.json: Add "@php artisan limenet:laravel-baseline:guidelines" to post-update-cmd section');

            return CheckResult::FAIL;
        }

        // Check that guidelines command comes before boost:update
        $composerJson = $this->getComposerJson();
        if ($composerJson !== null) {
            $postUpdateScripts = $composerJson['scripts']['post-update-cmd'] ?? [];
            $guidelinesIndex = null;
            $boostIndex = null;

            foreach ($postUpdateScripts as $index => $script) {
                if (str_contains($script, 'limenet:laravel-baseline:guidelines')) {
                    $guidelinesIndex = $index;
                }
                if (str_contains($script, 'boost:update')) {
                    $boostIndex = $index;
                }
            }

            // If both exist, guidelines must come before boost
            if ($guidelinesIndex !== null && $boostIndex !== null && $guidelinesIndex > $boostIndex) {
                $this->addComment('Guidelines update script must be called before boost:update in composer.json post-update-cmd section');

                return CheckResult::FAIL;
            }
        }

        return CheckResult::PASS;
    }
}

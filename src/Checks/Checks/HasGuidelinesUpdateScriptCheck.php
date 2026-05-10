<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractFixableCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

class HasGuidelinesUpdateScriptCheck extends AbstractFixableCheck
{
    public function fix(bool $dry = false): CheckResult
    {
        $composerJson = $this->getComposerJson();

        if ($composerJson === null) {
            return CheckResult::FAIL;
        }

        $scripts = $composerJson['scripts']['post-update-cmd'] ?? [];
        $guidelinesIdx = null;
        $boostIdx = null;

        foreach ($scripts as $i => $script) {
            if (str_contains($script, 'limenet:laravel-baseline:guidelines')) {
                $guidelinesIdx = $i;
            }

            if (str_contains($script, 'boost:update')) {
                $boostIdx = $i;
            }
        }

        if ($guidelinesIdx === null) {
            $this->addComment('Missing guidelines update script in composer.json: Add "@php artisan limenet:laravel-baseline:guidelines" to post-update-cmd section');

            if ($dry) {
                return CheckResult::FAIL;
            }

            $this->addToComposerScript('post-update-cmd', '@php artisan limenet:laravel-baseline:guidelines', insertBefore: 'boost:update');

            return $this->fix(dry: true);
        }

        if ($boostIdx !== null && $guidelinesIdx > $boostIdx) {
            $this->addComment('Guidelines update script must be called before boost:update in composer.json post-update-cmd section');

            if ($dry) {
                return CheckResult::FAIL;
            }

            $guidelinesCmd = '@php artisan limenet:laravel-baseline:guidelines';
            unset($scripts[$guidelinesIdx]);
            $scripts = array_values($scripts);

            foreach ($scripts as $i => $script) {
                if (str_contains($script, 'boost:update')) {
                    array_splice($scripts, $i, 0, [$guidelinesCmd]);
                    break;
                }
            }

            $composerJson['scripts']['post-update-cmd'] = $scripts;
            $this->writeComposerJson($composerJson);

            return $this->fix(dry: true);
        }

        return CheckResult::PASS;
    }
}

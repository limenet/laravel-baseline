<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

class UsesReleaseItCheck extends AbstractCheck
{
    public function check(): CheckResult
    {
        // Check if release-it and @release-it/bumper are in devDependencies
        if (!$this->checkNpmPackages(['release-it', '@release-it/bumper'])) {
            return CheckResult::FAIL;
        }

        // Check if release npm script exists
        if (!$this->checkNpmScript('release', 'release-it')) {
            $this->addComment('Missing release script in package.json: Add "release": "release-it" to scripts section');

            return CheckResult::FAIL;
        }

        // Check if .release-it.json exists and has correct configuration
        $releaseItConfig = $this->getReleaseItConfig();

        if ($releaseItConfig === null) {
            return CheckResult::FAIL;
        }

        // Check for plugins configuration
        $bumperPlugin = $releaseItConfig['plugins']['@release-it/bumper'] ?? null;

        if ($bumperPlugin === null) {
            $this->addComment('Missing @release-it/bumper plugin configuration in .release-it.json: Add plugins section with @release-it/bumper');

            return CheckResult::FAIL;
        }

        // Check bumper plugin out configuration
        $outFile = $bumperPlugin['out']['file'] ?? null;
        $outPath = $bumperPlugin['out']['path'] ?? null;

        if ($outFile !== 'composer.json' || $outPath !== 'version') {
            $this->addComment('Invalid @release-it/bumper configuration in .release-it.json: Set out.file to "composer.json" and out.path to "version"');

            return CheckResult::FAIL;
        }

        return CheckResult::PASS;
    }
}

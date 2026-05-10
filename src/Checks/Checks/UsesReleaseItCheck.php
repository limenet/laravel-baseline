<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractFixableCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

class UsesReleaseItCheck extends AbstractFixableCheck
{
    public function fix(bool $dry = false): CheckResult
    {
        // Can't run npm install — only fix config parts if packages are installed
        if (!$this->checkNpmPackages(['release-it', '@release-it/bumper'])) {
            return CheckResult::FAIL;
        }

        $releaseScriptOk = $this->checkNpmScript('release', 'release-it');

        if (!$releaseScriptOk) {
            $this->addComment('Missing release script in package.json: Add "release": "release-it" to scripts section');

            if ($dry) {
                return CheckResult::FAIL;
            }

            $packageFile = base_path('package.json');

            if (file_exists($packageFile)) {
                $packageJson = json_decode(file_get_contents($packageFile) ?: '{}', true, flags: JSON_THROW_ON_ERROR);
                $packageJson['scripts']['release'] = 'release-it';
                file_put_contents($packageFile, json_encode($packageJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
            }
        }

        $releaseItConfig = $this->getReleaseItConfig();
        $bumperPlugin = $releaseItConfig['plugins']['@release-it/bumper'] ?? null;

        if ($bumperPlugin === null) {
            $this->addComment('Missing @release-it/bumper plugin configuration in .release-it.json: Add plugins section with @release-it/bumper');

            if ($dry) {
                return CheckResult::FAIL;
            }
        } elseif (($bumperPlugin['out']['file'] ?? null) !== 'composer.json' || ($bumperPlugin['out']['path'] ?? null) !== 'version') {
            $this->addComment('Invalid @release-it/bumper configuration in .release-it.json: Set out.file to "composer.json" and out.path to "version"');

            if ($dry) {
                return CheckResult::FAIL;
            }
        }

        if ($dry) {
            return CheckResult::PASS;
        }

        // Apply .release-it.json fix
        $releaseItFile = base_path('.release-it.json');
        $config = file_exists($releaseItFile)
            ? (json_decode(file_get_contents($releaseItFile) ?: '{}', true, flags: JSON_THROW_ON_ERROR) ?? [])
            : [];

        $config['plugins']['@release-it/bumper']['out']['file'] = 'composer.json';
        $config['plugins']['@release-it/bumper']['out']['path'] = 'version';

        file_put_contents($releaseItFile, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");

        return $this->fix(dry: true);
    }
}

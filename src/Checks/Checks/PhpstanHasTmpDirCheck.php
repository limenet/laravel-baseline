<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractFixableCheck;
use Limenet\LaravelBaseline\Concerns\WritesNeonParameters;
use Limenet\LaravelBaseline\Enums\CheckResult;
use Limenet\LaravelBaseline\Project\Profile;

/**
 * PHPStan caches its result under the system temp directory by default, which
 * a CI job cannot carry over to the next pipeline. Inside the project the CI
 * templates can cache it, so an unchanged file is not analysed again.
 */
class PhpstanHasTmpDirCheck extends AbstractFixableCheck
{
    use WritesNeonParameters;

    public static function profiles(): array
    {
        return Profile::cases();
    }

    public function fix(bool $dry = false): CheckResult
    {
        $configFile = $this->phpstanConfigFile();

        if ($configFile === null) {
            $this->addComment('PHPStan configuration missing: Create phpstan.neon in project root');

            return CheckResult::FAIL;
        }

        $phpstanConfig = $this->loadNeonConfig($configFile);

        if ($phpstanConfig === null) {
            return CheckResult::FAIL;
        }

        $directory = trim($this->policy()->string('toolCache.phpstan'), '/');
        $tmpDir = $phpstanConfig['parameters']['tmpDir'] ?? null;

        if (!is_string($tmpDir) || self::normalise($tmpDir) !== $directory) {
            $this->addComment('PHPStan cache outside the project: Set "tmpDir: '.$directory.'" in the parameters section of '.$configFile.' so CI can cache it');

            if ($dry) {
                return CheckResult::FAIL;
            }

            $this->setNeonParameter($this->path($configFile), 'tmpDir', $directory);
        }

        $gitignoreResult = $this->ensureGitignoreEntry('/'.$directory.'/', 'ignore the PHPStan cache directory', $dry);

        if ($gitignoreResult !== null && $dry) {
            return $gitignoreResult;
        }

        return $dry ? CheckResult::PASS : $this->fix(dry: true);
    }

    /**
     * Relative paths in a PHPStan config resolve against the config file,
     * which sits in the project root, so these all name the same directory.
     */
    private static function normalise(string $path): string
    {
        $path = (string) preg_replace('#^(%currentWorkingDirectory%|\./)/?#', '', $path);

        return trim($path, '/');
    }
}

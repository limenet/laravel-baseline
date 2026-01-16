<?php

namespace Limenet\LaravelBaseline\Checks;

use Illuminate\Support\Composer;
use Illuminate\Support\Facades\Schedule;
use Limenet\LaravelBaseline\Concerns\CommentManagement;
use Limenet\LaravelBaseline\Enums\CheckResult;
use Symfony\Component\Yaml\Yaml;

abstract class AbstractCheck implements CheckInterface
{
    use CommentManagement;

    public function __construct(CommentCollector $commentCollector)
    {
        $this->commentCollector = $commentCollector;
    }

    /**
     * Default implementation derives name from class name.
     * e.g., UsesPestCheck -> usesPest
     */
    final public static function name(): string
    {
        return str(class_basename(static::class))
            ->beforeLast('Check')
            ->lcfirst()
            ->toString();
    }

    protected function getComposer(): Composer
    {
        return app(Composer::class)->setWorkingPath(base_path());
    }

    /**
     * @param  string|list<string>  $packages
     */
    protected function checkComposerPackages(string|array $packages): bool
    {
        $composer = $this->getComposer();
        $packages = is_string($packages) ? [$packages] : $packages;

        $this->addComment('Composer check: '.implode(', ', $packages));

        foreach ($packages as $package) {
            if (!$composer->hasPackage($package)) {
                return false;
            }
        }

        return true;
    }

    protected function checkComposerScript(string $scriptName, string $match): bool
    {
        $composerJson = $this->getComposerJson();

        if ($composerJson === null) {
            return false;
        }

        $this->addComment('Composer script check: '.$scriptName.' for '.$match);

        foreach ($composerJson['scripts'][$scriptName] ?? [] as $script) {
            if (str($script)->contains($match)) {
                return true;
            }
        }

        return false;
    }

    protected function hasPostDeployScript(string $match): bool
    {
        return $this->checkComposerScript('ci-deploy-post', $match);
    }

    protected function hasPostUpdateScript(string $match): bool
    {
        return $this->checkComposerScript('post-update-cmd', $match);
    }

    /**
     * @return array<string,mixed>|null
     */
    protected function getComposerJson(): ?array
    {
        $composerFile = base_path('composer.json');

        if (!file_exists($composerFile)) {
            $this->addComment('Composer configuration missing: composer.json not found in project root');

            return null;
        }

        return json_decode(
            file_get_contents($composerFile) ?: throw new \RuntimeException(),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
    }

    protected function getComposerPhpVersion(): ?string
    {
        $composerJson = $this->getComposerJson();

        if ($composerJson === null) {
            return null;
        }

        $phpConstraint = $composerJson['require']['php'] ?? null;

        if ($phpConstraint === null) {
            $this->addComment('PHP version not defined: Add "php" requirement to composer.json');

            return null;
        }

        // Extract the minimum version from the constraint (e.g., "^8.2" -> "8.2")
        if (!preg_match('/\^?(\d+\.\d+)/', $phpConstraint, $matches)) {
            $this->addComment('PHP version format invalid in composer.json: Use format "^X.Y" (e.g., "^8.4"), found: '.$phpConstraint);

            return null;
        }

        return $matches[1];
    }

    // === NPM Helpers ===

    /**
     * @param  string|list<string>  $packages
     */
    protected function checkNpmPackages(string|array $packages, string $packageType = 'devDependencies'): bool
    {
        $packageJson = $this->getPackageJson();

        if ($packageJson === null) {
            return false;
        }

        $packages = is_string($packages) ? [$packages] : $packages;

        $this->addComment('NPM check ('.$packageType.'): '.implode(', ', $packages));

        foreach ($packages as $package) {
            if (!isset($packageJson[$packageType][$package])) {
                return false;
            }
        }

        return true;
    }

    protected function checkNpmScript(string $scriptName, string $match): bool
    {
        $packageJson = $this->getPackageJson();

        if ($packageJson === null) {
            return false;
        }

        $this->addComment('NPM script check: '.$scriptName.' for '.$match);

        $script = $packageJson['scripts'][$scriptName] ?? null;

        if ($script === null) {
            return false;
        }

        return str_contains($script, $match);
    }

    /**
     * @return array<string,mixed>|null
     */
    protected function getPackageJson(): ?array
    {
        $packageFile = base_path('package.json');

        if (!file_exists($packageFile)) {
            $this->addComment('Package.json missing: Create package.json in project root');

            return null;
        }

        return json_decode(
            file_get_contents($packageFile) ?: throw new \RuntimeException(),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
    }

    // === Config File Helpers ===

    protected function getPhpunitXml(): \SimpleXMLElement|false|null
    {
        $xmlFile = base_path('/phpunit.xml');

        if (!file_exists($xmlFile)) {
            return null;
        }

        return simplexml_load_string(file_get_contents($xmlFile) ?: '');
    }

    protected function checkPhpunitEnvVar(string $name, string $expectedValue): bool
    {
        $xml = $this->getPhpunitXml();

        if ($xml === null || $xml === false) {
            return false;
        }

        foreach ($xml->php->env as $env) {
            $attrs = $env->attributes();
            if ((string) $attrs['name'] === $name && (string) $attrs['value'] === $expectedValue) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string,mixed>
     */
    protected function getGitlabCiData(): array
    {
        $ciFile = base_path('/.gitlab-ci.yml');

        if (!file_exists($ciFile)) {
            $this->addComment('GitLab CI configuration missing: Create .gitlab-ci.yml in project root');

            throw new \RuntimeException();
        }

        return Yaml::parseFile($ciFile);
    }

    /**
     * @return array<string,mixed>|null
     */
    protected function getDdevConfig(): ?array
    {
        $ddevConfigFile = base_path('.ddev/config.yaml');

        if (!file_exists($ddevConfigFile)) {
            $this->addComment('DDEV configuration missing: .ddev/config.yaml not found');

            return null;
        }

        return Yaml::parseFile($ddevConfigFile);
    }

    /**
     * @return array<string,mixed>|null
     */
    protected function getReleaseItConfig(): ?array
    {
        $releaseItFile = base_path('.release-it.json');

        if (!file_exists($releaseItFile)) {
            $this->addComment('Release-it configuration missing: Create .release-it.json in project root');

            return null;
        }

        return json_decode(
            file_get_contents($releaseItFile) ?: throw new \RuntimeException(),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
    }

    // === Schedule Helpers ===

    protected function hasScheduleEntry(string $command): bool
    {
        $this->addComment('Schedule check: '.$command);

        foreach (Schedule::events() as $event) {
            if (str_contains($event->command ?? '', $command)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Checks if a package is installed and has required schedule entries
     *
     * @param  string|list<string>  $scheduleCommands
     */
    protected function checkPackageWithSchedule(
        string $package,
        string|array $scheduleCommands,
    ): CheckResult {
        if (!$this->checkComposerPackages($package)) {
            return CheckResult::WARN;
        }

        $commands = is_string($scheduleCommands) ? [$scheduleCommands] : $scheduleCommands;

        foreach ($commands as $command) {
            if (!$this->hasScheduleEntry($command)) {
                return CheckResult::FAIL;
            }
        }

        return CheckResult::PASS;
    }
}

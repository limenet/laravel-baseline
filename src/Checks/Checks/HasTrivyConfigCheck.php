<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractCiJobCheck;
use Limenet\LaravelBaseline\Checks\FixableInterface;
use Limenet\LaravelBaseline\Enums\CheckResult;
use Symfony\Component\Yaml\Yaml;

class HasTrivyConfigCheck extends AbstractCiJobCheck implements FixableInterface
{
    public function check(): CheckResult
    {
        return $this->fix(dry: true);
    }

    public function fix(bool $dry = false): CheckResult
    {
        $ciResult = $this->checkRequiredCiJobs();

        if ($ciResult !== CheckResult::PASS) {
            if ($dry) {
                return $ciResult;
            }

            $ciFile = base_path('.gitlab-ci.yml');

            if (file_exists($ciFile)) {
                $ciData = $this->getGitlabCiData() ?? [];
                $changedCi = false;

                foreach ($this->requiredCiJobs() as $jobName => $templates) {
                    if (isset($ciData[$jobName])) {
                        continue;
                    }

                    $ciData[$jobName] = ['extends' => [$templates[0]]];
                    $changedCi = true;
                }

                if ($changedCi) {
                    file_put_contents($ciFile, Yaml::dump($ciData, 4, 2));
                }
            }
        }

        $gitignoreResult = $this->ensureGitignoreEntry(
            $this->policy()->string('trivy.gitignoreEntry'),
            'ignore the Trivy cache directory',
            $dry,
        );

        if ($gitignoreResult !== null && $dry) {
            return $gitignoreResult;
        }

        $ignoreFile = $this->policy()->string('trivy.ignoreFile');

        $ignoreFileResult = $this->ensureFileExists(
            $ignoreFile,
            '',
            $dry,
            "Missing ignore file: create {$ignoreFile} in project root (an empty file is acceptable)",
        );

        if ($ignoreFileResult !== null && $dry) {
            return $ignoreFileResult;
        }

        $configFile = $this->policy()->string('trivy.configFile');
        $trivyFile = base_path($configFile);

        if (!file_exists($trivyFile)) {
            if ($dry) {
                $this->addComment("{$configFile} not found");

                return CheckResult::FAIL;
            }

            file_put_contents($trivyFile, $this->canonicalConfig());

            return $this->fix(dry: true);
        }

        $trivyConfig = $this->loadYamlConfig($configFile);

        if ($trivyConfig === null) {
            return CheckResult::FAIL;
        }

        $changed = false;

        foreach ($this->policy()->strings('trivy.forbiddenKeys') as $forbidden) {
            if (!array_key_exists($forbidden, $trivyConfig)) {
                continue;
            }

            $this->addComment("Forbidden key in {$configFile}: '{$forbidden}' must not be set (use Trivy's default severity behavior)");

            if ($dry) {
                return CheckResult::FAIL;
            }

            unset($trivyConfig[$forbidden]);
            $changed = true;
        }

        foreach ($this->requirements(Yaml::parse($this->canonicalConfig())) as [$path, $expected]) {
            $dotted = implode('.', $path);

            $result = is_array($expected)
                ? $this->ensureListSubset($trivyConfig, $path, $expected, $dotted, $dry, $changed, $configFile)
                : $this->ensureScalar($trivyConfig, $path, $expected, $dotted, $dry, $changed, $configFile);

            if ($result !== null && $dry) {
                return $result;
            }
        }

        if ($dry) {
            return CheckResult::PASS;
        }

        if ($changed) {
            file_put_contents($trivyFile, Yaml::dump($trivyConfig, 4, 2));
        }

        return $this->fix(dry: true);
    }

    protected function requiredCiJobs(): array
    {
        return $this->policy()->stringListMap('trivy.ciJob');
    }

    /**
     * The canonical file body, written verbatim into a project that has none.
     */
    private function canonicalConfig(): string
    {
        return $this->policy()->template($this->policy()->string('trivy.template'));
    }

    /**
     * The canonical config read as a requirement set: every scalar leaf must be
     * equal in the project's file, every list leaf contained in it. Keys the
     * template does not mention are the project's own business.
     *
     * @param  array<string,mixed>  $node
     * @param  list<string>  $prefix
     * @return list<array{0: list<string>, 1: list<string>|scalar|null}>
     */
    private function requirements(array $node, array $prefix = []): array
    {
        $requirements = [];

        foreach ($node as $key => $value) {
            $path = [...$prefix, (string) $key];

            if (is_array($value)) {
                if (!array_is_list($value)) {
                    $requirements = [...$requirements, ...$this->requirements($value, $path)];

                    continue;
                }

                $requirements[] = [$path, array_map(strval(...), $value)];

                continue;
            }

            $requirements[] = [$path, $value];
        }

        return $requirements;
    }

    /**
     * @param  array<string,mixed>  $config
     * @param  list<string>  $path
     */
    private function ensureScalar(array &$config, array $path, mixed $expected, string $dotted, bool $dry, bool &$changed, string $configFile): ?CheckResult
    {
        $current = $this->getByPath($config, $path);

        if ($current === $expected) {
            return null;
        }

        $rendered = is_bool($expected) ? ($expected ? 'true' : 'false') : "'{$expected}'";
        $this->addComment("Invalid value in {$configFile}: '{$dotted}' must equal {$rendered}");

        if ($dry) {
            return CheckResult::FAIL;
        }

        $this->setByPath($config, $path, $expected);
        $changed = true;

        return CheckResult::FAIL;
    }

    /**
     * @param  array<string,mixed>  $config
     * @param  list<string>  $path
     * @param  list<string>  $required
     */
    private function ensureListSubset(array &$config, array $path, array $required, string $dotted, bool $dry, bool &$changed, string $configFile): ?CheckResult
    {
        $current = $this->getByPath($config, $path);
        $currentList = is_array($current) ? $current : [];
        $missing = array_values(array_diff($required, $currentList));

        if ($missing === []) {
            return null;
        }

        $this->addComment("Missing entries in {$configFile}: {$dotted} must include ".implode(', ', $missing));

        if ($dry) {
            return CheckResult::FAIL;
        }

        $this->setByPath($config, $path, array_values(array_merge($currentList, $missing)));
        $changed = true;

        return CheckResult::FAIL;
    }

    private function ensureFileExists(string $relative, string $defaultContent, bool $dry, string $missingComment): ?CheckResult
    {
        $file = base_path($relative);

        if (file_exists($file)) {
            return null;
        }

        $this->addComment($missingComment);

        if ($dry) {
            return CheckResult::FAIL;
        }

        file_put_contents($file, $defaultContent);

        return CheckResult::FAIL;
    }

    /**
     * @param  array<string,mixed>  $config
     * @param  list<string>  $path
     */
    private function getByPath(array $config, array $path): mixed
    {
        $current = $config;

        foreach ($path as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return null;
            }

            $current = $current[$segment];
        }

        return $current;
    }

    /**
     * @param  array<string,mixed>  $config
     * @param  list<string>  $path
     */
    private function setByPath(array &$config, array $path, mixed $value): void
    {
        $ref = &$config;

        foreach ($path as $segment) {
            if (!isset($ref[$segment]) || !is_array($ref[$segment])) {
                $ref[$segment] = [];
            }

            $ref = &$ref[$segment];
        }

        $ref = $value;
    }
}

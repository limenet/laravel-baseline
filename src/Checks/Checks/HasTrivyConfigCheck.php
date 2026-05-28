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

                if (!isset($ciData['security'])) {
                    $ciData['security'] = ['extends' => ['.lint_security']];
                    file_put_contents($ciFile, Yaml::dump($ciData, 4, 2));
                }
            }
        }

        $gitignoreResult = $this->ensureGitignoreEntry('.trivycache/', $dry);

        if ($gitignoreResult !== null && $dry) {
            return $gitignoreResult;
        }

        $ignoreFileResult = $this->ensureFileExists(
            '.trivyignore.yaml',
            '',
            $dry,
            'Missing ignore file: create .trivyignore.yaml in project root (an empty file is acceptable)',
        );

        if ($ignoreFileResult !== null && $dry) {
            return $ignoreFileResult;
        }

        $trivyFile = base_path('trivy.yaml');

        if (!file_exists($trivyFile)) {
            if ($dry) {
                $this->addComment('trivy.yaml not found');

                return CheckResult::FAIL;
            }

            file_put_contents($trivyFile, Yaml::dump($this->canonicalConfig(), 4, 2));

            return $this->fix(dry: true);
        }

        $trivyConfig = $this->loadYamlConfig('trivy.yaml');

        if ($trivyConfig === null) {
            return CheckResult::FAIL;
        }

        $changed = false;

        if (array_key_exists('severity', $trivyConfig)) {
            $this->addComment("Forbidden key in trivy.yaml: 'severity' must not be set (use Trivy's default severity behavior)");

            if ($dry) {
                return CheckResult::FAIL;
            }

            unset($trivyConfig['severity']);
            $changed = true;
        }

        $scalarRules = [
            [['ignorefile'], '.trivyignore.yaml', 'ignorefile'],
            [['cache', 'dir'], '.trivycache', 'cache.dir'],
            [['scan', 'disable-telemetry'], true, 'scan.disable-telemetry'],
            [['disable-vex-notice'], true, 'disable-vex-notice'],
            [['dependency-tree'], true, 'dependency-tree'],
        ];

        foreach ($scalarRules as [$path, $expected, $dotted]) {
            $result = $this->ensureScalar($trivyConfig, $path, $expected, $dotted, $dry, $changed);

            if ($result !== null && $dry) {
                return $result;
            }
        }

        $listRules = [
            [['scan', 'skip-files'], ['.env', 'vendor/**/Dockerfile'], 'scan.skip-files'],
            [['scan', 'skip-dirs'], ['.ddev/', 'storage/logs/'], 'scan.skip-dirs'],
            [['scan', 'scanners'], ['misconfig', 'secret', 'vuln'], 'scan.scanners'],
        ];

        foreach ($listRules as [$path, $required, $dotted]) {
            $result = $this->ensureListSubset($trivyConfig, $path, $required, $dotted, $dry, $changed);

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
        return ['security' => '.lint_security'];
    }

    /**
     * @return array<string,mixed>
     */
    private function canonicalConfig(): array
    {
        return [
            'ignorefile' => '.trivyignore.yaml',
            'cache' => ['dir' => '.trivycache'],
            'scan' => [
                'skip-files' => ['.env', 'vendor/**/Dockerfile'],
                'skip-dirs' => ['.ddev/', 'storage/logs/'],
                'scanners' => ['misconfig', 'secret', 'vuln'],
                'disable-telemetry' => true,
            ],
            'disable-vex-notice' => true,
            'dependency-tree' => true,
        ];
    }

    /**
     * @param  array<string,mixed>  $config
     * @param  list<string>  $path
     */
    private function ensureScalar(array &$config, array $path, mixed $expected, string $dotted, bool $dry, bool &$changed): ?CheckResult
    {
        $current = $this->getByPath($config, $path);

        if ($current === $expected) {
            return null;
        }

        $rendered = is_bool($expected) ? ($expected ? 'true' : 'false') : "'{$expected}'";
        $this->addComment("Invalid value in trivy.yaml: '{$dotted}' must equal {$rendered}");

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
    private function ensureListSubset(array &$config, array $path, array $required, string $dotted, bool $dry, bool &$changed): ?CheckResult
    {
        $current = $this->getByPath($config, $path);
        $currentList = is_array($current) ? $current : [];
        $missing = array_values(array_diff($required, $currentList));

        if ($missing === []) {
            return null;
        }

        $this->addComment("Missing entries in trivy.yaml: {$dotted} must include ".implode(', ', $missing));

        if ($dry) {
            return CheckResult::FAIL;
        }

        $this->setByPath($config, $path, array_values(array_merge($currentList, $missing)));
        $changed = true;

        return CheckResult::FAIL;
    }

    private function ensureGitignoreEntry(string $entry, bool $dry): ?CheckResult
    {
        $file = base_path('.gitignore');

        if (!file_exists($file)) {
            $this->addComment("Missing .gitignore in project root: create it and add '{$entry}'");

            if ($dry) {
                return CheckResult::FAIL;
            }

            file_put_contents($file, $entry."\n");

            return CheckResult::FAIL;
        }

        $contents = (string) file_get_contents($file);
        $lines = array_map('trim', explode("\n", $contents));
        $normalizedEntry = trim($entry, '/');
        $normalizedLines = array_map(static fn (string $line): string => trim($line, '/'), $lines);

        if (in_array($normalizedEntry, $normalizedLines, true)) {
            return null;
        }

        $this->addComment("Missing entry in .gitignore: add '{$entry}' to ignore the Trivy cache directory");

        if ($dry) {
            return CheckResult::FAIL;
        }

        $prefix = ($contents === '' || str_ends_with($contents, "\n")) ? '' : "\n";
        file_put_contents($file, $contents.$prefix.$entry."\n");

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

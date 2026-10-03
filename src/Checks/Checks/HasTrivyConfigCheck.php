<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractCiJobCheck;
use Limenet\LaravelBaseline\Checks\FixableInterface;
use Limenet\LaravelBaseline\Enums\CheckResult;
use Limenet\LaravelBaseline\Project\Profile;
use Limenet\LaravelBaseline\Support\YamlTextEditor;
use Symfony\Component\Yaml\Yaml;

class HasTrivyConfigCheck extends AbstractCiJobCheck implements FixableInterface
{
    /**
     * The text edits matching each change made to the decoded trivy.yaml, so
     * the file can be updated in place rather than re-dumped.
     *
     * @var list<callable(YamlTextEditor): void>
     */
    private array $yamlEdits = [];

    public static function profiles(): array
    {
        return Profile::cases();
    }

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

            $ciFile = $this->path('.gitlab-ci.yml');
            $ciData = file_exists($ciFile) ? $this->getGitlabCiData() : null;

            // A file that could not be read is never rewritten: treating it as
            // empty would replace every job in it with the one being added.
            if ($ciData !== null) {
                $this->appendMissingCiJobs($ciFile, $ciData);
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
        $trivyFile = $this->path($configFile);

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
        $this->yamlEdits = [];

        foreach ($this->policy()->strings('trivy.forbiddenKeys') as $forbidden) {
            if (!array_key_exists($forbidden, $trivyConfig)) {
                continue;
            }

            $this->addComment("Forbidden key in {$configFile}: '{$forbidden}' must not be set (use Trivy's default severity behavior)");

            if ($dry) {
                return CheckResult::FAIL;
            }

            unset($trivyConfig[$forbidden]);
            $this->yamlEdits[] = fn (YamlTextEditor $editor) => $editor->remove([$forbidden]);
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
            $editor = new YamlTextEditor((string) file_get_contents($trivyFile));

            foreach ($this->yamlEdits as $edit) {
                $edit($editor);
            }

            // A layout the editor cannot follow falls back to a full dump, which
            // loses the file's comments but never its data.
            file_put_contents($trivyFile, $editor->matches($trivyConfig) ? $editor->contents() : Yaml::dump($trivyConfig, 4, 2));
        }

        return $this->fix(dry: true);
    }

    protected function requiredCiJobs(): array
    {
        return $this->policy()->stringListMap('trivy.ciJob');
    }

    /**
     * Appends each missing job as text instead of dumping the parsed file
     * back: a dump would drop every comment, anchor and `!reference` tag.
     *
     * @param  array<string,mixed>  $ciData
     */
    private function appendMissingCiJobs(string $ciFile, array $ciData): void
    {
        $contents = (string) file_get_contents($ciFile);
        $appended = '';

        foreach ($this->requiredCiJobs() as $jobName => $templates) {
            if (!array_key_exists($jobName, $ciData)) {
                $appended .= "\n{$jobName}:\n  extends:\n    - {$templates[0]}\n";
            }
        }

        if ($appended === '') {
            return;
        }

        $separator = $contents === '' || str_ends_with($contents, "\n") ? '' : "\n";

        file_put_contents($ciFile, $contents.$separator.$appended);
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
        $this->yamlEdits[] = fn (YamlTextEditor $editor) => $editor->set($path, $expected);
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
        $this->yamlEdits[] = fn (YamlTextEditor $editor) => $editor->append($path, $missing);
        $changed = true;

        return CheckResult::FAIL;
    }

    private function ensureFileExists(string $relative, string $defaultContent, bool $dry, string $missingComment): ?CheckResult
    {
        $file = $this->path($relative);

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

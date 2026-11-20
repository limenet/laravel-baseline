<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractCiJobCheck;
use Limenet\LaravelBaseline\Checks\FixableInterface;
use Limenet\LaravelBaseline\Enums\CheckResult;
use Limenet\LaravelBaseline\Project\Profile;
use Limenet\LaravelBaseline\Support\YamlTextEditor;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * CycloneDX SBOMs uploaded to Dependency-Track through the shared
 * laravel/sbom.yml template: its include, its stage, one job per dependency
 * manifest the project has, and one server job per environment a deploy job
 * deploys to over SSH.
 *
 * Server jobs are matched by environment rather than host: two environments
 * may share the DEPLOY_HOST written in the file and differ only in their
 * environment-scoped CI/CD variables, which also hold SSH_KNOWN_HOSTS — so a
 * server job has to run in the environment, with an action that records no
 * deployment.
 */
class UploadsSbomsCheck extends AbstractCiJobCheck implements FixableInterface
{
    private const CI_FILE = '.gitlab-ci.yml';

    /** Mirrors the check the template runs on SBOM_SERVER_NAME. */
    private const SERVER_NAME_PATTERN = '/^[a-z0-9-]+$/';

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
        $data = $this->getGitlabCiData();

        if ($data === null) {
            return CheckResult::FAIL;
        }

        $file = $this->path(self::CI_FILE);
        $original = (string) file_get_contents($file);
        $editor = $dry ? null : new YamlTextEditor($original);
        $appended = [];

        if (!$this->ensureInclude($data, $editor) && $dry) {
            return CheckResult::FAIL;
        }

        if (!$this->ensureStage($data, $editor) && $dry) {
            return CheckResult::FAIL;
        }

        if (!$this->ensureAppJobs($data, $appended) && $dry) {
            return CheckResult::FAIL;
        }

        if (!$this->ensureServerJobs($data, $editor, $appended) && $dry) {
            return CheckResult::FAIL;
        }

        if ($editor === null) {
            return CheckResult::PASS;
        }

        $contents = $editor->contents();

        if ($appended !== []) {
            $separator = $contents === '' || str_ends_with($contents, "\n") ? '' : "\n";
            $contents .= $separator.implode('', $appended);
        }

        if ($contents === $original) {
            return $this->fix(dry: true);
        }

        // Never write a file the edits broke: the next run could not read it
        // to say so, and GitLab would refuse the pipeline.
        try {
            Yaml::parse($contents, Yaml::PARSE_CUSTOM_TAGS);
        } catch (ParseException) {
            $this->addComment('Could not update '.self::CI_FILE.' automatically: apply the changes above by hand');

            return CheckResult::FAIL;
        }

        file_put_contents($file, $contents);

        return $this->fix(dry: true);
    }

    /**
     * The app jobs whose manifest exists: a project without package.json has
     * no npm dependencies to describe.
     */
    protected function requiredCiJobs(): array
    {
        $manifests = $this->policy()->stringMap('sbom.jobManifests');

        return array_filter(
            $this->policy()->stringListMap('sbom.jobs'),
            fn (string $job): bool => !isset($manifests[$job]) || file_exists($this->path($manifests[$job])),
            ARRAY_FILTER_USE_KEY,
        );
    }

    /**
     * @param  array<array-key,mixed>  $job
     */
    private static function environmentName(array $job): ?string
    {
        $environment = $job['environment'] ?? null;

        return is_string($environment) ? $environment : self::scalar(is_array($environment) ? ($environment['name'] ?? null) : null);
    }

    /**
     * @param  array<array-key,mixed>  $job
     */
    private static function environmentAction(array $job): ?string
    {
        $environment = $job['environment'] ?? null;

        return is_array($environment) ? self::scalar($environment['action'] ?? null) : null;
    }

    private static function scalar(mixed $value): ?string
    {
        return is_scalar($value) && !is_bool($value) ? (string) $value : null;
    }

    /**
     * @param  array<string,mixed>  $data
     */
    private function ensureInclude(array $data, ?YamlTextEditor $editor): bool
    {
        $project = $this->policy()->string('sbom.include.project');
        $templateFile = $this->policy()->string('sbom.include.file');
        $include = $data['include'] ?? null;

        $entries = is_array($include) && array_is_list($include) ? $include : [$include];

        foreach ($entries as $entry) {
            if (!is_array($entry) || ($entry['project'] ?? null) !== $project) {
                continue;
            }

            $files = is_array($entry['file'] ?? null) ? $entry['file'] : [$entry['file'] ?? null];

            if (in_array($templateFile, $files, true)) {
                return true;
            }
        }

        $this->addComment('Missing include in '.self::CI_FILE.": include file '{$templateFile}' from project '{$project}'");

        if ($include !== null && !(is_array($include) && array_is_list($include))) {
            $this->addComment('Cannot add the include to '.self::CI_FILE." automatically: rewrite 'include' as a list first");

            return false;
        }

        $editor?->appendMapping(['include'], [
            'project' => $project,
            'ref' => $this->policy()->string('sbom.include.ref'),
            'file' => $templateFile,
        ]);

        return false;
    }

    /**
     * @param  array<string,mixed>  $data
     */
    private function ensureStage(array $data, ?YamlTextEditor $editor): bool
    {
        $stage = $this->policy()->string('sbom.stage');
        $stages = $data['stages'] ?? null;

        if (is_array($stages) && in_array($stage, $stages, true)) {
            return true;
        }

        $this->addComment('Missing stage in '.self::CI_FILE.": add '{$stage}' to 'stages'");

        if ($stages === null) {
            // Declaring stages replaces GitLab's implicit ones, which the
            // pipeline's other jobs may still rely on.
            $editor?->append(['stages'], [...$this->policy()->strings('sbom.defaultStages'), $stage]);
        } elseif (is_array($stages) && array_is_list($stages)) {
            $editor?->append(['stages'], [$stage]);
        }

        return false;
    }

    /**
     * @param  array<string,mixed>  $data
     * @param  list<string>  $appended
     */
    private function ensureAppJobs(array $data, array &$appended): bool
    {
        if ($this->checkRequiredCiJobs() === CheckResult::PASS) {
            return true;
        }

        // A job that exists but extends something else is the developer's to
        // reconcile; only a missing one is added.
        foreach ($this->requiredCiJobs() as $job => $templates) {
            if (!array_key_exists($job, $data)) {
                $appended[] = "\n{$job}:\n  extends:\n    - {$templates[0]}\n";
            }
        }

        return false;
    }

    /**
     * @param  array<string,mixed>  $data
     * @param  list<string>  $appended
     */
    private function ensureServerJobs(array $data, ?YamlTextEditor $editor, array &$appended): bool
    {
        $serverTemplate = $this->policy()->string('sbom.server.template');
        $nameVariable = $this->policy()->string('sbom.server.nameVariable');
        $action = $this->policy()->string('sbom.server.environmentAction');
        $globals = is_array($data['variables'] ?? null) ? $data['variables'] : [];

        $servers = array_filter(
            $this->jobs($data),
            fn (array $job): bool => in_array($serverTemplate, $this->extendsOf($job), true),
        );

        $ok = true;
        $taken = array_keys($data);

        foreach ($this->deployTargets($data) as $target) {
            ['job' => $deployJob, 'environment' => $environment, 'variables' => $own] = $target;
            $server = $this->matchServer($servers, $environment, $own, $globals, $nameVariable);

            if ($server === null) {
                $ok = false;
                $this->addComment($environment === null
                    ? 'Missing server SBOM in '.self::CI_FILE.": deploy job '{$deployJob}' needs a job extending {$serverTemplate} with the same DEPLOY_HOST and {$nameVariable} set"
                    : 'Missing server SBOM in '.self::CI_FILE.": deploy environment '{$environment}' (job '{$deployJob}') needs a job extending {$serverTemplate} with environment {name: {$environment}, action: {$action}} and {$nameVariable} set");

                if ($editor !== null) {
                    $name = $this->serverName($environment ?? $deployJob);
                    $jobName = $this->uniqueJobName($this->policy()->string('sbom.server.jobPrefix').str_replace('-', '_', $name), $taken);
                    $taken[] = $jobName;
                    $variables = [];

                    foreach ($own as $variable => $value) {
                        $quote = $editor->quoteAt([$deployJob, 'variables', $variable]);
                        $variables[$variable] = $quote !== null && !str_contains($value, $quote) ? $quote.$value.$quote : Yaml::dump($value);
                    }

                    $variables[$nameVariable] = Yaml::dump($name);
                    $appended[] = $this->renderServerJob($jobName, $serverTemplate, $environment, $action, $variables);
                }

                continue;
            }

            [$serverJob, $job] = $server;

            if ($environment !== null && (self::environmentName($job) !== $environment || self::environmentAction($job) !== $action)) {
                $ok = false;
                $this->addComment("Misconfigured server SBOM job '{$serverJob}' in ".self::CI_FILE.": set environment {name: {$environment}, action: {$action}} so it gets the environment's CI/CD variables without recording a deployment");

                if ($editor !== null) {
                    if (is_string($job['environment'] ?? null)) {
                        $editor->remove([$serverJob, 'environment']);
                    }

                    $editor->set([$serverJob, 'environment', 'name'], $environment);
                    $editor->set([$serverJob, 'environment', 'action'], $action);
                }
            }

            $serverName = self::scalar($job['variables'][$nameVariable] ?? null) ?? self::scalar($globals[$nameVariable] ?? null);

            if ($serverName === null || preg_match(self::SERVER_NAME_PATTERN, $serverName) !== 1) {
                $ok = false;
                $this->addComment("Misconfigured server SBOM job '{$serverJob}' in ".self::CI_FILE.": set {$nameVariable} to lowercase letters, digits and dashes");
            }

            foreach ($own as $variable => $value) {
                if ($this->effectiveVariable($job, $globals, $variable) !== $value) {
                    $ok = false;
                    $this->addComment("Misconfigured server SBOM job '{$serverJob}' in ".self::CI_FILE.": set {$variable} to '{$value}' like deploy job '{$deployJob}'");
                }
            }
        }

        return $ok;
    }

    /**
     * One target per environment the SSH deploy jobs deploy to, or per host for
     * a deploy job without an environment.
     *
     * @param  array<string,mixed>  $data
     * @return list<array{job: string, environment: ?string, variables: array<string,string>}>
     */
    private function deployTargets(array $data): array
    {
        $deployTemplates = $this->policy()->strings('sbom.server.deployTemplates');
        $defaults = $this->policy()->stringMap('sbom.server.deployTemplateEnvironments');
        $connection = $this->policy()->strings('sbom.server.connectionVariables');
        $globals = is_array($data['variables'] ?? null) ? $data['variables'] : [];
        $targets = [];

        foreach ($this->jobs($data) as $name => $job) {
            $templates = array_values(array_intersect($this->extendsOf($job), $deployTemplates));

            if ($templates === []) {
                continue;
            }

            $environment = self::environmentName($job);

            foreach ($templates as $template) {
                $environment ??= $defaults[$template] ?? null;
            }

            $own = [];

            foreach ($connection as $variable) {
                $value = self::scalar($job['variables'][$variable] ?? null);

                if ($value !== null) {
                    $own[$variable] = $value;
                }
            }

            $key = $environment !== null
                ? "environment:{$environment}"
                : 'host:'.($this->effectiveVariable($job, $globals, 'DEPLOY_HOST') ?? '');

            $targets[$key] ??= ['job' => $name, 'environment' => $environment, 'variables' => $own];
        }

        return array_values($targets);
    }

    /**
     * The server job covering a target: the one in its environment, else one
     * outside any environment whose name gives it away (it then only lacks the
     * environment), or for a target without an environment the one on its host.
     *
     * @param  array<string, array<array-key,mixed>>  $servers
     * @param  array<string,string>  $own
     * @param  array<array-key,mixed>  $globals
     * @return array{0: string, 1: array<array-key,mixed>}|null
     */
    private function matchServer(array $servers, ?string $environment, array $own, array $globals, string $nameVariable): ?array
    {
        if ($environment !== null) {
            foreach ($servers as $name => $job) {
                if (self::environmentName($job) === $environment) {
                    return [$name, $job];
                }
            }

            foreach ($servers as $name => $job) {
                if (self::environmentName($job) === null && self::scalar($job['variables'][$nameVariable] ?? null) === $this->serverName($environment)) {
                    return [$name, $job];
                }
            }

            return null;
        }

        $host = $own['DEPLOY_HOST'] ?? self::scalar($globals['DEPLOY_HOST'] ?? null);

        foreach ($servers as $name => $job) {
            if (self::environmentName($job) === null && $this->effectiveVariable($job, $globals, 'DEPLOY_HOST') === $host) {
                return [$name, $job];
            }
        }

        return null;
    }

    /**
     * @param  array<string,string>  $variables  already rendered YAML scalars
     */
    private function renderServerJob(string $jobName, string $template, ?string $environment, string $action, array $variables): string
    {
        $lines = ['', "{$jobName}:", '  extends:', "    - {$template}"];

        if ($environment !== null) {
            $lines = [...$lines, '  environment:', '    name: '.Yaml::dump($environment), "    action: {$action}"];
        }

        $lines[] = '  variables:';

        foreach ($variables as $variable => $value) {
            $lines[] = "    {$variable}: {$value}";
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * The visible jobs: every mapping under a key that is not hidden.
     *
     * @param  array<string,mixed>  $data
     * @return array<string, array<array-key,mixed>>
     */
    private function jobs(array $data): array
    {
        $jobs = [];

        foreach ($data as $name => $job) {
            if (is_string($name) && !str_starts_with($name, '.') && is_array($job)) {
                $jobs[$name] = $job;
            }
        }

        return $jobs;
    }

    /**
     * @param  array<array-key,mixed>  $job
     * @return list<string>
     */
    private function extendsOf(array $job): array
    {
        $extends = $job['extends'] ?? [];

        return array_values(array_filter(is_array($extends) ? $extends : [$extends], is_string(...)));
    }

    /**
     * @param  array<array-key,mixed>  $job
     * @param  array<array-key,mixed>  $globals
     */
    private function effectiveVariable(array $job, array $globals, string $variable): ?string
    {
        return self::scalar($job['variables'][$variable] ?? null) ?? self::scalar($globals[$variable] ?? null);
    }

    /** A valid SBOM_SERVER_NAME derived from an environment or job name. */
    private function serverName(string $from): string
    {
        $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($from)), '-');

        return $slug === '' ? 'server' : $slug;
    }

    /**
     * @param  list<array-key>  $taken
     */
    private function uniqueJobName(string $name, array $taken): string
    {
        $candidate = $name;

        for ($i = 2; in_array($candidate, $taken, true); $i++) {
            $candidate = "{$name}_{$i}";
        }

        return $candidate;
    }
}

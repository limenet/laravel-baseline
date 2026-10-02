<?php

namespace Limenet\LaravelBaseline\Checks;

use Composer\Semver\Intervals;
use Composer\Semver\VersionParser;
use Limenet\LaravelBaseline\Backup\BackupConfigVisitor;
use Limenet\LaravelBaseline\Concerns\CommentManagement;
use Limenet\LaravelBaseline\Enums\CheckResult;
use Limenet\LaravelBaseline\Policy\Policy;
use Limenet\LaravelBaseline\Project\Profile;
use Limenet\LaravelBaseline\Project\Project;
use Limenet\LaravelBaseline\Support\CheckName;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

abstract class AbstractCheck implements CheckInterface
{
    use CommentManagement;

    /**
     * The lowest driftingly/rector-laravel the rector.php this package writes is
     * valid against: 2.6.0 deleted LaravelSetProvider and moved its rules into
     * LaravelSetList::COMPOSER_BASED, and 2.6.1 is the first release requiring
     * the rector/rector ^2.6.4 that goes with it.
     */
    protected const MIN_RECTOR_LARAVEL = '2.6.1';

    public function __construct(
        CommentCollector $commentCollector,
        protected readonly Project $project,
    ) {
        $this->commentCollector = $commentCollector;
    }

    /**
     * Default implementation derives name from class name.
     * e.g., UsesPestCheck -> usesPest
     */
    final public static function name(): string
    {
        return CheckName::fromClass(static::class);
    }

    /**
     * Laravel only unless a check opts in: a check written for a Laravel app
     * that silently ran in a WordPress theme would report against the wrong
     * standard, whereas one missing from a profile is found and opted in.
     *
     * @return list<Profile>
     */
    public static function profiles(): array
    {
        return [Profile::Laravel];
    }

    /**
     * The policy values shared with the npm runner (see policy/policy.json).
     */
    protected function policy(): Policy
    {
        return $this->project->policy();
    }

    /**
     * An absolute path inside the checked project ($this->path() semantics).
     */
    protected function path(string $relative = ''): string
    {
        return $this->project->path($relative);
    }

    protected function profile(): Profile
    {
        return $this->project->profile();
    }

    /**
     * @param  string|list<string>  $packages
     */
    protected function checkComposerPackages(string|array $packages): bool
    {
        $packages = is_string($packages) ? [$packages] : $packages;

        $this->addComment('Composer check: '.implode(', ', $packages));

        foreach ($packages as $package) {
            if (!$this->project->hasComposerPackage($package)) {
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

        foreach ((array) ($composerJson['scripts'][$scriptName] ?? []) as $script) {
            if (is_string($script) && str_contains($script, $match)) {
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
        $composerFile = $this->path('composer.json');

        if (!file_exists($composerFile)) {
            $this->addComment('Composer configuration missing: composer.json not found in project root');

            return null;
        }

        return json_decode(
            file_get_contents($composerFile) ?: throw new \RuntimeException,
            true,
            flags: JSON_THROW_ON_ERROR,
        );
    }

    protected function composerPackageSatisfies(string $package, string $constraint): bool
    {
        $installedConstraint = $this->declaredComposerConstraint($package);

        if ($installedConstraint === null) {
            return false;
        }

        $parser = new VersionParser;

        return Intervals::haveIntersections(
            $parser->parseConstraints($installedConstraint),
            $parser->parseConstraints($constraint),
        );
    }

    /**
     * The constraint composer.json declares for a package, from either require
     * section, or null when it names the package in neither.
     */
    protected function declaredComposerConstraint(string $package): ?string
    {
        $composerJson = $this->getComposerJson();

        if ($composerJson === null) {
            return null;
        }

        $constraint = $composerJson['require'][$package]
            ?? $composerJson['require-dev'][$package]
            ?? null;

        return is_string($constraint) ? $constraint : null;
    }

    /**
     * Whether the constraint declared for a package still admits versions below
     * $version — the question a floor has to ask, since composerPackageSatisfies()
     * only reports whether two ranges overlap anywhere, and `^2.0` overlaps
     * `>=2.6` while still resolving to 2.0.
     *
     * A package composer.json does not name is nobody's floor to violate (it is
     * pulled in transitively, if at all), so that reports false.
     */
    protected function composerPackageAllowsBelow(string $package, string $version): bool
    {
        $declaredConstraint = $this->declaredComposerConstraint($package);

        if ($declaredConstraint === null) {
            return false;
        }

        $parser = new VersionParser;

        return Intervals::haveIntersections(
            $parser->parseConstraints($declaredConstraint),
            $parser->parseConstraints('<'.$version),
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
    protected function checkNpmPackages(
        string|array $packages,
        string $packageType = 'devDependencies',
    ): bool {
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
        $packageFile = $this->path('package.json');

        if (!file_exists($packageFile)) {
            $this->addComment('Package.json missing: Create package.json in project root');

            return null;
        }

        return json_decode(
            file_get_contents($packageFile) ?: throw new \RuntimeException,
            true,
            flags: JSON_THROW_ON_ERROR,
        );
    }

    /**
     * The raw `engines.node` constraint from package.json (e.g. "^22"), or null if absent.
     */
    protected function getPackageJsonNodeVersion(): ?string
    {
        $packageJson = $this->getPackageJson();

        if ($packageJson === null) {
            return null;
        }

        $constraint = $packageJson['engines']['node'] ?? null;

        return is_string($constraint) && $constraint !== '' ? $constraint : null;
    }

    /**
     * The raw, trimmed version from the project's .nvmrc (e.g. "22"), or null if absent/empty.
     */
    protected function getNvmrcNodeVersion(): ?string
    {
        $nvmrcFile = $this->path('.nvmrc');

        if (!file_exists($nvmrcFile)) {
            return null;
        }

        $version = trim(file_get_contents($nvmrcFile) ?: '');

        return $version !== '' ? $version : null;
    }

    /**
     * The raw `engines.npm` constraint from package.json (e.g. "^12"), or null if absent.
     */
    protected function getPackageJsonNpmVersion(): ?string
    {
        $packageJson = $this->getPackageJson();

        if ($packageJson === null) {
            return null;
        }

        $constraint = $packageJson['engines']['npm'] ?? null;

        return is_string($constraint) && $constraint !== '' ? $constraint : null;
    }

    /**
     * Parse the project's .npmrc into a key => value map. Returns [] if the file is absent.
     *
     * @return array<string,string>
     */
    protected function getNpmrc(): array
    {
        $npmrcFile = $this->path('.npmrc');

        if (!file_exists($npmrcFile)) {
            return [];
        }

        $config = [];

        foreach (explode("\n", file_get_contents($npmrcFile) ?: '') as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#') || str_starts_with($line, ';')) {
                continue;
            }

            if (!str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $config[trim($key)] = trim($value);
        }

        return $config;
    }

    // === Config File Helpers ===

    protected function getPhpunitXml(): \SimpleXMLElement|false|null
    {
        $xmlFile = $this->path('/phpunit.xml');

        if (!file_exists($xmlFile)) {
            return null;
        }

        $content = file_get_contents($xmlFile);
        if ($content === false || $content === '') {
            return false;
        }

        return simplexml_load_string($content);
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
     * @return array<string,mixed>|null
     */
    protected function loadYamlConfig(string $relativePath): ?array
    {
        $file = $this->path($relativePath);
        $path = ltrim($relativePath, '/');

        if (!file_exists($file)) {
            $this->addComment("{$path} not found");

            return null;
        }

        try {
            $data = Yaml::parseFile($file);
        } catch (ParseException $e) {
            // A malformed file is a finding, not a crash: the fixable checks
            // re-read the file to verify their own write, so an exception here
            // aborts the entire run instead of reporting the one bad file.
            $this->addComment("{$path} could not be parsed: {$e->getMessage()}");

            return null;
        }

        if (!is_array($data)) {
            $this->addComment("{$path} is empty or invalid");

            return null;
        }

        return $data;
    }

    /**
     * @return array<string,mixed>|null
     */
    protected function getGitlabCiData(): ?array
    {
        return $this->loadYamlConfig('/.gitlab-ci.yml');
    }

    /**
     * @return array<string,mixed>|null
     */
    protected function getDdevConfig(): ?array
    {
        return $this->loadYamlConfig('.ddev/config.yaml');
    }

    /**
     * Sets a top-level scalar key in a YAML file via a targeted text edit,
     * replacing only the matching line (or appending one) instead of
     * re-dumping the whole file — this preserves comments and formatting
     * that a full parse/dump round trip would discard.
     */
    protected function setYamlScalarKey(string $file, string $key, string $value): void
    {
        $content = file_get_contents($file) ?: '';
        $line = "{$key}: {$value}";
        $pattern = '/^'.preg_quote($key, '/').':.*$/m';

        if (preg_match($pattern, $content) === 1) {
            $content = preg_replace($pattern, $line, $content, 1) ?? $content;
        } else {
            $content = $this->insertYamlLineBeforeTrailingComments($content, $line);
        }

        file_put_contents($file, $content);
    }

    /**
     * Appends an item to a top-level YAML list key via a targeted text edit,
     * preserving the list's existing flow/block style and leaving the rest
     * of the file (including comments) untouched. Creates the key in block
     * style if it doesn't exist yet.
     */
    protected function appendToYamlListKey(string $file, string $key, string $item): void
    {
        $content = file_get_contents($file) ?: '';
        $keyPattern = preg_quote($key, '/');

        // Flow style: `key: [a, b]` or `key: []`
        if (preg_match('/^('.$keyPattern.':\s*\[)([^\]]*)(\])/m', $content, $matches) === 1) {
            $items = trim($matches[2]);
            $newItems = $items === '' ? $item : $items.', '.$item;
            $content = preg_replace(
                '/^'.$keyPattern.':\s*\[[^\]]*\]/m',
                $key.': ['.$newItems.']',
                $content,
                1,
            ) ?? $content;
            file_put_contents($file, $content);

            return;
        }

        // Block style: `key:\n  - a\n  - b`
        if (preg_match('/^'.$keyPattern.':\s*$(\n(?:^[ \t]*-.*$\n?)*)/m', $content, $matches, PREG_OFFSET_CAPTURE) === 1) {
            $block = $matches[1][0];
            $insertAt = $matches[1][1] + strlen($block);
            $indent = '  ';

            if (preg_match('/^([ \t]+)-/m', $block, $indentMatch) === 1) {
                $indent = $indentMatch[1];
            }

            $newLine = $indent.'- '.$item."\n";
            $content = substr($content, 0, $insertAt).$newLine.substr($content, $insertAt);
            file_put_contents($file, $content);

            return;
        }

        // Key doesn't exist yet
        $content = $this->insertYamlLineBeforeTrailingComments($content, $key.":\n  - ".$item);
        file_put_contents($file, $content);
    }

    /**
     * @return array<string,mixed>|null
     */
    protected function getReleaseItConfig(): ?array
    {
        $releaseItFile = $this->path('.release-it.json');

        if (!file_exists($releaseItFile)) {
            $this->addComment('Release-it configuration missing: Create .release-it.json in project root');

            return null;
        }

        return json_decode(
            file_get_contents($releaseItFile) ?: throw new \RuntimeException,
            true,
            flags: JSON_THROW_ON_ERROR,
        );
    }

    /**
     * @return array<string|int, mixed>|null
     */
    protected function parsePhpConfigFile(string $relativePath): ?array
    {
        $file = $this->path($relativePath);
        $path = ltrim($relativePath, '/');

        if (!file_exists($file)) {
            $this->addComment("{$path} not found");

            return null;
        }

        $code = file_get_contents($file);
        if ($code === false) {
            $this->addComment("{$path} is unreadable");

            return null;
        }

        $parser = (new ParserFactory)->createForNewestSupportedVersion();

        try {
            $ast = $parser->parse($code);
        } catch (\Throwable) {
            $this->addComment("{$path} could not be parsed");

            return null;
        }

        if ($ast === null) {
            $this->addComment("{$path} could not be parsed");

            return null;
        }

        $visitor = new BackupConfigVisitor;
        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        return $visitor->getConfig();
    }

    // === Fix Helpers ===

    /**
     * @param  array<string, mixed>  $data
     */
    protected function writeComposerJson(array $data): void
    {
        file_put_contents(
            $this->path('composer.json'),
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n",
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function writePackageJson(array $data): void
    {
        file_put_contents(
            $this->path('package.json'),
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n",
        );
    }

    /**
     * Append $value to scripts.$scriptName unless already present (matched by str_contains).
     * If $insertBefore is given, insert before the first entry containing that substring; else append.
     */
    protected function addToComposerScript(
        string $scriptName,
        string $value,
        ?string $insertBefore = null,
    ): void {
        $composerJson = $this->getComposerJson();

        if ($composerJson === null) {
            return;
        }

        // Composer accepts a single command as a plain string.
        $scripts = (array) ($composerJson['scripts'][$scriptName] ?? []);

        foreach ($scripts as $script) {
            if (str_contains($script, $value)) {
                return;
            }
        }

        if ($insertBefore !== null) {
            foreach ($scripts as $i => $script) {
                if (str_contains($script, $insertBefore)) {
                    array_splice($scripts, $i, 0, [$value]);
                    $composerJson['scripts'][$scriptName] = $scripts;
                    $this->writeComposerJson($composerJson);

                    return;
                }
            }
        }

        $composerJson['scripts'][$scriptName] = [...$scripts, $value];
        $this->writeComposerJson($composerJson);
    }

    /**
     * Remove any scripts.$scriptName entries containing $match. Writes composer.json only if something changed.
     */
    protected function removeFromComposerScript(string $scriptName, string $match): void
    {
        $composerJson = $this->getComposerJson();

        if ($composerJson === null) {
            return;
        }

        $scripts = (array) ($composerJson['scripts'][$scriptName] ?? []);
        $filtered = array_values(array_filter($scripts, fn ($script): bool => !str_contains($script, $match)));

        if ($filtered === $scripts) {
            return;
        }

        $composerJson['scripts'][$scriptName] = $filtered;
        $this->writeComposerJson($composerJson);
    }

    /**
     * Remove a package from require/require-dev in composer.json (JSON edit only — does not run composer,
     * so composer.lock/vendor won't be updated; the developer must run `composer update` afterward).
     */
    protected function removeComposerPackage(string $package): void
    {
        $composerJson = $this->getComposerJson();

        if ($composerJson === null) {
            return;
        }

        $changed = false;

        foreach (['require', 'require-dev'] as $section) {
            if (array_key_exists($package, $composerJson[$section] ?? [])) {
                unset($composerJson[$section][$package]);
                $changed = true;
            }
        }

        if ($changed) {
            $this->writeComposerJson($composerJson);
        }
    }

    /**
     * Add or overwrite an env var in the <php> section of phpunit.xml using DOMDocument.
     */
    protected function setPhpunitEnvVar(string $name, string $value): void
    {
        $xmlFile = $this->path('phpunit.xml');

        if (!file_exists($xmlFile)) {
            return;
        }

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;

        if (!$dom->load($xmlFile)) {
            return;
        }

        $xpath = new \DOMXPath($dom);

        // Find existing <env name="$name" ...>
        $existing = $xpath->query("//php/env[@name='{$name}']");

        if ($existing !== false && $existing->length > 0) {
            /** @var \DOMElement $node */
            $node = $existing->item(0);
            $node->setAttribute('value', $value);
        } else {
            // Find or create <php> element
            $phpElements = $dom->getElementsByTagName('php');

            if ($phpElements->length === 0) {
                $root = $dom->documentElement;
                $phpEl = $dom->createElement('php');
                $root?->appendChild($phpEl);
            } else {
                $phpEl = $phpElements->item(0);
            }

            $env = $dom->createElement('env');
            $env->setAttribute('name', $name);
            $env->setAttribute('value', $value);
            $phpEl?->appendChild($env);
        }

        $dom->save($xmlFile);
    }

    // === Gitignore Helpers ===

    /**
     * Ensure an entry exists in the project .gitignore, creating or appending to the file
     * unless this is a dry run.
     *
     * Returns null when the entry is already present, otherwise the failing result.
     */
    protected function ensureGitignoreEntry(string $entry, string $reason, bool $dry): ?CheckResult
    {
        $file = $this->path('.gitignore');

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

        $this->addComment("Missing entry in .gitignore: add '{$entry}' to {$reason}");

        if ($dry) {
            return CheckResult::FAIL;
        }

        $prefix = ($contents === '' || str_ends_with($contents, "\n")) ? '' : "\n";
        file_put_contents($file, $contents.$prefix.$entry."\n");

        return CheckResult::FAIL;
    }

    /**
     * Inserts a line before the file's trailing block of comment/blank
     * lines (DDEV appends a large commented-out documentation block after
     * the live keys), so newly added keys land alongside the other live
     * config instead of after all the documentation. Falls back to
     * appending at the end when there's no such trailing block.
     */
    private function insertYamlLineBeforeTrailingComments(string $content, string $line): string
    {
        $lines = explode("\n", rtrim($content, "\n"));
        $insertAt = count($lines);

        for ($i = count($lines) - 1; $i >= 0; $i--) {
            $trimmed = trim($lines[$i]);

            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                $insertAt = $i;

                continue;
            }

            break;
        }

        array_splice($lines, $insertAt, 0, [$line]);

        return implode("\n", $lines)."\n";
    }
}

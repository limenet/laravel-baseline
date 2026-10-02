<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Enums\CheckResult;

/**
 * Every PHP file the project owns sits under PHPStan's parameters.paths, or
 * is deliberately excluded via excludePaths. NEON is read as YAML, and
 * `includes` are not followed, so paths set in an included file are not seen.
 */
class PhpstanCoversAllPhpFilesCheck extends AbstractCoversAllPhpFilesCheck
{
    /**
     * Prefixes PHPStan resolves to the project root.
     */
    private const ROOT_PREFIXES = ['%currentWorkingDirectory%/', '%rootDir%/../../../'];

    public function check(): CheckResult
    {
        $configFile = $this->phpstanConfigFile();

        if ($configFile === null) {
            $this->addComment('No PHPStan configuration (phpstan.neon) to check the analysed paths of');

            return CheckResult::WARN;
        }

        $config = $this->loadNeonConfig($configFile);

        if ($config === null) {
            return CheckResult::FAIL;
        }

        $parameters = is_array($config['parameters'] ?? null) ? $config['parameters'] : [];
        $uncovered = $this->uncoveredFiles(
            $this->pathList($parameters['paths'] ?? []),
            $this->excludedPaths($parameters['excludePaths'] ?? []),
        );

        if ($uncovered === []) {
            return CheckResult::PASS;
        }

        $this->reportUncovered(
            'PHPStan',
            $uncovered,
            "add them (or their directory) to parameters.paths in {$configFile}, or to excludePaths if leaving them unanalysed is deliberate",
        );

        return CheckResult::FAIL;
    }

    /**
     * excludePaths is either a list or split into analyse / analyseAndScan.
     *
     * @return list<string>
     */
    private function excludedPaths(mixed $excludePaths): array
    {
        if (!is_array($excludePaths)) {
            return [];
        }

        if (array_is_list($excludePaths)) {
            return $this->pathList($excludePaths);
        }

        return [
            ...$this->pathList($excludePaths['analyse'] ?? []),
            ...$this->pathList($excludePaths['analyseAndScan'] ?? []),
        ];
    }

    /**
     * @return list<string>
     */
    private function pathList(mixed $paths): array
    {
        if (!is_array($paths)) {
            return [];
        }

        $list = [];

        foreach ($paths as $path) {
            if (!is_string($path)) {
                continue;
            }

            // "path (?)" marks an optional path that may not exist.
            $path = (string) preg_replace('/\s*\(\?\)$/', '', $path);

            foreach (self::ROOT_PREFIXES as $prefix) {
                if (str_starts_with($path, $prefix)) {
                    $path = substr($path, strlen($prefix));
                }
            }

            $list[] = $path;
        }

        return $list;
    }
}

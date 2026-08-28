<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractFixableCheck;
use Limenet\LaravelBaseline\Checks\CheckRegistry;
use Limenet\LaravelBaseline\Enums\CheckResult;
use Limenet\LaravelBaseline\PhpFile\PhpFileWriter;

/**
 * An exclude naming a check this package no longer registers silences nothing:
 * the name is matched against the registry, so once a check is renamed or
 * removed the entry is dead config that outlives the reason it was added.
 */
class DoesNotExcludeUnknownChecksCheck extends AbstractFixableCheck
{
    public function fix(bool $dry = false): CheckResult
    {
        $path = config_path('baseline.php');
        $config = $this->readConfig($path);
        $excludes = $config['excludes'] ?? [];

        if (!is_array($excludes) || $excludes === []) {
            return CheckResult::PASS;
        }

        $known = array_map(
            fn (string $class): string => $class::name(),
            CheckRegistry::all(),
        );

        $live = [];
        $dead = [];

        foreach ($excludes as $name) {
            if (is_string($name) && in_array($name, $known, true)) {
                $live[] = $name;

                continue;
            }

            $dead[] = is_string($name) ? $name : get_debug_type($name);
        }

        if ($dead === []) {
            return CheckResult::PASS;
        }

        foreach ($dead as $name) {
            $this->addComment(sprintf(
                'Remove "%s" from the excludes in config/baseline.php: no check by that name is registered, so the entry excludes nothing',
                $name,
            ));
        }

        if ($dry) {
            return CheckResult::FAIL;
        }

        $config['excludes'] = $live;
        PhpFileWriter::writeConfig($path, $config);

        return $this->fix(dry: true);
    }

    /**
     * Read config/baseline.php from disk rather than through config(), the same
     * way PeriodicStateManager does: the fix rewrites that file, and a cached
     * config would report state the file no longer holds.
     *
     * @return array<string,mixed>
     */
    private function readConfig(string $path): array
    {
        if (!file_exists($path)) {
            return [];
        }

        $config = require $path;

        return is_array($config) ? $config : [];
    }
}

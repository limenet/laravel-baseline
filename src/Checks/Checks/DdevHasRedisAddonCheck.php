<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;
use Symfony\Component\Yaml\Yaml;

class DdevHasRedisAddonCheck extends AbstractCheck
{
    private const MIN_VERSION = '2.2.0';

    public function check(): CheckResult
    {
        $manifestFile = base_path('.ddev/addon-metadata/redis/manifest.yaml');

        if (!file_exists($manifestFile)) {
            $this->addComment('DDEV Redis addon not installed: Install with "ddev add-on get ddev/ddev-redis"');

            return CheckResult::FAIL;
        }

        $manifest = Yaml::parseFile($manifestFile);

        if (!is_array($manifest)) {
            $this->addComment('DDEV Redis addon manifest is empty or invalid: Check .ddev/addon-metadata/redis/manifest.yaml');

            return CheckResult::FAIL;
        }

        $version = $manifest['version'] ?? null;

        if ($version === null) {
            $this->addComment('DDEV Redis addon version missing: "version" field not found in .ddev/addon-metadata/redis/manifest.yaml');

            return CheckResult::FAIL;
        }

        // Strip leading "v" prefix for version comparison
        $normalizedVersion = ltrim($version, 'v');

        if (version_compare($normalizedVersion, self::MIN_VERSION, '<')) {
            $this->addComment(sprintf(
                'DDEV Redis addon version too old: Found %s, requires at least v%s. Update with "ddev add-on get ddev/ddev-redis"',
                $version,
                self::MIN_VERSION,
            ));

            return CheckResult::FAIL;
        }

        return CheckResult::PASS;
    }
}

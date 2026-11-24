<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractFixableCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;
use Limenet\LaravelBaseline\Support\Filesystem;

/**
 * Horizon and Telescope used to publish their compiled dashboard into
 * public/vendor/<name>; newer releases inline the CSS and JS into the page
 * instead and no longer register an assets tag. The published copy is then
 * dead weight that never gets updated again.
 *
 * The installed version is read from composer.lock rather than the declared
 * constraint: deleting the directory under a release that still serves it
 * would break the dashboard, and `^5.0` says nothing about what resolved.
 */
class DoesNotHaveStalePublishedAssetsCheck extends AbstractFixableCheck
{
    public function fix(bool $dry = false): CheckResult
    {
        $locked = $this->lockedVersions();

        if ($locked === null) {
            return CheckResult::PASS;
        }

        $stale = [];

        foreach ($this->policy()->stringMap('publishedAssets.inlinedSince') as $package => $since) {
            $relative = 'public/vendor/'.substr($package, (int) strrpos($package, '/') + 1);
            $absolute = $this->path($relative);

            if (!is_dir($absolute)) {
                continue;
            }

            if (!array_key_exists($package, $locked)) {
                $stale[] = $absolute;
                $this->addComment("Remove {$relative} — {$package} is no longer installed");

                continue;
            }

            $version = $this->normalizeVersion($locked[$package]);

            if ($version === null || version_compare($version, $since, '<')) {
                continue;
            }

            $stale[] = $absolute;
            $this->addComment("Remove {$relative} — {$package} {$version} inlines its dashboard assets (since {$since}), so the published copy is stale and unused");
        }

        if ($stale === []) {
            return CheckResult::PASS;
        }

        if ($dry) {
            return CheckResult::FAIL;
        }

        foreach ($stale as $absolute) {
            is_link($absolute) ? unlink($absolute) : Filesystem::deleteDirectory($absolute);
        }

        return $this->fix(dry: true);
    }

    /**
     * Package name => locked version, or null when there is no readable
     * composer.lock to tell what is actually installed.
     *
     * @return array<string,string>|null
     */
    private function lockedVersions(): ?array
    {
        $file = $this->path('composer.lock');

        if (!is_file($file)) {
            return null;
        }

        $lock = json_decode((string) file_get_contents($file), true);

        if (!is_array($lock)) {
            return null;
        }

        $versions = [];

        foreach (['packages', 'packages-dev'] as $section) {
            if (!is_array($lock[$section] ?? null)) {
                continue;
            }

            foreach ($lock[$section] as $package) {
                if (is_array($package) && is_string($package['name'] ?? null) && is_string($package['version'] ?? null)) {
                    $versions[$package['name']] = $package['version'];
                }
            }
        }

        return $versions;
    }

    /**
     * `v5.29.0` → `5.29.0`; a branch alias such as `5.x-dev` has no comparable
     * version and yields null, so the directory is left alone.
     */
    private function normalizeVersion(string $version): ?string
    {
        return preg_match('/^v?(\d+\.\d+\.\d+)/', $version, $matches) === 1 ? $matches[1] : null;
    }
}

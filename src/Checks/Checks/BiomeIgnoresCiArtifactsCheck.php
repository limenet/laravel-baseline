<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractBiomeIgnoresCheck;
use Limenet\LaravelBaseline\Project\Profile;

/**
 * Biome must not check the files a CI runner leaves in the project root. The
 * GitLab runner extracts a `metadata.json` holding the cache key next to the
 * checkout whenever it restores a cache; a `files.includes` that starts from
 * `**` picks it up, and `biome ci` fails on a file nobody committed — but only
 * on the runs that happened to restore a cache.
 */
class BiomeIgnoresCiArtifactsCheck extends AbstractBiomeIgnoresCheck
{
    public static function profiles(): array
    {
        return Profile::cases();
    }

    protected function ignored(): array
    {
        return $this->policy()->strings('biome.ciArtifacts');
    }

    protected function describe(): string
    {
        return 'CI runner artifacts';
    }

    protected function consequence(): string
    {
        return 'failing `biome ci` whenever the runner restores a cache';
    }
}

<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractBiomeIgnoresCheck;

/**
 * Biome must not check Laravel's JSON translation files. They are keyed by the
 * source string and maintained by translation tooling, not by hand, so
 * Biome's formatter rewriting them only churns diffs against that tooling.
 *
 * A pattern is probed with `en` in place of its `*`, plus every file it
 * currently matches, so an exclusion that only covers some locales still
 * fails while a project without lang files is held to it all the same.
 */
class BiomeIgnoresLaravelLangFilesCheck extends AbstractBiomeIgnoresCheck
{
    protected function ignored(): array
    {
        return $this->policy()->strings('biome.laravelLangFiles');
    }

    protected function describe(): string
    {
        return 'Laravel lang files';
    }

    protected function probes(string $pattern): array
    {
        $probes = [str_replace('*', 'en', $pattern)];
        $root = rtrim($this->path(), '/').'/';

        foreach (glob($root.$pattern) ?: [] as $file) {
            $probes[] = substr($file, strlen($root));
        }

        return $probes;
    }
}

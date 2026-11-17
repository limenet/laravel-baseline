<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractBiomeIgnoresCheck;
use Limenet\LaravelBaseline\Project\Profile;

/**
 * Biome must not check `.ddev`. DDEV writes and owns the files in it, and
 * since 2.6 Biome formats YAML: it re-indents the add-on manifests DDEV
 * generates, which the next `ddev add-on get` writes back.
 *
 * The directory is probed through DDEV's own config file inside it, so an
 * exclusion of its contents (`!.ddev/**`) counts too.
 */
class BiomeIgnoresDdevCheck extends AbstractBiomeIgnoresCheck
{
    public static function profiles(): array
    {
        return Profile::cases();
    }

    protected function ignored(): array
    {
        return $this->policy()->strings('biome.ddev');
    }

    protected function probes(string $pattern): array
    {
        return ["{$pattern}/config.yaml"];
    }

    protected function describe(): string
    {
        return 'DDEV-managed files';
    }

    protected function consequence(): string
    {
        return 'which re-indents the YAML DDEV generates now that Biome 2.6+ formats YAML';
    }
}

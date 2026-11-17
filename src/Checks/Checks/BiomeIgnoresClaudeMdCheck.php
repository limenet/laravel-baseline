<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractBiomeIgnoresCheck;
use Limenet\LaravelBaseline\Project\Profile;

/**
 * Biome must not check CLAUDE.md. Laravel Boost regenerates it on
 * `boost:update`, and since 2.6 Biome formats Markdown, so the two would
 * keep rewriting each other's output.
 */
class BiomeIgnoresClaudeMdCheck extends AbstractBiomeIgnoresCheck
{
    public static function profiles(): array
    {
        return Profile::cases();
    }

    protected function ignored(): array
    {
        return $this->policy()->strings('biome.claudeMd');
    }

    protected function describe(): string
    {
        return 'CLAUDE.md';
    }

    protected function consequence(): string
    {
        return 'which Biome 2.6+ reformats now that it formats Markdown';
    }
}

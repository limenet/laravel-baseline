<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractBiomeIgnoresCheck;
use Limenet\LaravelBaseline\Project\Profile;

/**
 * Biome must not check the installed Claude skills. They are copies the
 * baseline (and Laravel Boost) writes into `.claude/skills`, not code the
 * project owns, and since 2.6 Biome formats Markdown: it inserts a blank line
 * after the frontmatter's opening `---`, which breaks the skill.
 *
 * The directory is probed through a skill file inside it, so an exclusion of
 * its contents (`!.claude/skills/**`) or of Markdown altogether counts too.
 */
class BiomeIgnoresClaudeSkillsCheck extends AbstractBiomeIgnoresCheck
{
    public static function profiles(): array
    {
        return Profile::cases();
    }

    protected function ignored(): array
    {
        return $this->policy()->strings('biome.claudeSkills');
    }

    protected function probes(string $pattern): array
    {
        return ["{$pattern}/example/SKILL.md"];
    }

    protected function describe(): string
    {
        return 'installed Claude skills';
    }

    protected function consequence(): string
    {
        return 'which breaks their frontmatter now that Biome 2.6+ formats Markdown';
    }
}

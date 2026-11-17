import { policy } from '../policy.js'
import { BiomeIgnoresCheck } from './biome-ignores-check.js'

/**
 * Biome must not check the installed Claude skills. They are copies
 * `baseline install-skills` writes into `.claude/skills`, not code the project
 * owns, and since 2.6 Biome formats Markdown: it inserts a blank line after the
 * frontmatter's opening `---`, which breaks the skill.
 *
 * The directory is probed through a skill file inside it, so an exclusion of
 * its contents (`!.claude/skills/**`) or of Markdown altogether counts too.
 */
export class BiomeIgnoresClaudeSkillsCheck extends BiomeIgnoresCheck {
    static override readonly checkName = 'biomeIgnoresClaudeSkills'

    protected ignored(): string[] {
        return policy().strings('biome.claudeSkills')
    }

    protected override probes(pattern: string): string[] {
        return [`${pattern}/example/SKILL.md`]
    }

    protected describe(): string {
        return 'installed Claude skills'
    }

    protected override consequence(): string {
        return 'which breaks their frontmatter now that Biome 2.6+ formats Markdown'
    }
}

import { policy } from '../policy.js'
import { BiomeIgnoresCheck } from './biome-ignores-check.js'

/**
 * Biome must not check CLAUDE.md. Laravel Boost regenerates it on
 * `boost:update`, and since 2.6 Biome formats Markdown, so the two would
 * keep rewriting each other's output.
 */
export class BiomeIgnoresClaudeMdCheck extends BiomeIgnoresCheck {
    static override readonly checkName = 'biomeIgnoresClaudeMd'

    protected ignored(): string[] {
        return policy().strings('biome.claudeMd')
    }

    protected describe(): string {
        return 'CLAUDE.md'
    }

    protected override consequence(): string {
        return 'which Biome 2.6+ reformats now that it formats Markdown'
    }
}

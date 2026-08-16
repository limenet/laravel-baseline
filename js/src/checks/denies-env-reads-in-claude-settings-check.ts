import { readdirSync } from 'node:fs'
import { policy } from '../policy.js'
import type { CheckResult } from './check.js'
import { ClaudeSettingsCheck } from './claude-settings-check.js'

export class DeniesEnvReadsInClaudeSettingsCheck extends ClaudeSettingsCheck {
    static override readonly checkName = 'deniesEnvReadsInClaudeSettings'

    fix(dry = false): CheckResult {
        return this.requireEntries(
            'deny',
            this.requiredDenyEntries(),
            (entry) =>
                `Claude settings: add "${entry}" to permissions.deny in .claude/settings.json — prevents Claude from reading secrets`,
            dry,
        )
    }

    /**
     * The policy's shared entries plus every environment that ships an encrypted
     * file (.env.{environment}.encrypted => deny .env.{environment}).
     *
     * .env.example is intentionally never denied so it stays readable.
     */
    private requiredDenyEntries(): string[] {
        const entries = policy().strings('claude.deny.shared')

        for (const name of readdirSync(this.project.basePath).sort()) {
            if (name.startsWith('.env.') && name.endsWith('.encrypted')) {
                entries.push(`Read(./${name.slice(0, -'.encrypted'.length)})`)
            }
        }

        return entries
    }
}

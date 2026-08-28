import { policy } from '../policy.js'
import type { CheckResult } from './check.js'
import { ClaudeSettingsCheck } from './claude-settings-check.js'

/**
 * Only the shared half of the policy's allow list applies here — the DDEV and
 * artisan entries the PHP runner also requires are unsatisfiable in a project
 * with neither.
 */
export class AllowsToolingInClaudeSettingsCheck extends ClaudeSettingsCheck {
    static override readonly checkName = 'allowsToolingInClaudeSettings'

    fix(dry = false): CheckResult {
        return this.requireEntries(
            'allow',
            policy().strings('claude.allow.shared'),
            (entry) => `Claude settings: add "${entry}" to permissions.allow in .claude/settings.json`,
            dry,
        )
    }
}

import { policy } from '../policy.js'
import type { CheckResult } from './check.js'
import { ClaudeSettingsCheck } from './claude-settings-check.js'

export class RunsCiLintHookInClaudeSettingsCheck extends ClaudeSettingsCheck {
    static override readonly checkName = 'runsCiLintHookInClaudeSettings'

    fix(dry = false): CheckResult {
        // The JS command, not the PHP runner's `ddev composer run ci-lint`.
        const command = policy().string('claude.ciLintHookCommand.js')

        const settings = this.readSettings()
        const stopGroups = settings.hooks?.Stop ?? []

        const present = stopGroups.some((group) =>
            (group.hooks ?? []).some((hook) => hook.command === command),
        )

        if (present) {
            return 'pass'
        }

        this.comment(`Claude settings: add a Stop hook running "${command}" to .claude/settings.json`)

        if (dry) {
            return 'fail'
        }

        settings.hooks = {
            ...settings.hooks,
            Stop: [...stopGroups, { matcher: '', hooks: [{ type: 'command', command }] }],
        }

        this.writeSettings(settings)

        return 'pass'
    }
}

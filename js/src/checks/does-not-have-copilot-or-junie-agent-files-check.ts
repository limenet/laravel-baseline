import { policy } from '../policy.js'
import { type CheckResult, FixableCheck } from './check.js'

export class DoesNotHaveCopilotOrJunieAgentFilesCheck extends FixableCheck {
    static override readonly checkName = 'doesNotHaveCopilotOrJunieAgentFiles'

    fix(dry = false): CheckResult {
        const present: string[] = []

        for (const [path, reason] of Object.entries(policy().stringMap('agentFiles.forbidden'))) {
            if (this.project.exists(path)) {
                present.push(path)
                this.comment(`Remove ${path} — ${reason}`)
            }
        }

        if (present.length === 0) {
            return 'pass'
        }

        if (dry) {
            return 'fail'
        }

        for (const path of present) {
            this.project.remove(path)
        }

        return this.fix(true)
    }
}

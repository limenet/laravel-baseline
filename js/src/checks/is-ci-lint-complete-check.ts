import { policy } from '../policy.js'
import { Check, type CheckResult } from './check.js'

interface PackageJson {
    scripts?: Record<string, string>
}

/**
 * The JS counterpart of the PHP runner's isCiLintComplete: same intent, different
 * toolchain — the linter and type checker must actually run in `ci-lint`, not
 * just be installed.
 */
export class IsCiLintCompleteCheck extends Check {
    static override readonly checkName = 'isCiLintComplete'

    check(): CheckResult {
        const script = this.project.readJson<PackageJson>('package.json')?.scripts?.['ci-lint']

        if (script === undefined) {
            this.comment('Missing ci-lint script in package.json: Add "ci-lint" to scripts section')

            return 'fail'
        }

        for (const required of policy().strings('ciLint.required.js')) {
            if (!script.includes(required)) {
                this.comment(`Incomplete ci-lint script in package.json: it must run "${required}"`)

                return 'fail'
            }
        }

        return 'pass'
    }
}

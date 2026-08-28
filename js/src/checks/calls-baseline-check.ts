import { policy } from '../policy.js'
import { type CheckResult, FixableCheck } from './check.js'

interface PackageJson {
    scripts?: Record<string, string>
}

/**
 * The runner must actually run. The PHP counterpart hooks composer's
 * post-update-cmd; npm has no equivalent lifecycle hook that survives npm 12's
 * script blocking, so the JS project runs it from `ci-lint` instead — the script
 * the CI template and the Claude Stop hook both already invoke.
 */
export class CallsBaselineCheck extends FixableCheck {
    static override readonly checkName = 'callsBaseline'

    fix(dry = false): CheckResult {
        const command = policy().string('ciLint.baselineCommand.js')
        const packageJson = this.project.readJson<PackageJson>('package.json')

        if (packageJson === null) {
            this.comment('Package.json missing: Create package.json in project root')

            return 'fail'
        }

        const script = packageJson.scripts?.['ci-lint']

        if (script?.includes(command)) {
            return 'pass'
        }

        this.comment(`ci-lint must run the baseline: add "${command}" to the ci-lint script in package.json`)

        if (dry) {
            return 'fail'
        }

        packageJson.scripts = {
            ...packageJson.scripts,
            'ci-lint': script === undefined || script.trim() === '' ? command : `${script} && ${command}`,
        }

        this.project.writeJson('package.json', packageJson)

        return this.fix(true)
    }
}

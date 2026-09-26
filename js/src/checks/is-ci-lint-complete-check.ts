import { policy } from '../policy.js'
import { Check, type CheckResult } from './check.js'

interface PackageJson {
    scripts?: Record<string, string>
    dependencies?: Record<string, string>
    devDependencies?: Record<string, string>
}

/**
 * The JS counterpart of the PHP runner's isCiLintComplete: same intent, different
 * toolchain — the linter and type checker must actually run in `ci-lint`, not
 * just be installed.
 *
 * Which linter is the project's choice: every one it installs has to run, and it
 * has to install at least one. The type checker is only required once there is
 * TypeScript for it to check.
 */
export class IsCiLintCompleteCheck extends Check {
    static override readonly checkName = 'isCiLintComplete'

    check(): CheckResult {
        const packageJson = this.project.readJson<PackageJson>('package.json')
        const script = packageJson?.scripts?.['ci-lint']

        if (script === undefined) {
            this.comment('Missing ci-lint script in package.json: Add "ci-lint" to scripts section')

            return 'fail'
        }

        const linters = policy().stringMap('ciLint.linters.js')
        const installed = Object.keys(linters).filter(
            (name) =>
                packageJson?.dependencies?.[name] !== undefined ||
                packageJson?.devDependencies?.[name] !== undefined,
        )

        if (installed.length === 0) {
            this.comment(
                `No linter in package.json: install one of ${Object.keys(linters).join(', ')} and run it in the ci-lint script`,
            )

            return 'fail'
        }

        const required = [
            ...installed.map((name) => linters[name] as string),
            ...policy().strings('ciLint.required.js'),
        ]

        if (this.project.containsFileWithExtension(policy().strings('ciLint.typeChecker.js.extensions'))) {
            required.push(policy().string('ciLint.typeChecker.js.command'))
        }

        for (const command of required) {
            if (!script.includes(command)) {
                this.comment(`Incomplete ci-lint script in package.json: it must run "${command}"`)

                return 'fail'
            }
        }

        return 'pass'
    }
}

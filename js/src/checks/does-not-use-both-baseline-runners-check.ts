import { policy } from '../policy.js'
import { Check, type CheckResult } from './check.js'

interface ComposerJson {
    require?: Record<string, string>
    'require-dev'?: Record<string, string>
}

/**
 * The mirror image of the PHP runner's check of the same name: a project runs
 * one runner, and where both could run, the Composer one wins. It is the larger
 * registry — every check here has a counterpart there, plus the Laravel ones —
 * so this runner is the one that steps aside.
 *
 * Uninstalling is the developer's call, so this detects and reports.
 */
export class DoesNotUseBothBaselineRunnersCheck extends Check {
    static override readonly checkName = 'doesNotUseBothBaselineRunners'

    check(): CheckResult {
        const composerJson = this.project.readJson<ComposerJson>('composer.json')

        if (composerJson === null) {
            return 'pass'
        }

        const composerPackage = policy().string('baseline.composerPackage')

        for (const section of ['require', 'require-dev'] as const) {
            if (composerJson[section]?.[composerPackage] === undefined) {
                continue
            }

            const npmPackage = policy().string('baseline.npmPackage')

            this.comment(
                `Two baseline runners in one project: composer.json requires ${composerPackage} in ${section}, and that runner covers everything this one does and more. Run "npm uninstall ${npmPackage}" and drop its "baseline check" entry from the ci-lint npm script.`,
            )

            return 'fail'
        }

        return 'pass'
    }
}

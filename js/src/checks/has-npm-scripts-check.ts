import { policy } from '../policy.js'
import { Check, type CheckResult } from './check.js'

interface PackageJson {
    scripts?: Record<string, string>
}

export class HasNpmScriptsCheck extends Check {
    static override readonly checkName = 'hasNpmScripts'

    check(): CheckResult {
        const packageJson = this.project.readJson<PackageJson>('package.json')

        if (packageJson === null) {
            this.comment('Package.json missing: Create package.json in project root')

            return 'fail'
        }

        for (const script of policy().strings('npmScripts.required')) {
            if (packageJson.scripts?.[script] === undefined) {
                this.comment(`Missing ${script} script in package.json: Add "${script}" to scripts section`)

                return 'fail'
            }
        }

        return 'pass'
    }
}

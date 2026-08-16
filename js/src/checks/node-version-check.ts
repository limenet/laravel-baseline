import { policy } from '../policy.js'
import { compatible, guaranteesAtLeast, lowestMajor } from '../support/versions.js'
import { type CheckResult, FixableCheck } from './check.js'

interface PackageJson {
    engines?: { node?: string }
}

export class NodeVersionCheck extends FixableCheck {
    static override readonly checkName = 'nodeVersion'

    fix(dry = false): CheckResult {
        const packageJson = this.project.readJson<PackageJson>('package.json')

        if (packageJson === null) {
            this.comment('Package.json missing: Create package.json in project root')

            return 'fail'
        }

        const floor = policy().string('node.minMajor')
        const engines = nonEmpty(packageJson.engines?.node)
        const nvmrc = nonEmpty(this.project.read('.nvmrc')?.trim())

        // Two declared-but-conflicting versions need a human decision — never auto-fixed.
        if (engines !== null && nvmrc !== null && !compatible(engines, nvmrc)) {
            this.comment(
                `Node version mismatch: package.json engines.node (${engines}) and .nvmrc (${nvmrc}) disagree`,
            )

            return 'fail'
        }

        const enginesTooLow = engines !== null && !guaranteesAtLeast(engines, floor)
        const nvmrcTooLow = nvmrc !== null && !guaranteesAtLeast(nvmrc, floor)

        // A below-floor declaration is bumped to the floor; otherwise keep the project's own line.
        const major = enginesTooLow || nvmrcTooLow ? floor : resolveMajor(engines, nvmrc, floor)

        if (engines === null) {
            this.comment(`package.json missing engines.node: add "engines": { "node": "^${major}" }`)

            if (dry) {
                return 'fail'
            }
        } else if (enginesTooLow) {
            this.comment(
                `engines.node (${engines}) allows Node < ${floor}; require Node >= ${floor} (e.g. "^${floor}")`,
            )

            if (dry) {
                return 'fail'
            }
        }

        if (nvmrc === null) {
            this.comment(`.nvmrc missing: create a .nvmrc pinning Node "${major}"`)

            if (dry) {
                return 'fail'
            }
        } else if (nvmrcTooLow) {
            this.comment(`.nvmrc (${nvmrc}) pins Node < ${floor}: set it to "${floor}"`)

            if (dry) {
                return 'fail'
            }
        }

        if (dry) {
            return 'pass'
        }

        if (engines === null || enginesTooLow) {
            packageJson.engines = { ...packageJson.engines, node: `^${major}` }
            this.project.writeJson('package.json', packageJson)
        }

        if (nvmrc === null || nvmrcTooLow) {
            this.project.write('.nvmrc', `${major}\n`)
        }

        return this.fix(true)
    }
}

/** The major to establish: the .nvmrc value, then engines.node, then the floor. */
function resolveMajor(engines: string | null, nvmrc: string | null, floor: string): string {
    for (const candidate of [nvmrc, engines]) {
        if (candidate === null) {
            continue
        }

        const major = lowestMajor(candidate)

        if (major !== null) {
            return major
        }
    }

    return floor
}

function nonEmpty(value: string | undefined): string | null {
    return value === undefined || value === '' ? null : value
}

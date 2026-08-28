import { policy } from '../policy.js'
import { parseNpmrc, upsertNpmrc } from '../support/npmrc.js'
import { guaranteesAtLeast } from '../support/versions.js'
import { type CheckResult, FixableCheck } from './check.js'

interface PackageJson {
    engines?: { npm?: string }
}

export class HardensNpmSupplyChainCheck extends FixableCheck {
    static override readonly checkName = 'hardensNpmSupplyChain'

    fix(dry = false): CheckResult {
        const packageJson = this.project.readJson<PackageJson>('package.json')

        if (packageJson === null) {
            this.comment('Package.json missing: Create package.json in project root')

            return 'fail'
        }

        // npm 12 blocks dependency lifecycle scripts by default and defaults
        // --allow-git/--allow-remote to none, so requiring it hardens installs for free.
        const floor = policy().string('npm.minMajor')
        const constraint = policy().string('npm.constraint')
        const minReleaseAgeDays = policy().number('npm.minReleaseAgeDays')

        const npm = packageJson.engines?.npm ?? null
        const npmTooLow = npm !== null && !guaranteesAtLeast(npm, floor)

        if (npm === null) {
            this.comment(
                `package.json missing engines.npm: add "engines": { "npm": "${constraint}" } (npm ${floor} blocks dependency lifecycle scripts by default)`,
            )

            if (dry) {
                return 'fail'
            }
        } else if (npmTooLow) {
            this.comment(
                `engines.npm (${npm}) allows npm < ${floor}; require npm >= ${floor} (e.g. "${constraint}")`,
            )

            if (dry) {
                return 'fail'
            }
        }

        const npmrcContents = this.project.read('.npmrc')
        const npmrc = parseNpmrc(npmrcContents)

        if (policy().boolean('npm.engineStrict') && npmrc['engine-strict'] !== 'true') {
            this.comment(
                '.npmrc missing engine-strict=true: add "engine-strict=true" so the required npm version is enforced, not just advised',
            )

            if (dry) {
                return 'fail'
            }
        }

        const declared = npmrc['min-release-age']
        const minReleaseAge = declared === undefined || declared === '' ? null : Number.parseInt(declared, 10)

        if (minReleaseAge === null || Number.isNaN(minReleaseAge)) {
            this.comment(
                `.npmrc missing min-release-age: add "min-release-age=${minReleaseAgeDays}" for a ${minReleaseAgeDays}-day dependency install cooldown`,
            )

            if (dry) {
                return 'fail'
            }
        } else if (minReleaseAge < minReleaseAgeDays) {
            this.comment(
                `.npmrc min-release-age (${minReleaseAge}) is below the recommended ${minReleaseAgeDays}-day cooldown`,
            )

            if (dry) {
                return 'fail'
            }
        }

        if (dry) {
            return 'pass'
        }

        if (npm === null || npmTooLow) {
            packageJson.engines = { ...packageJson.engines, npm: constraint }
            this.project.writeJson('package.json', packageJson)
        }

        this.project.write(
            '.npmrc',
            upsertNpmrc(npmrcContents, {
                'engine-strict': 'true',
                'min-release-age': String(minReleaseAgeDays),
            }),
        )

        return this.fix(true)
    }
}

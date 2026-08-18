import { type CheckResult, FixableCheck } from './check.js'

interface PackageJson {
    scripts?: Record<string, string>
    devDependencies?: Record<string, string>
}

interface ReleaseItConfig {
    plugins?: Record<string, unknown>
}

/**
 * The JS counterpart of the PHP runner's usesReleaseIt. Deliberately the
 * opposite assertion about @release-it/bumper: in a Laravel project the bumper
 * writes the version into composer.json, whereas here package.json is already
 * release-it's own source of truth, so a bumper pointed at it is redundant and a
 * bumper pointed at composer.json is meaningless.
 */
export class UsesReleaseItCheck extends FixableCheck {
    static override readonly checkName = 'usesReleaseIt'

    fix(dry = false): CheckResult {
        const packageJson = this.project.readJson<PackageJson>('package.json')

        if (packageJson === null) {
            this.comment('Package.json missing: Create package.json in project root')

            return 'fail'
        }

        if (packageJson.devDependencies?.['release-it'] === undefined) {
            // Installing is the developer's call — this check never runs npm.
            this.comment('Missing release-it: run "npm install --save-dev release-it"')

            return 'fail'
        }

        const bumper =
            this.project.readJson<ReleaseItConfig>('.release-it.json')?.plugins?.['@release-it/bumper']

        if (bumper !== undefined) {
            this.comment(
                "Remove the @release-it/bumper plugin from .release-it.json: release-it already writes package.json, which is this project's source of truth",
            )

            return 'fail'
        }

        const release = packageJson.scripts?.release

        if (release?.includes('release-it')) {
            return 'pass'
        }

        this.comment('Missing release script in package.json: Add "release": "release-it" to scripts section')

        if (dry) {
            return 'fail'
        }

        packageJson.scripts = { ...packageJson.scripts, release: 'release-it' }
        this.project.writeJson('package.json', packageJson)

        return this.fix(true)
    }
}

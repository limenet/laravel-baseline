import { PeriodicCheck } from './periodic-check.js'

export class UpdatesDependenciesCheck extends PeriodicCheck {
    static override readonly checkName = 'updatesDependencies'

    promptDescription(): string {
        return 'Run the `updating-dependencies` skill to update npm dependencies, review changelogs, and check for semver-blocked majors.'
    }
}

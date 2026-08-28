import { readState, STATE_FILE, writeState } from '../state.js'
import { type CheckResult, FixableCheck } from './check.js'
import { checkNames } from './registry.js'

/**
 * An exclude naming a check this runner does not register silences nothing: the
 * name is matched against the registry, so once a check is renamed, removed, or
 * copied over from the Laravel runner's list, the entry is dead config that
 * outlives the reason it was added.
 */
export class DoesNotExcludeUnknownChecksCheck extends FixableCheck {
    static override readonly checkName = 'doesNotExcludeUnknownChecks'

    fix(dry = false): CheckResult {
        const state = readState(this.project)
        const excludes = state.excludes ?? []
        const known = checkNames()
        const dead = excludes.filter((name) => !known.includes(name))

        if (dead.length === 0) {
            return 'pass'
        }

        for (const name of dead) {
            this.comment(
                `Remove "${name}" from the excludes in ${STATE_FILE}: no check by that name is registered, so the entry excludes nothing`,
            )
        }

        if (dry) {
            return 'fail'
        }

        writeState(this.project, {
            ...state,
            excludes: excludes.filter((name) => known.includes(name)),
        })

        return this.fix(true)
    }
}

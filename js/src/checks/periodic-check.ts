import { policy } from '../policy.js'
import { lastRun } from '../state.js'
import { Check, type CheckResult } from './check.js'

/**
 * A requirement that cannot be verified statically because it needs a developer
 * to do something on a schedule. Mirrors src/Checks/AbstractPeriodicCheck.php.
 */
export abstract class PeriodicCheck extends Check {
    /** Shown to the developer by `baseline periodic`. */
    abstract promptDescription(): string

    intervalDays(): number {
        return policy().number('periodic.defaultIntervalDays')
    }

    /** Return false to skip the check entirely — e.g. an optional tool is absent. */
    isApplicable(): boolean {
        return true
    }

    /** Do not override: preconditions belong in isApplicable(). */
    check(): CheckResult {
        const previous = lastRun(this.project, this.name)

        if (previous === null || this.hasExpired(previous)) {
            this.comment('Run `npx baseline periodic` to complete this periodic check')

            return 'fail'
        }

        return 'pass'
    }

    private hasExpired(previous: Date): boolean {
        const due = new Date(previous)
        due.setDate(due.getDate() + this.intervalDays())

        return due.getTime() < Date.now()
    }
}

export function isPeriodic(check: Check): check is PeriodicCheck {
    return check instanceof PeriodicCheck
}

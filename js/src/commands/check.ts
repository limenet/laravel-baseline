import { type CheckResult, CommentCollector, icon, isError, isFixable } from '../checks/check.js'
import { createChecks } from '../checks/registry.js'
import type { Project } from '../project.js'
import { excludedChecks } from '../state.js'

export interface CheckOptions {
    fix: boolean
    verbose: boolean
}

/** Runs every registered check and returns the process exit code. */
export function runCheck(project: Project, options: CheckOptions): number {
    const comments = new CommentCollector()
    const excluded = excludedChecks(project)

    let errors = 0

    for (const check of createChecks(project, comments)) {
        if (excluded.includes(check.name)) {
            console.log(`⚪ ${displayName(check.name)} (excluded)`)

            continue
        }

        comments.reset()

        let fixed = false
        let result: CheckResult

        if (options.fix && isFixable(check)) {
            result = check.check()

            if (isError(result)) {
                comments.reset()
                result = check.fix()
                fixed = !isError(result)
            }
        } else {
            result = check.check()
        }

        if (isError(result)) {
            errors += 1
        }

        const show = isError(result) || options.verbose || fixed

        if (show) {
            console.log(`${fixed ? '🔧' : icon(result)} ${displayName(check.name)}${fixed ? ' (fixed)' : ''}`)

            for (const comment of comments.all()) {
                console.log(`   ${comment}`)
            }

            if (isError(result)) {
                console.log(`   💡 To exclude, add "${check.name}" to excludes in .baseline.json`)
            }
        }
    }

    if (errors !== 0) {
        console.error(`\nBaseline check failed with ${errors} error(s).`)

        return 1
    }

    console.log('\nBaseline check passed!')

    return 0
}

/** nodeVersion => Node Version, matching the PHP runner's ucsplit display. */
function displayName(name: string): string {
    return name.replace(/(?<!^)([A-Z])/g, ' $1').replace(/^./, (first) => first.toUpperCase())
}

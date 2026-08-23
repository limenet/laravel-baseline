import { parseDocument } from 'yaml'
import type { CheckResult } from '../checks/check.js'
import type { Project } from '../project.js'

export const CI_FILE = '.gitlab-ci.yml'

/**
 * The TS counterpart of AbstractCiJobCheck. A function rather than a base class:
 * hasCiJobs is a plain Check and hasTrivyConfig a FixableCheck, and TS has no
 * way to sit one shared base under both.
 */
export function checkRequiredCiJobs(
    project: Project,
    required: Record<string, string[]>,
    comment: (message: string) => void,
): CheckResult {
    const jobs = readCiJobs(project, comment)

    if (jobs === null) {
        return 'fail'
    }

    for (const [job, accepted] of Object.entries(required)) {
        const declared = jobs[job]?.extends

        if (!Array.isArray(declared) || declared.length !== 1 || !accepted.includes(declared[0] as string)) {
            const rendered = accepted.map((template) => `[${template}]`).join(' or ')
            comment(
                `Missing or misconfigured CI job in ${CI_FILE}: Add job '${job}' with 'extends: ${rendered}'`,
            )

            return 'fail'
        }
    }

    return 'pass'
}

/** The parsed pipeline, or null with the reason already commented. */
export function readCiJobs(
    project: Project,
    comment: (message: string) => void,
): Record<string, { extends?: unknown } | undefined> | null {
    const contents = project.read(CI_FILE)

    if (contents === null) {
        comment(`${CI_FILE} not found`)

        return null
    }

    const document = parseDocument(contents)

    if (document.errors.length > 0) {
        comment(`${CI_FILE} could not be parsed: ${document.errors[0]?.message ?? 'invalid YAML'}`)

        return null
    }

    const config: unknown = document.toJS()

    if (typeof config !== 'object' || config === null) {
        comment(`${CI_FILE} is empty or invalid`)

        return null
    }

    return config as Record<string, { extends?: unknown } | undefined>
}

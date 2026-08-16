import { parse } from 'yaml'
import { policy } from '../policy.js'
import { Check, type CheckResult } from './check.js'

/**
 * The same shared GitLab CI templates the Laravel projects extend, minus the
 * php job — see ci.requiredJobs.js in the policy.
 */
export class HasCiJobsCheck extends Check {
    static override readonly checkName = 'hasCiJobs'

    check(): CheckResult {
        const contents = this.project.read('.gitlab-ci.yml')

        if (contents === null) {
            this.comment('.gitlab-ci.yml not found')

            return 'fail'
        }

        let config: unknown

        try {
            config = parse(contents)
        } catch {
            this.comment('.gitlab-ci.yml could not be parsed')

            return 'fail'
        }

        if (typeof config !== 'object' || config === null) {
            this.comment('.gitlab-ci.yml is empty or invalid')

            return 'fail'
        }

        const jobs = config as Record<string, { extends?: unknown } | undefined>

        for (const [job, accepted] of Object.entries(policy().stringListMap('ci.requiredJobs.js'))) {
            const declared = jobs[job]?.extends

            if (
                !Array.isArray(declared) ||
                declared.length !== 1 ||
                !accepted.includes(declared[0] as string)
            ) {
                const rendered = accepted.map((template) => `[${template}]`).join(' or ')
                this.comment(
                    `Missing or misconfigured CI job in .gitlab-ci.yml: Add job '${job}' with 'extends: ${rendered}'`,
                )

                return 'fail'
            }
        }

        return 'pass'
    }
}

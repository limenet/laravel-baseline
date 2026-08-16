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

        for (const [job, template] of Object.entries(policy().stringMap('ci.requiredJobs.js'))) {
            const declared = jobs[job]?.extends

            if (!Array.isArray(declared) || declared.length !== 1 || declared[0] !== template) {
                this.comment(
                    `Missing or misconfigured CI job in .gitlab-ci.yml: Add job '${job}' with 'extends: [${template}]'`,
                )

                return 'fail'
            }
        }

        return 'pass'
    }
}

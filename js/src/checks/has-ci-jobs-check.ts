import { policy } from '../policy.js'
import { checkRequiredCiJobs } from '../support/ci-jobs.js'
import { Check, type CheckResult } from './check.js'

/**
 * The same shared GitLab CI templates the Laravel projects extend, minus the
 * php job — see ci.requiredJobs.js in the policy.
 */
export class HasCiJobsCheck extends Check {
    static override readonly checkName = 'hasCiJobs'

    check(): CheckResult {
        return checkRequiredCiJobs(this.project, policy().stringListMap('ci.requiredJobs.js'), (message) =>
            this.comment(message),
        )
    }
}

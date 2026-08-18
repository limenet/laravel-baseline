import { parseDocument } from 'yaml'
import { policy } from '../policy.js'
import { type CheckResult, FixableCheck } from './check.js'

const CI_FILE = '.gitlab-ci.yml'

/**
 * With no DDEV to derive the Node version from the project, the CI variable is
 * what keeps the pipeline and the developer's machine on the same major — the
 * shared template resolves it.
 */
export class CiSetsNodeVersionCheck extends FixableCheck {
    static override readonly checkName = 'ciSetsNodeVersion'

    fix(dry = false): CheckResult {
        const contents = this.project.read(CI_FILE)

        if (contents === null) {
            this.comment(`${CI_FILE} not found`)

            return 'fail'
        }

        const name = policy().string('ci.nodeVersionVariable.name')
        const value = policy().string('ci.nodeVersionVariable.value')

        // parseDocument() collects syntax errors on the document instead of
        // throwing, and such a document cannot be stringified — so this has to be
        // checked up front, or the write below crashes the CLI on a malformed
        // pipeline rather than reporting it.
        const document = parseDocument(contents)

        if (document.errors.length > 0) {
            this.comment(`${CI_FILE} could not be parsed: ${document.errors[0]?.message ?? 'invalid YAML'}`)

            return 'fail'
        }

        if (String(document.getIn(['variables', name]) ?? '') === value) {
            return 'pass'
        }

        this.comment(
            `${CI_FILE} must set variables.${name} to "${value}" so the shared CI template resolves the Node version`,
        )

        if (dry) {
            return 'fail'
        }

        // setIn on the parsed document rather than a re-dump, so comments and
        // formatting elsewhere in the pipeline survive.
        document.setIn(['variables', name], value)
        this.project.write(CI_FILE, document.toString())

        return this.fix(true)
    }
}

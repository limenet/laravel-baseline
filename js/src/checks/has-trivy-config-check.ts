import { parse, parseDocument } from 'yaml'
import { policy } from '../policy.js'
import { CI_FILE, checkRequiredCiJobs, readCiJobs } from '../support/ci-jobs.js'
import { type CheckResult, FixableCheck } from './check.js'

type Requirement = [path: string[], expected: unknown]

/**
 * Trivy's setup, identical to the Laravel runner's: the security CI job, the
 * canonical trivy.yaml, an ignore file, and the cache directory in .gitignore.
 *
 * The canonical config skips paths a JS project does not have (`vendor/**`,
 * `storage/logs/`, `.ddev/`). Skipping a directory that is not there costs
 * nothing, and one shared template means the two runners cannot drift — so the
 * npm runner requires the same file rather than a trimmed variant.
 */
export class HasTrivyConfigCheck extends FixableCheck {
    static override readonly checkName = 'hasTrivyConfig'

    fix(dry = false): CheckResult {
        const ciResult = checkRequiredCiJobs(this.project, policy().stringListMap('trivy.ciJob'), (message) =>
            this.comment(message),
        )

        if (ciResult !== 'pass') {
            if (dry) {
                return ciResult
            }

            this.addMissingCiJobs()
        }

        const gitignoreResult = this.ensureGitignoreEntry(
            policy().string('trivy.gitignoreEntry'),
            'ignore the Trivy cache directory',
            dry,
        )

        if (gitignoreResult !== null && dry) {
            return gitignoreResult
        }

        const ignoreFile = policy().string('trivy.ignoreFile')

        if (!this.project.exists(ignoreFile)) {
            this.comment(
                `Missing ignore file: create ${ignoreFile} in project root (an empty file is acceptable)`,
            )

            if (dry) {
                return 'fail'
            }

            this.project.write(ignoreFile, '')
        }

        const configFile = policy().string('trivy.configFile')
        const contents = this.project.read(configFile)

        if (contents === null) {
            this.comment(`${configFile} not found`)

            if (dry) {
                return 'fail'
            }

            this.project.write(configFile, this.canonicalConfig())

            return this.fix(true)
        }

        // parseDocument() keeps comments and formatting across the write below,
        // and collects syntax errors instead of throwing.
        const document = parseDocument(contents)

        if (document.errors.length > 0) {
            this.comment(
                `${configFile} could not be parsed: ${document.errors[0]?.message ?? 'invalid YAML'}`,
            )

            return 'fail'
        }

        const config: unknown = document.toJS()

        if (typeof config !== 'object' || config === null) {
            this.comment(`${configFile} is empty or invalid`)

            return 'fail'
        }

        let changed = false

        for (const forbidden of policy().strings('trivy.forbiddenKeys')) {
            if (!(forbidden in config)) {
                continue
            }

            this.comment(
                `Forbidden key in ${configFile}: '${forbidden}' must not be set (use Trivy's default severity behavior)`,
            )

            if (dry) {
                return 'fail'
            }

            document.delete(forbidden)
            changed = true
        }

        for (const [path, expected] of this.requirements(parse(this.canonicalConfig()))) {
            const dotted = path.join('.')
            const current = this.getByPath(config, path)

            if (Array.isArray(expected)) {
                const declared = Array.isArray(current) ? (current as unknown[]) : []
                const missing = expected.filter((entry) => !declared.includes(entry))

                if (missing.length === 0) {
                    continue
                }

                this.comment(`Missing entries in ${configFile}: ${dotted} must include ${missing.join(', ')}`)

                if (dry) {
                    return 'fail'
                }

                document.setIn(path, [...declared, ...missing])
                changed = true

                continue
            }

            if (current === expected) {
                continue
            }

            this.comment(
                `Invalid value in ${configFile}: '${dotted}' must equal ${
                    typeof expected === 'boolean' ? String(expected) : `'${String(expected)}'`
                }`,
            )

            if (dry) {
                return 'fail'
            }

            document.setIn(path, expected)
            changed = true
        }

        if (dry) {
            return 'pass'
        }

        if (changed) {
            this.project.write(configFile, document.toString())
        }

        return this.fix(true)
    }

    /** The canonical body, written verbatim into a project that has none. */
    private canonicalConfig(): string {
        return policy().template(policy().string('trivy.template'))
    }

    /**
     * The canonical config read as a requirement set: every scalar leaf must be
     * equal in the project's file, every list leaf contained in it. Keys the
     * template does not mention are the project's own business.
     */
    private requirements(node: unknown, prefix: string[] = []): Requirement[] {
        if (typeof node !== 'object' || node === null || Array.isArray(node)) {
            return []
        }

        const found: Requirement[] = []

        for (const [key, value] of Object.entries(node)) {
            const path = [...prefix, key]

            if (typeof value === 'object' && value !== null && !Array.isArray(value)) {
                found.push(...this.requirements(value, path))

                continue
            }

            found.push([path, value])
        }

        return found
    }

    private getByPath(config: unknown, path: string[]): unknown {
        return path.reduce<unknown>((carry, segment) => {
            if (typeof carry !== 'object' || carry === null) {
                return undefined
            }

            return (carry as Record<string, unknown>)[segment]
        }, config)
    }

    /** Adds each missing job, leaving the rest of the pipeline untouched. */
    private addMissingCiJobs(): void {
        const contents = this.project.read(CI_FILE)

        if (contents === null || readCiJobs(this.project, () => {}) === null) {
            return
        }

        const document = parseDocument(contents)
        let changed = false

        for (const [job, accepted] of Object.entries(policy().stringListMap('trivy.ciJob'))) {
            if (document.hasIn([job])) {
                continue
            }

            document.setIn([job], { extends: [accepted[0]] })
            changed = true
        }

        if (changed) {
            this.project.write(CI_FILE, document.toString())
        }
    }

    private ensureGitignoreEntry(entry: string, reason: string, dry: boolean): CheckResult | null {
        const contents = this.project.read('.gitignore')

        if (contents === null) {
            this.comment(`Missing .gitignore in project root: create it and add '${entry}'`)

            if (dry) {
                return 'fail'
            }

            this.project.write('.gitignore', `${entry}\n`)

            return 'fail'
        }

        const normalise = (line: string) => line.trim().replace(/^\/+|\/+$/g, '')

        if (contents.split('\n').map(normalise).includes(normalise(entry))) {
            return null
        }

        this.comment(`Missing entry in .gitignore: add '${entry}' to ${reason}`)

        if (dry) {
            return 'fail'
        }

        const prefix = contents === '' || contents.endsWith('\n') ? '' : '\n'
        this.project.write('.gitignore', `${contents}${prefix}${entry}\n`)

        return 'fail'
    }
}

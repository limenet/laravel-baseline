import {
    type Document,
    isMap,
    isScalar,
    isSeq,
    type Pair,
    parseDocument,
    type Scalar,
    type YAMLMap,
} from 'yaml'
import { policy } from '../policy.js'
import { CI_FILE, checkRequiredCiJobs, readCiJobs } from '../support/ci-jobs.js'
import { yamlStyle } from '../support/yaml-style.js'
import { type CheckResult, FixableCheck } from './check.js'

type Job = Record<string, unknown>

interface DeployTarget {
    job: string
    environment: string | null
    variables: Record<string, string>
}

/** Mirrors the check the template runs on SBOM_SERVER_NAME. */
const SERVER_NAME_PATTERN = /^[a-z0-9-]+$/

/**
 * CycloneDX SBOMs uploaded to Dependency-Track through the shared
 * laravel/sbom.yml template, identical to the PHP runner's check: its include,
 * its stage, one job per dependency manifest, and one server job per
 * environment an SSH deploy job deploys to.
 *
 * Degrades for a JS project on its own: without composer.json there is no
 * sbom:php job to require, and the node deploy templates are not SSH deploys,
 * so no server job either. What it adds is the image variable — the template
 * runs on laravel/$PHP_VERSION, which a PHP project declares anyway and a JS
 * project otherwise would not.
 */
export class UploadsSbomsCheck extends FixableCheck {
    static override readonly checkName = 'uploadsSboms'

    fix(dry = false): CheckResult {
        const data = readCiJobs(this.project, (message) => this.comment(message)) as Job | null

        if (data === null) {
            return 'fail'
        }

        const original = this.project.read(CI_FILE) ?? ''
        const document = dry ? null : parseDocument(original)

        if (!this.ensureInclude(data, document) && dry) {
            return 'fail'
        }

        if (!this.ensureStage(data, document) && dry) {
            return 'fail'
        }

        if (!this.ensureAppJobs(data, document) && dry) {
            return 'fail'
        }

        if (!this.ensureServerJobs(data, document) && dry) {
            return 'fail'
        }

        if (!this.ensureImageVariable(data) && dry) {
            return 'fail'
        }

        if (document === null) {
            return 'pass'
        }

        const contents = document.toString(yamlStyle(original))

        if (contents !== original) {
            this.project.write(CI_FILE, contents)
        }

        return this.fix(true)
    }

    /** The app jobs whose manifest exists. */
    private requiredJobs(): Record<string, string[]> {
        const manifests = policy().stringMap('sbom.jobManifests')

        return Object.fromEntries(
            Object.entries(policy().stringListMap('sbom.jobs')).filter(
                ([job]) => manifests[job] === undefined || this.project.exists(manifests[job]),
            ),
        )
    }

    private ensureInclude(data: Job, document: Document | null): boolean {
        const project = policy().string('sbom.include.project')
        const templateFile = policy().string('sbom.include.file')
        const include = data.include
        const entries = Array.isArray(include) ? include : [include]

        for (const entry of entries) {
            if (!isRecord(entry) || entry.project !== project) {
                continue
            }

            const files = Array.isArray(entry.file) ? entry.file : [entry.file]

            if (files.includes(templateFile)) {
                return true
            }
        }

        this.comment(
            `Missing include in ${CI_FILE}: include file '${templateFile}' from project '${project}'`,
        )

        if (include !== undefined && include !== null && !Array.isArray(include)) {
            this.comment(
                `Cannot add the include to ${CI_FILE} automatically: rewrite 'include' as a list first`,
            )

            return false
        }

        if (document !== null) {
            const entry = { project, ref: policy().string('sbom.include.ref'), file: templateFile }
            const sequence = document.get('include', true)

            if (isSeq(sequence)) {
                const node = document.createNode(entry) as YAMLMap
                const sibling = sequence.items.find(isMap)

                // Each value quoted the way the same key already is.
                for (const pair of node.items) {
                    const like = sibling?.get((pair.key as Scalar).value, true)

                    if (isScalar(like) && isScalar(pair.value)) {
                        pair.value.type = like.type
                    }
                }

                sequence.add(node)
            } else {
                document.set('include', [entry])
            }
        }

        return false
    }

    private ensureStage(data: Job, document: Document | null): boolean {
        const stage = policy().string('sbom.stage')
        const stages = data.stages

        if (Array.isArray(stages) && stages.includes(stage)) {
            return true
        }

        this.comment(`Missing stage in ${CI_FILE}: add '${stage}' to 'stages'`)

        if (document !== null) {
            const sequence = document.get('stages', true)

            if (stages === undefined || stages === null) {
                // Declaring stages replaces GitLab's implicit ones, which the
                // pipeline's other jobs may still rely on.
                appendPair(document, 'stages', [...policy().strings('sbom.defaultStages'), stage])
            } else if (isSeq(sequence)) {
                sequence.add(document.createNode(stage))
            }
        }

        return false
    }

    private ensureAppJobs(data: Job, document: Document | null): boolean {
        const required = this.requiredJobs()

        if (checkRequiredCiJobs(this.project, required, (message) => this.comment(message)) === 'pass') {
            return true
        }

        // A job that exists but extends something else is the developer's to
        // reconcile; only a missing one is added.
        for (const [job, templates] of Object.entries(required)) {
            if (document !== null && !(job in data)) {
                appendPair(document, job, { extends: [templates[0]] })
            }
        }

        return false
    }

    private ensureServerJobs(data: Job, document: Document | null): boolean {
        const serverTemplate = policy().string('sbom.server.template')
        const nameVariable = policy().string('sbom.server.nameVariable')
        const action = policy().string('sbom.server.environmentAction')
        const globals = isRecord(data.variables) ? data.variables : {}
        const servers = Object.entries(jobs(data)).filter(([, job]) =>
            extendsOf(job).includes(serverTemplate),
        )
        const taken = new Set(Object.keys(data))
        let ok = true

        for (const { job: deployJob, environment, variables: own } of this.deployTargets(data)) {
            const server = this.matchServer(servers, environment, own, globals, nameVariable)

            if (server === null) {
                ok = false
                this.comment(
                    environment === null
                        ? `Missing server SBOM in ${CI_FILE}: deploy job '${deployJob}' needs a job extending ${serverTemplate} with the same DEPLOY_HOST and ${nameVariable} set`
                        : `Missing server SBOM in ${CI_FILE}: deploy environment '${environment}' (job '${deployJob}') needs a job extending ${serverTemplate} with environment {name: ${environment}, action: ${action}} and ${nameVariable} set`,
                )

                if (document !== null) {
                    const name = serverName(environment ?? deployJob)
                    const jobName = uniqueJobName(
                        `${policy().string('sbom.server.jobPrefix')}${name.replaceAll('-', '_')}`,
                        taken,
                    )
                    taken.add(jobName)
                    this.appendServerJob(document, jobName, deployJob, environment, own, name)
                }

                continue
            }

            const [serverJob, job] = server

            if (
                environment !== null &&
                (environmentName(job) !== environment || environmentAction(job) !== action)
            ) {
                ok = false
                this.comment(
                    `Misconfigured server SBOM job '${serverJob}' in ${CI_FILE}: set environment {name: ${environment}, action: ${action}} so it gets the environment's CI/CD variables without recording a deployment`,
                )

                const node = document?.get(serverJob, true)

                if (isMap(node)) {
                    const current = node.get('environment', true)

                    if (isMap(current)) {
                        current.set('name', environment)
                        current.set('action', action)
                    } else {
                        node.delete('environment')
                        node.set('environment', document?.createNode({ name: environment, action }))
                    }
                }
            }

            const name = scalar(variablesOf(job)[nameVariable]) ?? scalar(globals[nameVariable])

            if (name === null || !SERVER_NAME_PATTERN.test(name)) {
                ok = false
                this.comment(
                    `Misconfigured server SBOM job '${serverJob}' in ${CI_FILE}: set ${nameVariable} to lowercase letters, digits and dashes`,
                )
            }

            for (const [variable, value] of Object.entries(own)) {
                if (effectiveVariable(job, globals, variable) !== value) {
                    ok = false
                    this.comment(
                        `Misconfigured server SBOM job '${serverJob}' in ${CI_FILE}: set ${variable} to '${value}' like deploy job '${deployJob}'`,
                    )
                }
            }
        }

        return ok
    }

    private ensureImageVariable(data: Job): boolean {
        const variable = policy().string('sbom.imageVariable')

        if (isRecord(data.variables) && scalar(data.variables[variable]) !== null) {
            return true
        }

        this.comment(
            `Missing variable in ${CI_FILE}: set a top-level ${variable}, the SBOM jobs run on the laravel/$${variable} image`,
        )

        return false
    }

    /**
     * One target per environment the SSH deploy jobs deploy to, or per host for
     * a deploy job without an environment.
     */
    private deployTargets(data: Job): DeployTarget[] {
        const deployTemplates = policy().strings('sbom.server.deployTemplates')
        const defaults = policy().stringMap('sbom.server.deployTemplateEnvironments')
        const connection = policy().strings('sbom.server.connectionVariables')
        const globals = isRecord(data.variables) ? data.variables : {}
        const targets = new Map<string, DeployTarget>()

        for (const [name, job] of Object.entries(jobs(data))) {
            const templates = extendsOf(job).filter((template) => deployTemplates.includes(template))

            if (templates.length === 0) {
                continue
            }

            let environment = environmentName(job)

            for (const template of templates) {
                environment ??= defaults[template] ?? null
            }

            const own: Record<string, string> = {}

            for (const variable of connection) {
                const value = scalar(variablesOf(job)[variable])

                if (value !== null) {
                    own[variable] = value
                }
            }

            const key =
                environment !== null
                    ? `environment:${environment}`
                    : `host:${effectiveVariable(job, globals, 'DEPLOY_HOST') ?? ''}`

            if (!targets.has(key)) {
                targets.set(key, { job: name, environment, variables: own })
            }
        }

        return [...targets.values()]
    }

    /**
     * The server job covering a target: the one in its environment, else one
     * outside any environment whose name gives it away (it then only lacks the
     * environment), or for a target without an environment the one on its host.
     */
    private matchServer(
        servers: Array<[string, Job]>,
        environment: string | null,
        own: Record<string, string>,
        globals: Record<string, unknown>,
        nameVariable: string,
    ): [string, Job] | null {
        if (environment !== null) {
            return (
                servers.find(([, job]) => environmentName(job) === environment) ??
                servers.find(
                    ([, job]) =>
                        environmentName(job) === null &&
                        scalar(variablesOf(job)[nameVariable]) === serverName(environment),
                ) ??
                null
            )
        }

        const host = own.DEPLOY_HOST ?? scalar(globals.DEPLOY_HOST)

        return (
            servers.find(
                ([, job]) =>
                    environmentName(job) === null && effectiveVariable(job, globals, 'DEPLOY_HOST') === host,
            ) ?? null
        )
    }

    private appendServerJob(
        document: Document,
        jobName: string,
        deployJob: string,
        environment: string | null,
        own: Record<string, string>,
        name: string,
    ): void {
        const job: Record<string, unknown> = { extends: [policy().string('sbom.server.template')] }

        if (environment !== null) {
            job.environment = { name: environment, action: policy().string('sbom.server.environmentAction') }
        }

        job.variables = { ...own, [policy().string('sbom.server.nameVariable')]: name }

        const node = appendPair(document, jobName, job)
        const variables = (node as YAMLMap).get('variables', true)

        // A copied variable keeps the quoting the deploy job gave it.
        for (const variable of Object.keys(own)) {
            const source = document.getIn([deployJob, 'variables', variable], true)
            const target = isMap(variables) ? variables.get(variable, true) : undefined

            if (isScalar(source) && isScalar(target)) {
                target.type = source.type
            }
        }
    }
}

/** Adds a top-level key after a blank line, the way every job is laid out. */
function appendPair(document: Document, key: string, value: unknown): unknown {
    const pair = document.createPair(key, value) as Pair<Scalar, unknown>

    if (isMap(document.contents) && document.contents.items.length > 0) {
        pair.key.spaceBefore = true
    }

    if (isMap(document.contents)) {
        document.contents.items.push(pair)
    } else {
        document.set(key, value)
    }

    return pair.value
}

function isRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null && !Array.isArray(value)
}

/** The visible jobs: every mapping under a key that is not hidden. */
function jobs(data: Job): Record<string, Job> {
    return Object.fromEntries(
        Object.entries(data).filter(
            (entry): entry is [string, Job] => !entry[0].startsWith('.') && isRecord(entry[1]),
        ),
    )
}

function extendsOf(job: Job): string[] {
    const declared = job.extends ?? []

    return (Array.isArray(declared) ? declared : [declared]).filter(
        (entry): entry is string => typeof entry === 'string',
    )
}

function variablesOf(job: Job): Record<string, unknown> {
    return isRecord(job.variables) ? job.variables : {}
}

function effectiveVariable(job: Job, globals: Record<string, unknown>, variable: string): string | null {
    return scalar(variablesOf(job)[variable]) ?? scalar(globals[variable])
}

function environmentName(job: Job): string | null {
    const environment = job.environment

    if (typeof environment === 'string') {
        return environment
    }

    return isRecord(environment) ? scalar(environment.name) : null
}

function environmentAction(job: Job): string | null {
    return isRecord(job.environment) ? scalar(job.environment.action) : null
}

function scalar(value: unknown): string | null {
    return typeof value === 'string' || typeof value === 'number' ? String(value) : null
}

/** A valid SBOM_SERVER_NAME derived from an environment or job name. */
function serverName(from: string): string {
    const slug = from
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '')

    return slug === '' ? 'server' : slug
}

function uniqueJobName(name: string, taken: Set<string>): string {
    let candidate = name

    for (let i = 2; taken.has(candidate); i++) {
        candidate = `${name}_${i}`
    }

    return candidate
}

import { mkdtempSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { join } from 'node:path'
import { expect, it } from 'vitest'
import { parse } from 'yaml'
import { CommentCollector } from '../src/checks/check.js'
import { CiSetsNodeVersionCheck } from '../src/checks/ci-sets-node-version-check.js'
import { Project } from '../src/project.js'

function scratch(ci: string): Project {
    const project = Project.at(mkdtempSync(join(tmpdir(), 'baseline-ci-')))

    project.write('.gitlab-ci.yml', ci)

    return project
}

function check(project: Project) {
    return new CiSetsNodeVersionCheck(project, new CommentCollector())
}

it('preserves comments and unrelated jobs when adding the variable', () => {
    const project = scratch(`# Pipeline for the frontend
include:
  - project: limenet/ci-templates
    file: /templates.yml

variables:
  # Cache the npm store between jobs
  NPM_CONFIG_CACHE: .npm

js:
  extends:
    - .lint_js
`)

    expect(check(project).fix()).toBe('pass')

    const raw = project.read('.gitlab-ci.yml') ?? ''

    expect(raw).toContain('# Pipeline for the frontend')
    expect(raw).toContain('# Cache the npm store between jobs')
    expect(raw).toContain('NPM_CONFIG_CACHE: .npm')
    expect(raw).toContain('limenet/ci-templates')

    const parsed = parse(raw)

    expect(parsed.variables.NODE_VERSION).toBe('latest')
    expect(parsed.js.extends).toStrictEqual(['.lint_js'])
})

it('replaces a pinned major rather than appending a second entry', () => {
    const project = scratch('variables:\n  NODE_VERSION: "24"\n')

    expect(check(project).fix()).toBe('pass')

    const raw = project.read('.gitlab-ci.yml') ?? ''

    expect(raw.match(/NODE_VERSION/g)).toHaveLength(1)
    expect(parse(raw).variables.NODE_VERSION).toBe('latest')
})

it('adds the variable beside an entry written in the extended syntax', () => {
    const project = scratch(`variables:
  DEPLOY_TARGET:
    value: "staging"
    description: "Where the pipeline deploys to"

js:
  extends:
    - .lint_js
`)

    expect(check(project).fix()).toBe('pass')

    const parsed = parse(project.read('.gitlab-ci.yml') ?? '')

    expect(parsed.variables.NODE_VERSION).toBe('latest')
    expect(parsed.variables.DEPLOY_TARGET).toStrictEqual({
        value: 'staging',
        description: 'Where the pipeline deploys to',
    })
})

it('reports a malformed pipeline instead of crashing on the write', () => {
    // parseDocument() collects errors rather than throwing, and the document it
    // returns cannot be stringified — so the guard has to be an explicit check.
    const project = scratch('variables:\n  - [unclosed\n')

    expect(check(project).fix()).toBe('fail')
    expect(project.read('.gitlab-ci.yml')).toBe('variables:\n  - [unclosed\n')
})

it('fails without repairing when there is no CI configuration at all', () => {
    const project = Project.at(mkdtempSync(join(tmpdir(), 'baseline-ci-')))

    expect(check(project).fix()).toBe('fail')
    expect(project.exists('.gitlab-ci.yml')).toBe(false)
})

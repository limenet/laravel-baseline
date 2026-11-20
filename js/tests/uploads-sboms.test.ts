import { mkdtempSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { join } from 'node:path'
import { expect, it } from 'vitest'
import { parse } from 'yaml'
import { CommentCollector } from '../src/checks/check.js'
import { UploadsSbomsCheck } from '../src/checks/uploads-sboms-check.js'
import { Project } from '../src/project.js'

const COMPLETE = `include:
  - project: limenet/ci-cd-templates
    ref: master
    file: laravel/sbom.yml

stages:
  - sbom

variables:
  PHP_VERSION: "8.5"

sbom:npm:
  extends:
    - .sbom_npm
`

function scratch(ci: string): Project {
    const project = Project.at(mkdtempSync(join(tmpdir(), 'baseline-sbom-')))
    project.write('package.json', '{}')
    project.write('.gitlab-ci.yml', ci)

    return project
}

function run(project: Project, fix = false) {
    const comments = new CommentCollector()
    const check = new UploadsSbomsCheck(project, comments)

    return { result: fix ? check.fix() : check.check(), comments: comments.all() }
}

it('passes on a JS pipeline with the npm job', () => {
    expect(run(scratch(COMPLETE)).result).toBe('pass')
})

it('refuses to add the include to a scalar include', () => {
    const ci = COMPLETE.replace(/include:[\s\S]*?\n\n/, "include: 'ci/local.yml'\n\n")
    const project = scratch(ci)
    const { result, comments } = run(project, true)

    expect(result).toBe('fail')
    expect(comments).toContain(
        "Cannot add the include to .gitlab-ci.yml automatically: rewrite 'include' as a list first",
    )
    expect(project.read('.gitlab-ci.yml')).toBe(ci)
})

it('leaves an unparseable pipeline untouched', () => {
    const project = scratch('deploy: [\n')

    expect(run(project, true).result).toBe('fail')
    expect(project.read('.gitlab-ci.yml')).toBe('deploy: [\n')
})

it('names a server job after the deploy job when its template sets no environment', () => {
    const project = scratch(`${COMPLETE}
deploy_Live:
  extends:
    - .deploy_sage_tagged
`)

    expect(run(project, true).result).toBe('pass')
    expect(parse(project.read('.gitlab-ci.yml') ?? '')['sbom:server_deploy_live']).toStrictEqual({
        extends: ['.sbom_server'],
        variables: { SBOM_SERVER_NAME: 'deploy-live' },
    })
})

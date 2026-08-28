import { mkdtempSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { join } from 'node:path'
import { expect, it } from 'vitest'
import { CommentCollector } from '../src/checks/check.js'
import { DoesNotExcludeUnknownChecksCheck } from '../src/checks/does-not-exclude-unknown-checks-check.js'
import { Project } from '../src/project.js'
import { type BaselineState, readState } from '../src/state.js'

function scratch(state?: BaselineState): Project {
    const project = Project.at(mkdtempSync(join(tmpdir(), 'baseline-excludes-')))

    project.write('package.json', '{}\n')

    if (state !== undefined) {
        project.writeJson('.baseline.json', state)
    }

    return project
}

function check(project: Project, comments = new CommentCollector()) {
    return new DoesNotExcludeUnknownChecksCheck(project, comments)
}

it('passes when the project has no state file', () => {
    expect(check(scratch()).check()).toBe('pass')
})

it('passes when every exclude names a registered check', () => {
    expect(check(scratch({ excludes: ['hasEditorconfig', 'hasNpmScripts'] })).check()).toBe('pass')
})

it('fails on a check the Laravel runner registers but this one does not', () => {
    const comments = new CommentCollector()

    expect(check(scratch({ excludes: ['usesPest'] }), comments).check()).toBe('fail')
    expect(comments.all()).toContain(
        'Remove "usesPest" from the excludes in .baseline.json: no check by that name is registered, so the entry excludes nothing',
    )
})

it('removes only the dead entries, leaving periodic state alone', () => {
    const project = scratch({
        excludes: ['hasEditorconfig', 'hasRectorConfigWithSetProviders'],
        periodic: { updatesDependencies: '2026-01-01T00:00:00.000Z' },
    })

    expect(check(project).fix()).toBe('pass')
    expect(readState(project)).toStrictEqual({
        excludes: ['hasEditorconfig'],
        periodic: { updatesDependencies: '2026-01-01T00:00:00.000Z' },
    })
})

it('does not rewrite the state file on a dry run', () => {
    const project = scratch({ excludes: ['hasRectorConfigWithSetProviders'] })
    const before = project.read('.baseline.json')

    expect(check(project).check()).toBe('fail')
    expect(project.read('.baseline.json')).toBe(before)
})

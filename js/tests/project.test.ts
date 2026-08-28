import { mkdtempSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { join } from 'node:path'
import { expect, it } from 'vitest'
import { Project } from '../src/project.js'

function scratch(): Project {
    return Project.at(mkdtempSync(join(tmpdir(), 'baseline-project-')))
}

it('reads back JSON it wrote', () => {
    const project = scratch()

    project.writeJson('package.json', { name: 'app' })

    expect(project.readJson('package.json')).toStrictEqual({ name: 'app' })
})

it('returns null for a file that is not there', () => {
    expect(scratch().readJson('package.json')).toBeNull()
})

it('throws on malformed JSON rather than reporting the file as absent', () => {
    const project = scratch()

    project.write('.claude/settings.json', '{ "permissions": ')

    // null is how "not there" is spelled, and every caller treats it as licence
    // to write the file from scratch — so a syntax error must not look like one,
    // or --fix would silently discard the developer's hooks and env.
    expect(() => project.readJson('.claude/settings.json')).toThrow(/settings\.json is not valid JSON/)
})

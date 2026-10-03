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

it('writes a new JSON file with four spaces', () => {
    const project = scratch()

    project.writeJson('package.json', { scripts: { test: 'vitest' } })

    expect(project.read('package.json')).toBe('{\n    "scripts": {\n        "test": "vitest"\n    }\n}\n')
})

it.each([
    ['two spaces', '  '],
    ['tabs', '\t'],
])('keeps the indent of an existing JSON file: %s', (_name, indent) => {
    const project = scratch()

    project.write('package.json', `{\n${indent}"name": "app"\n}\n`)
    project.writeJson('package.json', { name: 'app', scripts: { test: 'vitest' } })

    expect(project.read('package.json')).toBe(
        `{\n${indent}"name": "app",\n${indent}"scripts": {\n${indent}${indent}"test": "vitest"\n${indent}}\n}\n`,
    )
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

it('finds a file by extension at any depth', () => {
    const project = scratch()

    project.write('src/deep/nested/index.ts', '')

    expect(project.containsFileWithExtension(['.ts'])).toBe(true)
    expect(project.containsFileWithExtension(['.tsx'])).toBe(false)
})

it('does not count files under node_modules or dot-directories as the project’s own', () => {
    const project = scratch()

    project.write('node_modules/some-package/index.d.ts', '')
    project.write('.claude/hooks/hook.ts', '')
    project.write('src/index.js', '')

    expect(project.containsFileWithExtension(['.ts'])).toBe(false)
})

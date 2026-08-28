import { mkdtempSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { join } from 'node:path'
import { expect, it } from 'vitest'
import { BiomeUsesLocalSchemaCheck } from '../src/checks/biome-uses-local-schema-check.js'
import { CommentCollector } from '../src/checks/check.js'
import { policy } from '../src/policy.js'
import { Project } from '../src/project.js'

const LOCAL = policy().string('biome.schema')

function scratch(contents?: string): Project {
    const project = Project.at(mkdtempSync(join(tmpdir(), 'baseline-biome-')))

    project.write('package.json', '{}\n')

    if (contents !== undefined) {
        project.write('biome.json', contents)
    }

    return project
}

function check(project: Project) {
    return new BiomeUsesLocalSchemaCheck(project, new CommentCollector())
}

it('passes when the project does not use Biome', () => {
    expect(check(scratch()).check()).toBe('pass')
})

it('accepts the escaped-slash spelling of the same path', () => {
    const escaped = LOCAL.replaceAll('/', '\\/')

    expect(check(scratch(`{ "$schema": "${escaped}" }\n`)).check()).toBe('pass')
})

it('fails on an empty biome.json without writing to it', () => {
    const project = scratch('')

    expect(check(project).fix()).toBe('fail')
    expect(project.read('biome.json')).toBe('')
})

it('rewrites the remote $schema in place, comments and all', () => {
    // Biome reads its config as JSONC, so a commented file is valid input and a
    // fix must survive it — which a decode/re-encode round trip would not.
    const before = [
        '{',
        '  // pinned by hand, and forgotten ever since',
        '  "$schema": "https://biomejs.dev/schemas/2.3.14/schema.json",',
        '  "linter": { "enabled": true }',
        '}',
        '',
    ].join('\n')

    const project = scratch(before)

    expect(check(project).fix()).toBe('pass')
    expect(project.read('biome.json')).toBe(
        before.replace('https://biomejs.dev/schemas/2.3.14/schema.json', LOCAL),
    )
})

it('inserts a missing $schema as the first key, matching the file’s indentation', () => {
    const project = scratch('{\n  "linter": {\n    "enabled": true\n  }\n}\n')

    expect(check(project).fix()).toBe('pass')
    expect(project.read('biome.json')).toBe(
        `{\n  "$schema": "${LOCAL}",\n  "linter": {\n    "enabled": true\n  }\n}\n`,
    )
})

it('inserts into an otherwise empty config', () => {
    const project = scratch('{}\n')

    expect(check(project).fix()).toBe('pass')
    expect(project.readJson('biome.json')).toStrictEqual({ $schema: LOCAL })
})

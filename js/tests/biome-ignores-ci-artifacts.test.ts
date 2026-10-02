import { mkdtempSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { join } from 'node:path'
import { expect, it } from 'vitest'
import { BiomeIgnoresCiArtifactsCheck } from '../src/checks/biome-ignores-ci-artifacts-check.js'
import { CommentCollector } from '../src/checks/check.js'
import { Project } from '../src/project.js'

function scratch(contents: string): Project {
    const project = Project.at(mkdtempSync(join(tmpdir(), 'baseline-biome-artifacts-')))

    project.write('package.json', '{}\n')
    project.write('biome.json', contents)

    return project
}

function withIncludes(includes: string[]): Project {
    return scratch(JSON.stringify({ files: { includes } }))
}

it.each([
    ['everything', ['**'], 'fail'],
    ['every json file', ['**/*.json'], 'fail'],
    ['root json files', ['*.json'], 'fail'],
    ['excluded', ['**', '!metadata.json'], 'pass'],
    ['excluded anywhere', ['**', '!**/metadata.json'], 'pass'],
    ['excluded with ./', ['**', '!./metadata.json'], 'pass'],
    ['force-ignored', ['**', '!!metadata.json'], 'pass'],
    ['taken back by a later pattern', ['**', '!metadata.json', '*.json'], 'fail'],
    ['allowlist', ['resources/**', 'src/*.ts'], 'pass'],
    ['nested json only', ['config/*.json'], 'pass'],
    ['empty', [], 'pass'],
])("follows Biome's last-match-wins evaluation: %s", (_, includes, expected) => {
    expect(new BiomeIgnoresCiArtifactsCheck(withIncludes(includes), new CommentCollector()).check()).toBe(
        expected,
    )
})

it('fails on an unparsable biome.json without writing to it', () => {
    const project = scratch('{ "files": ')
    const comments = new CommentCollector()

    expect(new BiomeIgnoresCiArtifactsCheck(project, comments).fix()).toBe('fail')
    expect(comments.all()).toContain('biome.json is not valid JSON')
    expect(project.read('biome.json')).toBe('{ "files": ')
})

it('fails when files.includes is not a list', () => {
    const comments = new CommentCollector()

    expect(
        new BiomeIgnoresCiArtifactsCheck(scratch('{ "files": { "includes": "**" } }'), comments).fix(),
    ).toBe('fail')
    expect(comments.all()).toContain('Invalid files.includes in biome.json: must be a list of glob patterns')
})

it("appends to files.includes, not to an override's includes", () => {
    const project = scratch(
        '{\n  "overrides": [{ "includes": ["**/*.css"] }],\n  "files": { "includes": ["**"] }\n}\n',
    )

    expect(new BiomeIgnoresCiArtifactsCheck(project, new CommentCollector()).fix()).toBe('pass')
    expect(project.read('biome.json')).toBe(
        '{\n  "overrides": [{ "includes": ["**/*.css"] }],\n  "files": { "includes": ["**", "!metadata.json"] }\n}\n',
    )
})

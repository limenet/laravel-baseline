import { mkdtempSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { join } from 'node:path'
import { expect, it } from 'vitest'
import { BiomeIgnoresClaudeMdCheck } from '../src/checks/biome-ignores-claude-md-check.js'
import { CommentCollector } from '../src/checks/check.js'
import { Project } from '../src/project.js'

function withIncludes(includes: string[]): Project {
    const project = Project.at(mkdtempSync(join(tmpdir(), 'baseline-biome-claude-md-')))

    project.write('package.json', '{}\n')
    project.write('biome.json', JSON.stringify({ files: { includes } }))

    return project
}

it.each([
    ['everything', ['**'], 'fail'],
    ['every markdown file', ['**/*.md'], 'fail'],
    ['excluded', ['**', '!CLAUDE.md'], 'pass'],
    ['excluded anywhere', ['**', '!**/CLAUDE.md'], 'pass'],
    ['markdown excluded everywhere', ['**', '!**/*.md'], 'pass'],
    ['taken back by a later pattern', ['**', '!**/*.md', '*.md'], 'fail'],
    ['allowlist', ['resources/**', 'src/*.ts'], 'pass'],
    ['empty', [], 'pass'],
])("follows Biome's last-match-wins evaluation: %s", (_, includes, expected) => {
    expect(new BiomeIgnoresClaudeMdCheck(withIncludes(includes), new CommentCollector()).check()).toBe(
        expected,
    )
})

it('tells which entry to add and why', () => {
    const comments = new CommentCollector()

    expect(new BiomeIgnoresClaudeMdCheck(withIncludes(['**']), comments).check()).toBe('fail')

    const message = comments.all().join('\n')

    expect(message).toContain('Biome checks CLAUDE.md (CLAUDE.md)')
    expect(message).toContain('Biome 2.6+ reformats')
    expect(message).toContain('add "!CLAUDE.md" at the end of files.includes')
})

it('fixes idempotently', () => {
    const project = withIncludes(['**'])

    expect(new BiomeIgnoresClaudeMdCheck(project, new CommentCollector()).fix()).toBe('pass')
    expect(new BiomeIgnoresClaudeMdCheck(project, new CommentCollector()).fix()).toBe('pass')
    expect(JSON.parse(project.read('biome.json') ?? '').files.includes).toEqual(['**', '!CLAUDE.md'])
})

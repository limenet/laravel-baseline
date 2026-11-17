import { mkdtempSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { join } from 'node:path'
import { expect, it } from 'vitest'
import { BiomeIgnoresClaudeSkillsCheck } from '../src/checks/biome-ignores-claude-skills-check.js'
import { CommentCollector } from '../src/checks/check.js'
import { Project } from '../src/project.js'

function withIncludes(includes: string[]): Project {
    const project = Project.at(mkdtempSync(join(tmpdir(), 'baseline-biome-skills-')))

    project.write('package.json', '{}\n')
    project.write('biome.json', JSON.stringify({ files: { includes } }))

    return project
}

it.each([
    ['everything', ['**'], 'fail'],
    ['every markdown file', ['**/*.md'], 'fail'],
    ['excluded', ['**', '!.claude/skills'], 'pass'],
    ['excluded with ./', ['**', '!./.claude/skills'], 'pass'],
    ['excluded with its contents', ['**', '!.claude/skills/**'], 'pass'],
    ['markdown excluded everywhere', ['**', '!**/*.md'], 'pass'],
    ['parent excluded', ['**', '!.claude'], 'pass'],
    ['force-ignored', ['**', '!!.claude/skills'], 'pass'],
    ['taken back by a later pattern', ['**', '!.claude', '.claude/**'], 'fail'],
    ['allowlist', ['resources/**', 'src/*.ts'], 'pass'],
    ['empty', [], 'pass'],
])("follows Biome's last-match-wins evaluation: %s", (_, includes, expected) => {
    expect(new BiomeIgnoresClaudeSkillsCheck(withIncludes(includes), new CommentCollector()).check()).toBe(
        expected,
    )
})

it('tells which entry to add and why', () => {
    const comments = new CommentCollector()

    expect(new BiomeIgnoresClaudeSkillsCheck(withIncludes(['**']), comments).check()).toBe('fail')

    const message = comments.all().join('\n')

    expect(message).toContain('Biome checks installed Claude skills (.claude/skills)')
    expect(message).toContain('Biome 2.6+ formats Markdown')
    expect(message).toContain('add "!.claude/skills" at the end of files.includes')
})

it('fixes idempotently', () => {
    const project = withIncludes(['**'])

    expect(new BiomeIgnoresClaudeSkillsCheck(project, new CommentCollector()).fix()).toBe('pass')
    expect(new BiomeIgnoresClaudeSkillsCheck(project, new CommentCollector()).fix()).toBe('pass')
    expect(JSON.parse(project.read('biome.json') ?? '').files.includes).toEqual(['**', '!.claude/skills'])
})

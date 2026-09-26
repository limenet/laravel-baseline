import { mkdtempSync, readdirSync, readFileSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { join } from 'node:path'
import { expect, it, vi } from 'vitest'
import {
    packagedSkills,
    runInstallSkills,
    skillsDirectory,
    syncSkills,
} from '../src/commands/install-skills.js'
import { Project } from '../src/project.js'

function scratch(): Project {
    return Project.at(mkdtempSync(join(tmpdir(), 'baseline-skills-')))
}

function silently<T>(run: () => T): T {
    const log = vi.spyOn(console, 'log').mockImplementation(() => {})
    const error = vi.spyOn(console, 'error').mockImplementation(() => {})

    try {
        return run()
    } finally {
        log.mockRestore()
        error.mockRestore()
    }
}

it('packages at least the two portable skills', () => {
    const skills = readdirSync(skillsDirectory()).sort()

    expect(skills).toContain('creating-a-release')
    expect(skills).toContain('updating-dependencies')
})

it('installs every packaged skill into .claude/skills', () => {
    const project = scratch()

    expect(silently(() => runInstallSkills(project, { force: false }))).toBe(0)

    for (const skill of readdirSync(skillsDirectory())) {
        const installed = project.read(`.claude/skills/${skill}/SKILL.md`)

        expect(installed).toBe(readFileSync(join(skillsDirectory(), skill, 'SKILL.md'), 'utf8'))
    }
})

it('leaves an existing skill alone unless forced', () => {
    const project = scratch()
    const target = '.claude/skills/creating-a-release/SKILL.md'

    project.write(target, '# mine\n')

    expect(silently(() => runInstallSkills(project, { force: false }))).toBe(0)
    expect(project.read(target)).toBe('# mine\n')

    expect(silently(() => runInstallSkills(project, { force: true }))).toBe(0)
    expect(project.read(target)).not.toBe('# mine\n')
})

it('syncs missing and outdated skills, and nothing else', () => {
    const project = scratch()
    const all = packagedSkills().map((skill) => skill.target)

    expect(all.length).toBeGreaterThan(0)
    expect(syncSkills(project)).toEqual(all)
    expect(syncSkills(project)).toEqual([])

    const target = '.claude/skills/creating-a-release/SKILL.md'
    project.write(target, '# outdated\n')
    project.write('.claude/skills/project-specific/SKILL.md', '# mine\n')

    expect(syncSkills(project)).toEqual([target])
    expect(project.read(target)).toBe(
        readFileSync(join(skillsDirectory(), 'creating-a-release', 'SKILL.md'), 'utf8'),
    )
    expect(project.read('.claude/skills/project-specific/SKILL.md')).toBe('# mine\n')
})

it('ships no DDEV or composer instructions in the JS skills', () => {
    // These are the JS variants — anything routed through ddev or composer would
    // be unrunnable in the projects they are installed into.
    for (const skill of readdirSync(skillsDirectory())) {
        const contents = readFileSync(join(skillsDirectory(), skill, 'SKILL.md'), 'utf8')

        expect(contents, `${skill} mentions ddev`).not.toContain('ddev ')
        expect(contents, `${skill} mentions composer`).not.toContain('composer ')
    }
})

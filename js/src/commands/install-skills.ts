import { existsSync, readdirSync, readFileSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'
import type { Project } from '../project.js'

/**
 * Copies the packaged skills into the project's .claude/skills/.
 *
 * The PHP runner does not need this: Laravel Boost discovers
 * resources/boost/skills/ by scanning installed composer packages. A JS project
 * has no vendor autoload and no Boost, so the runner installs them itself.
 *
 * js/src and js/dist sit at the same depth, so this resolves identically under
 * vitest and in the published package.
 */
export function skillsDirectory(): string {
    return join(dirname(fileURLToPath(import.meta.url)), '..', '..', 'skills')
}

export interface PackagedSkill {
    name: string
    target: string
    contents: string
}

/**
 * Every skill this package ships, with the path it is installed to. Shared by
 * the install-skills command and hasInstalledSkills, so the two can never
 * disagree about what "installed" means.
 */
export function packagedSkills(): PackagedSkill[] {
    const source = skillsDirectory()

    if (!existsSync(source)) {
        return []
    }

    return readdirSync(source)
        .sort()
        .filter((name) => existsSync(join(source, name, 'SKILL.md')))
        .map((name) => ({
            name,
            target: `.claude/skills/${name}/SKILL.md`,
            contents: readFileSync(join(source, name, 'SKILL.md'), 'utf8'),
        }))
}

/**
 * Brings .claude/skills/ in line with the packaged skills and returns the paths
 * it wrote. Run by `check --fix`, so upgrading the package and fixing is enough
 * to pick up new and changed skills. The skills belong to the package, like the
 * canonical .editorconfig: a local edit is drift and gets overwritten. Skills the
 * package does not ship are left alone.
 */
export function syncSkills(project: Project): string[] {
    const written: string[] = []

    for (const skill of packagedSkills()) {
        if (project.read(skill.target) === skill.contents) {
            continue
        }

        project.write(skill.target, skill.contents)
        written.push(skill.target)
    }

    return written
}

export interface InstallSkillsOptions {
    force: boolean
}

export function runInstallSkills(project: Project, options: InstallSkillsOptions): number {
    const source = skillsDirectory()

    if (!existsSync(source)) {
        console.error(`No packaged skills found at ${source}.`)

        return 1
    }

    let installed = 0
    let skipped = 0

    for (const skill of packagedSkills()) {
        if (project.exists(skill.target) && !options.force) {
            console.log(`⏭  ${skill.name} (already installed — pass --force to overwrite)`)
            skipped += 1

            continue
        }

        project.write(skill.target, skill.contents)
        console.log(`✅ ${skill.name} → ${skill.target}`)
        installed += 1
    }

    if (installed === 0 && skipped === 0) {
        console.error('No skills were installed.')

        return 1
    }

    console.log(`\n${installed} skill(s) installed, ${skipped} left untouched.`)

    return 0
}

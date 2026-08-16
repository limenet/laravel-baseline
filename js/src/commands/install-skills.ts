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

    for (const skill of readdirSync(source).sort()) {
        const file = join(source, skill, 'SKILL.md')

        if (!existsSync(file)) {
            continue
        }

        const target = `.claude/skills/${skill}/SKILL.md`

        if (project.exists(target) && !options.force) {
            console.log(`⏭  ${skill} (already installed — pass --force to overwrite)`)
            skipped += 1

            continue
        }

        project.write(target, readFileSync(file, 'utf8'))
        console.log(`✅ ${skill} → ${target}`)
        installed += 1
    }

    if (installed === 0 && skipped === 0) {
        console.error('No skills were installed.')

        return 1
    }

    console.log(`\n${installed} skill(s) installed, ${skipped} left untouched.`)

    return 0
}

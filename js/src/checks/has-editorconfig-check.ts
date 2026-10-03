import { policy } from '../policy.js'
import { type CheckResult, FixableCheck } from './check.js'

export class HasEditorconfigCheck extends FixableCheck {
    static override readonly checkName = 'hasEditorconfig'

    fix(dry = false): CheckResult {
        const contents = this.project.read('.editorconfig')

        if (contents === null) {
            this.comment('Editorconfig missing: Create .editorconfig in project root')

            if (dry) {
                return 'fail'
            }

            this.project.write('.editorconfig', this.canonicalContent())

            return this.fix(true)
        }

        if (contents.trim() === '') {
            this.comment('Editorconfig empty: Add content to .editorconfig')

            if (dry) {
                return 'fail'
            }

            this.project.write('.editorconfig', this.canonicalContent())

            return this.fix(true)
        }

        // A subset of the canonical file, so a project may add its own sections
        // without failing.
        const missing = policy()
            .strings('editorconfig.requiredProperties')
            .filter((property) => !contents.includes(property))

        if (missing.length === 0) {
            return 'pass'
        }

        this.comment(`Editorconfig incomplete: Add "${missing[0]}" to .editorconfig`)

        if (dry) {
            return 'fail'
        }

        this.project.write('.editorconfig', missing.reduce(mergeProperty, contents))

        return this.fix(true)
    }

    private canonicalContent(): string {
        return policy().template(policy().string('editorconfig.template'))
    }
}

const SECTION = /^\s*\[/

/**
 * Adds one required property to an existing .editorconfig without touching the
 * project's own sections, mirroring HasEditorconfigCheck::mergeProperty() in the
 * PHP runner. `root` belongs in the preamble; everything else in `[*]`, where a
 * line for the same key is replaced and a new one is appended. A file with no
 * `[*]` gets one ahead of its first section, so the specific sections still
 * override it.
 */
export function mergeProperty(contents: string, property: string): string {
    const lines = contents.replace(/\n+$/, '').split('\n')
    const key = (property.split('=')[0] ?? '').trim()
    const keyLine = new RegExp(`^\\s*${key.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}\\s*=`)
    const nextSection = (from: number): number => {
        const index = lines.findIndex((line, i) => i >= from && SECTION.test(line))

        return index === -1 ? lines.length : index
    }

    if (key === 'root') {
        const preambleEnd = nextSection(0)
        const existing = lines.findIndex((line, i) => i < preambleEnd && keyLine.test(line))

        if (existing !== -1) {
            lines[existing] = property
        } else {
            lines.splice(0, 0, ...(preambleEnd === 0 ? [property, ''] : [property]))
        }

        return `${lines.join('\n')}\n`
    }

    const header = lines.findIndex((line) => line.trim() === '[*]')

    if (header === -1) {
        const at = nextSection(0)
        const block = ['[*]', property]

        if (at < lines.length) {
            block.push('')
        }

        if (at > 0 && lines[at - 1]?.trim() !== '') {
            block.unshift('')
        }

        lines.splice(at, 0, ...block)

        return `${lines.join('\n')}\n`
    }

    const end = nextSection(header + 1)
    const existing = lines.findIndex((line, i) => i > header && i < end && keyLine.test(line))

    if (existing !== -1) {
        lines[existing] = property
    } else {
        let last = end - 1

        while (last > header && lines[last]?.trim() === '') {
            last--
        }

        lines.splice(last + 1, 0, property)
    }

    return `${lines.join('\n')}\n`
}

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

        this.project.write('.editorconfig', this.canonicalContent())

        return this.fix(true)
    }

    private canonicalContent(): string {
        return policy().template(policy().string('editorconfig.template'))
    }
}

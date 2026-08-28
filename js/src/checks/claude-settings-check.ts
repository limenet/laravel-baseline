import { type CheckResult, FixableCheck } from './check.js'

export const CLAUDE_SETTINGS_FILE = '.claude/settings.json'

export interface ClaudeSettings {
    permissions?: { allow?: string[]; deny?: string[] }
    hooks?: Record<string, Array<{ matcher?: string; hooks?: Array<{ type?: string; command?: string }> }>>
}

/**
 * Shared plumbing for the .claude/settings.json checks, mirroring
 * src/Checks/AbstractClaudeSettingsCheck.php.
 */
export abstract class ClaudeSettingsCheck extends FixableCheck {
    protected readSettings(): ClaudeSettings {
        return this.project.readJson<ClaudeSettings>(CLAUDE_SETTINGS_FILE) ?? {}
    }

    protected writeSettings(settings: ClaudeSettings): void {
        this.project.write(CLAUDE_SETTINGS_FILE, `${JSON.stringify(settings, null, 4)}\n`)
    }

    /** Append the required values that are absent, preserving existing entries and order. */
    protected mergeMissing(existing: string[], required: string[]): string[] {
        return [...existing, ...required.filter((value) => !existing.includes(value))]
    }

    /**
     * The permissions.allow / permissions.deny checks are the same shape: diff a
     * required list against what is there, comment on each gap, then append.
     */
    protected requireEntries(
        kind: 'allow' | 'deny',
        required: string[],
        message: (entry: string) => string,
        dry: boolean,
    ): CheckResult {
        const settings = this.readSettings()
        const existing = settings.permissions?.[kind] ?? []
        const missing = required.filter((entry) => !existing.includes(entry))

        if (missing.length === 0) {
            return 'pass'
        }

        for (const entry of missing) {
            this.comment(message(entry))
        }

        if (dry) {
            return 'fail'
        }

        settings.permissions = {
            ...settings.permissions,
            [kind]: this.mergeMissing(existing, required),
        }

        this.writeSettings(settings)

        return 'pass'
    }
}

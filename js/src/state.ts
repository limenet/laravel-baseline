import type { Project } from './project.js'

/**
 * Per-project runner state, in `.baseline.json` at the project root.
 *
 * The PHP runner keeps the same two things in `config/baseline.php` — a JS
 * project has no `config/` directory to write a PHP file into, so the shape is
 * mirrored in JSON instead:
 *
 *   { "excludes": ["hasEditorconfig"], "periodic": { "updatesDependencies": "…" } }
 */
export const STATE_FILE = '.baseline.json'

export interface BaselineState {
    excludes?: string[]
    periodic?: Record<string, string>
}

export function readState(project: Project): BaselineState {
    return project.readJson<BaselineState>(STATE_FILE) ?? {}
}

export function excludedChecks(project: Project): string[] {
    return readState(project).excludes ?? []
}

export function writeState(project: Project, state: BaselineState): void {
    project.writeJson(STATE_FILE, state)
}

/** When the developer last confirmed a periodic check, or null if never. */
export function lastRun(project: Project, checkName: string): Date | null {
    const recorded = readState(project).periodic?.[checkName]

    if (recorded === undefined) {
        return null
    }

    const parsed = new Date(recorded)

    return Number.isNaN(parsed.getTime()) ? null : parsed
}

export function recordRun(project: Project, checkName: string, when: Date = new Date()): void {
    const state = readState(project)

    writeState(project, {
        ...state,
        periodic: { ...state.periodic, [checkName]: when.toISOString() },
    })
}

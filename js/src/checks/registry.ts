import type { Project } from '../project.js'
import type { Check, CommentCollector } from './check.js'
import { DoesNotHaveCopilotOrJunieAgentFilesCheck } from './does-not-have-copilot-or-junie-agent-files-check.js'
import { HardensNpmSupplyChainCheck } from './hardens-npm-supply-chain-check.js'
import { HasEditorconfigCheck } from './has-editorconfig-check.js'
import { NodeVersionCheck } from './node-version-check.js'

type CheckConstructor = (new (
    project: Project,
    comments: CommentCollector,
) => Check) & { readonly checkName: string }

/**
 * Every check this runner knows about, in execution and display order.
 *
 * This is intentionally a subset of the PHP runner's registry: only the checks
 * that mean something in a project with no PHP and no DDEV are here. See the
 * README for the ported / not-ported table.
 */
const checks: CheckConstructor[] = [
    DoesNotHaveCopilotOrJunieAgentFilesCheck,
    HardensNpmSupplyChainCheck,
    HasEditorconfigCheck,
    NodeVersionCheck,
]

export function allChecks(): CheckConstructor[] {
    return [...checks]
}

export function checkNames(): string[] {
    return checks.map((check) => check.checkName)
}

export function findCheck(name: string): CheckConstructor | undefined {
    return checks.find((check) => check.checkName === name)
}

export function createChecks(project: Project, comments: CommentCollector): Check[] {
    return checks.map((Constructor) => new Constructor(project, comments))
}

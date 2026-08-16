import type { Project } from '../project.js'

/** Mirrors Limenet\LaravelBaseline\Enums\CheckResult, values included. */
export type CheckResult = 'pass' | 'fail' | 'warn'

export function isError(result: CheckResult): boolean {
    return result === 'fail'
}

export function icon(result: CheckResult): string {
    return { pass: '✅', fail: '❌', warn: '⚠️' }[result]
}

/** Collects the actionable messages a single check run produced. */
export class CommentCollector {
    private comments: string[] = []

    add(comment: string): void {
        this.comments.push(comment)
    }

    all(): string[] {
        return [...this.comments]
    }

    reset(): void {
        this.comments = []
    }
}

export abstract class Check {
    /**
     * The check's stable identifier — the same string the PHP runner derives from
     * its class name, and the key used in .baseline.json excludes and in fixtures.
     * Declared explicitly rather than derived, so a bundler renaming a class can
     * never silently rename a check.
     */
    static readonly checkName: string

    constructor(
        protected readonly project: Project,
        protected readonly comments: CommentCollector,
    ) {}

    abstract check(): CheckResult

    protected comment(message: string): void {
        this.comments.add(message)
    }

    get name(): string {
        return (this.constructor as typeof Check).checkName
    }
}

/**
 * A check that can repair what it detects. check() is the dry run, so detection
 * and repair can never drift apart — the same shape as the PHP
 * AbstractFixableCheck.
 */
export abstract class FixableCheck extends Check {
    check(): CheckResult {
        return this.fix(true)
    }

    abstract fix(dry?: boolean): CheckResult
}

export function isFixable(check: Check): check is FixableCheck {
    return check instanceof FixableCheck
}

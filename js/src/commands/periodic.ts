import { createInterface } from 'node:readline/promises'
import { CommentCollector } from '../checks/check.js'
import { isPeriodic } from '../checks/periodic-check.js'
import { createChecks } from '../checks/registry.js'
import type { Project } from '../project.js'
import { recordRun } from '../state.js'

/**
 * Walks the developer through every expired periodic check and records the ones
 * they confirm. Mirrors the artisan limenet:laravel-baseline:periodic command.
 */
export async function runPeriodic(project: Project): Promise<number> {
    const comments = new CommentCollector()

    const expired = createChecks(project, comments)
        .filter(isPeriodic)
        .filter((check) => check.isApplicable())
        .filter((check) => check.check() === 'fail')

    if (expired.length === 0) {
        console.log('All periodic checks are up to date!')

        return 0
    }

    const readline = createInterface({ input: process.stdin, output: process.stdout })

    try {
        for (const check of expired) {
            console.log(`\n${check.name}`)
            console.log(check.promptDescription())

            const answer = await readline.question('\nHave you completed this task? [y/N] ')

            if (answer.trim().toLowerCase().startsWith('y')) {
                recordRun(project, check.name)
                console.log('✅ Marked as done.')
            } else {
                console.log('⏭ Skipped.')
            }
        }
    } finally {
        readline.close()
    }

    return 0
}

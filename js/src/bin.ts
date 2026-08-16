#!/usr/bin/env node
import { parseArgs } from 'node:util'
import { runCheck } from './commands/check.js'
import { runPeriodic } from './commands/periodic.js'
import { Project } from './project.js'

const USAGE = `baseline — a highly opinionated baseline for JS/TS projects.

Usage:
  baseline check [--fix] [--verbose] [--cwd <path>]
  baseline periodic [--cwd <path>]

Options:
  --fix        Repair what can be repaired, then re-verify
  --verbose    Show passing checks too
  --cwd        Project root to inspect (default: the current directory)
  --help       Show this message
`

async function main(): Promise<number> {
    const { values, positionals } = parseArgs({
        allowPositionals: true,
        options: {
            fix: { type: 'boolean', default: false },
            verbose: { type: 'boolean', short: 'v', default: false },
            cwd: { type: 'string' },
            help: { type: 'boolean', short: 'h', default: false },
        },
    })

    const command = positionals[0]

    if (values.help || command === undefined) {
        console.log(USAGE)

        return values.help ? 0 : 1
    }

    const project = Project.at(values.cwd)

    switch (command) {
        case 'check':
            return runCheck(project, { fix: values.fix, verbose: values.verbose })
        case 'periodic':
            return await runPeriodic(project)
        default:
            console.error(`Unknown command "${command}".\n\n${USAGE}`)

            return 1
    }
}

process.exitCode = await main()

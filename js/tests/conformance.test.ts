import { cpSync, existsSync, mkdtempSync, readdirSync, readFileSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'
import { expect, it } from 'vitest'
import { CommentCollector } from '../src/checks/check.js'
import { findCheck } from '../src/checks/registry.js'
import { Policy } from '../src/policy.js'
import { Project } from '../src/project.js'

/**
 * Executes the shared fixtures in `fixtures/` — the same tree the PHP runner's
 * Pest suite executes (tests/Conformance/FixtureConformanceTest.php). Agreeing on
 * these verdicts is what keeps the two implementations from drifting.
 */
const fixtureRoot = join(dirname(fileURLToPath(import.meta.url)), '..', '..', 'fixtures')

interface FixtureCase {
    check: string
    description: string
    engines: string[]
    expect: string
    fix?: {
        expect: string
        files?: Record<string, string>
        json?: Record<string, Record<string, unknown>>
        absent?: string[]
    }
}

function cases(onlyFixable = false): Array<{ name: string; dir: string; case: FixtureCase }> {
    const found: Array<{ name: string; dir: string; case: FixtureCase }> = []

    for (const check of readdirSync(fixtureRoot).sort()) {
        for (const name of readdirSync(join(fixtureRoot, check)).sort()) {
            const dir = join(fixtureRoot, check, name)
            const parsed = JSON.parse(readFileSync(join(dir, 'case.json'), 'utf8')) as FixtureCase

            if (!parsed.engines.includes('js')) {
                continue
            }

            if (onlyFixable && parsed.fix === undefined) {
                continue
            }

            found.push({ name: `${parsed.check} — ${parsed.description}`, dir, case: parsed })
        }
    }

    return found
}

function materialise(dir: string): Project {
    const temp = mkdtempSync(join(tmpdir(), 'baseline-fixture-'))

    cpSync(join(dir, 'project'), temp, { recursive: true })

    return Project.at(temp)
}

function makeCheck(project: Project, name: string) {
    const Constructor = findCheck(name)

    if (Constructor === undefined) {
        throw new Error(`Fixture references unregistered check "${name}"`)
    }

    return new Constructor(project, new CommentCollector())
}

function get(data: unknown, path: string): unknown {
    return path.split('.').reduce<unknown>((carry, segment) => {
        if (typeof carry !== 'object' || carry === null) {
            return undefined
        }

        return (carry as Record<string, unknown>)[segment]
    }, data)
}

it.each(cases())('reaches the shared fixture verdict: $name', ({ dir, case: fixture }) => {
    const project = materialise(dir)

    expect(makeCheck(project, fixture.check).check()).toBe(fixture.expect)
})

it.each(cases(true))(
    'leaves the shared fixture file state behind after a fix: $name',
    ({ dir, case: fixture }) => {
        const project = materialise(dir)
        const check = makeCheck(project, fixture.check)

        if (!('fix' in check) || typeof check.fix !== 'function') {
            throw new Error(`Fixture expects ${fixture.check} to be fixable, but it is not`)
        }

        expect(check.fix()).toBe(fixture.fix?.expect)

        for (const [path, expected] of Object.entries(fixture.fix?.files ?? {})) {
            // @template:<name> resolves to policy/templates/<name>, so canonical file
            // bodies are never duplicated into a fixture.
            const resolved = expected.startsWith('@template:')
                ? Policy.fromDirectory().template(expected.slice('@template:'.length))
                : expected

            expect(project.read(path)).toBe(resolved)
        }

        for (const [path, assertions] of Object.entries(fixture.fix?.json ?? {})) {
            const data = project.readJson(path)

            for (const [key, expected] of Object.entries(assertions)) {
                expect(get(data, key)).toStrictEqual(expected)
            }
        }

        for (const path of fixture.fix?.absent ?? []) {
            expect(existsSync(project.path(path))).toBe(false)
        }
    },
)

it('covers every fixture that declares the js engine', () => {
    const declared = cases()

    expect(declared.length).toBeGreaterThan(0)

    for (const { case: fixture } of declared) {
        expect(findCheck(fixture.check), `no js implementation for ${fixture.check}`).toBeDefined()
    }
})

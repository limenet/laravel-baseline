import { policy } from '../policy.js'
import {
    append,
    configObject,
    hasOpaqueMembers,
    type JsNode,
    membersNamed,
} from '../support/js-object-literal.js'
import { type CheckResult, FixableCheck } from './check.js'

const PATH = ['server', 'watch', 'ignored']

/**
 * Where the globs live or would go: the `ignored` array when the config has
 * one, otherwise the innermost object that exists and the keys still to be
 * written into it.
 */
interface Target {
    node: JsNode
    keys: string[]
}

/**
 * Vite's dev server watches the whole project root and skips only `.git`,
 * `node_modules`, `test-results`, its cache and its build output. Everything
 * else a project git-ignores — coverage reports, tool caches — is watched too:
 * file handles nobody needs, and a reload whenever a test run or a cache write
 * touches one of them.
 *
 * So `server.watch.ignored` has to list those directories. Vite appends the
 * option to its own defaults and matches each glob against absolute paths,
 * which is why every entry is a directory name between two double-star
 * segments rather than a path anchored to the project root.
 *
 * A project without a Vite config is none of this check's business: pass.
 *
 * The check reads the exported object literal with a scanner rather than a
 * parser and edits it as text; a config it cannot follow — a block-bodied
 * function, `server` built from a variable or a spread — is reported and left
 * alone. See js-object-literal.ts for why it is not a real parser.
 */
export class ViteWatchIgnoresGeneratedDirectoriesCheck extends FixableCheck {
    static override readonly checkName = 'viteWatchIgnoresGeneratedDirectories'

    fix(dry = false): CheckResult {
        const configFile = policy()
            .strings('vite.configFiles')
            .find((candidate) => this.project.exists(candidate))

        if (configFile === undefined) {
            return 'pass'
        }

        const contents = this.project.read(configFile) ?? ''
        const required = policy().strings('vite.watchIgnored.shared')
        const target = locate(contents)

        if (typeof target === 'string') {
            this.comment(
                `Cannot verify server.watch.ignored in ${configFile} (${target}): make sure it lists ${quoted(required)}, so the dev server does not watch generated directories`,
            )

            return 'fail'
        }

        const absent = missing(target, required)

        if (absent.length === 0) {
            return 'pass'
        }

        this.comment(
            `Missing in ${configFile}: server.watch.ignored must list ${quoted(absent)}, so the dev server does not watch generated directories`,
        )

        if (dry) {
            return 'fail'
        }

        const fixed = withGlobs(contents, absent)

        // Written only when a second reading agrees the edit did what it was
        // meant to — the file is hand-written code, not a generated config.
        const verified = locate(fixed)

        if (typeof verified === 'string' || missing(verified, required).length > 0) {
            return 'fail'
        }

        this.project.write(configFile, fixed)

        return 'pass'
    }
}

/** The target, or — as a string — why the config cannot be followed. */
function locate(contents: string): Target | string {
    let object = configObject(contents)

    if (object === null) {
        return 'it does not export a plain object literal'
    }

    for (const [depth, key] of PATH.entries()) {
        const path = PATH.slice(0, depth + 1).join('.')
        const members = membersNamed(object, key)

        if (members.length > 1) {
            return `${path} is declared more than once`
        }

        const value = members[0]?.value ?? null

        if (members.length === 0) {
            return hasOpaqueMembers(object)
                ? `${path} may come from a spread or a computed key`
                : { node: object, keys: PATH.slice(depth) }
        }

        if (value?.kind !== (key === 'ignored' ? 'array' : 'object')) {
            return key === 'ignored' ? `${path} is not an array literal` : `${path} is not an object literal`
        }

        object = value
    }

    return { node: object, keys: [] }
}

function missing(target: Target, required: string[]): string[] {
    if (target.keys.length > 0) {
        return required
    }

    const present = target.node.elements.map((element) => element.value)

    return required.filter((glob) => !present.includes(glob))
}

function withGlobs(source: string, globs: string[]): string {
    const unit = /^([ \t]+)[^\s*]/m.exec(source)?.[1] ?? '    '
    const target = locate(source)

    if (typeof target === 'string') {
        return source
    }

    if (target.keys.length > 0) {
        const quote = quoteFor(source, null)

        return append(source, target.node, unit, (indent, trailing, inline) =>
            render(target.keys, globs, quote, unit, indent, trailing, inline),
        )
    }

    let contents = source

    // One at a time, re-reading in between: every append moves the offsets.
    for (const glob of globs) {
        const current = locate(contents)

        if (typeof current === 'string') {
            break
        }

        const quote = quoteFor(contents, current.node)
        contents = append(contents, current.node, unit, () => quote + glob + quote)
    }

    return contents
}

/**
 * `server: { watch: { ignored: […] } }` from the first key still missing,
 * without a comma after it — the caller knows whether one belongs there.
 */
function render(
    keys: string[],
    globs: string[],
    quote: string,
    unit: string,
    indent: string,
    trailing: boolean,
    inline: boolean,
): string {
    const entries = globs.map((glob) => quote + glob + quote)
    const objects = keys.slice(0, -1)

    if (inline) {
        return `${objects.map((key) => `${key}: { `).join('')}ignored: [${entries.join(', ')}]${' }'.repeat(objects.length)}`
    }

    const comma = trailing ? ',' : ''
    const depth = objects.length
    const lines = objects.map((key, level) => `${unit.repeat(level)}${key}: {`)

    lines.push(`${unit.repeat(depth)}ignored: [`)

    for (const [index, entry] of entries.entries()) {
        lines.push(`${unit.repeat(depth + 1)}${entry}${index === entries.length - 1 ? comma : ','}`)
    }

    lines.push(`${unit.repeat(depth)}]${depth > 0 ? comma : ''}`)

    for (let level = depth - 1; level >= 0; level--) {
        lines.push(`${unit.repeat(level)}}${level > 0 ? comma : ''}`)
    }

    return lines.join(`\n${indent}`)
}

/**
 * The quote the new entries are written with: the one their siblings use,
 * otherwise the one the file uses, otherwise a single quote.
 */
function quoteFor(contents: string, array: JsNode | null): string {
    for (const element of array?.elements ?? []) {
        if (element.quote === "'" || element.quote === '"') {
            return element.quote
        }
    }

    return contents.includes('"') && !contents.includes("'") ? '"' : "'"
}

function quoted(globs: string[]): string {
    return globs.map((glob) => `'${glob}'`).join(', ')
}

import { findNodeAtLocation, type Node, type ParseError, parseTree } from 'jsonc-parser'
import { policy } from '../policy.js'
import { type CheckResult, FixableCheck } from './check.js'

/**
 * A set of files Biome must not check, kept out through `files.includes` — the
 * TS counterpart of the PHP AbstractBiomeIgnoresCheck.
 *
 * `files.includes` is evaluated the way Biome does: in order, the last pattern
 * matching the file deciding, and no `includes` at all meaning every file. A
 * pattern matching one of the file's directories counts as matching the file,
 * so `!dist` keeps `dist/app.js` out. A project whose includes are an
 * allowlist that never reaches the files is fine as it is.
 *
 * Like biomeUsesLocalSchema, the fix edits the text rather than re-encoding the
 * document: Biome formats biome.json itself, and the file may carry comments.
 */
export abstract class BiomeIgnoresCheck extends FixableCheck {
    fix(dry = false): CheckResult {
        const configFile = policy().string('biome.configFile')
        let contents = this.project.read(configFile)

        if (contents === null) {
            return 'pass'
        }

        const root = parse(contents)

        if (root?.type !== 'object') {
            this.comment(`${configFile} is not valid JSON`)

            return 'fail'
        }

        const includes = findNodeAtLocation(root, ['files', 'includes'])

        if (includes !== undefined && includes.type !== 'array') {
            this.comment(`Invalid files.includes in ${configFile}: must be a list of glob patterns`)

            return 'fail'
        }

        const exposed = this.ignored().filter((pattern) =>
            this.probes(pattern).some((probe) => processes(includes, probe)),
        )

        if (exposed.length === 0) {
            return 'pass'
        }

        const listed = exposed.join(', ')
        const entries = exposed.map((pattern) => JSON.stringify(`!${pattern}`)).join(', ')
        const describe = this.describe()

        if (includes === undefined) {
            this.comment(
                `Biome checks ${describe} (${listed}) because ${configFile} has no files.includes: add "files": { "includes": ["**", ${entries}] }`,
            )

            return 'fail'
        }

        const consequence = this.consequence() === '' ? '' : `, ${this.consequence()}`

        this.comment(
            `Biome checks ${describe} (${listed}) through files.includes in ${configFile}${consequence}: add ${entries} at the end of files.includes`,
        )

        if (dry) {
            return 'fail'
        }

        for (const pattern of exposed) {
            contents = appendExclusion(contents, pattern)
        }

        this.project.write(configFile, contents)

        return this.fix(true)
    }

    /** The patterns to append, negated, to files.includes. */
    protected abstract ignored(): string[]

    /** What the files are, for the comment (e.g. "CI runner artifacts"). */
    protected abstract describe(): string

    /**
     * The project-relative paths standing for a pattern, any of which being
     * processed means the pattern still has to be excluded.
     */
    protected probes(pattern: string): string[] {
        return [pattern]
    }

    /** Why checking them hurts, appended to the comment after a comma; empty for none. */
    protected consequence(): string {
        return ''
    }
}

/** The document's root, or undefined when the source is not valid JSONC. */
function parse(contents: string): Node | undefined {
    const errors: ParseError[] = []
    const root = parseTree(contents, errors, { allowTrailingComma: true })

    return errors.length === 0 ? root : undefined
}

/**
 * Whether Biome processes a file: no includes means everything, otherwise the
 * last matching pattern decides.
 */
function processes(includes: Node | undefined, file: string): boolean {
    if (includes === undefined) {
        return true
    }

    let processed = false

    for (const pattern of includes.children ?? []) {
        if (typeof pattern.value !== 'string') {
            continue
        }

        if (matches(pattern.value.replace(/^!+/, ''), file)) {
            processed = !pattern.value.startsWith('!')
        }
    }

    return processed
}

/**
 * Biome's glob syntax: `*` stays within a path segment, `**` crosses them.
 * Matching a parent directory matches everything inside it.
 */
function matches(glob: string, file: string): boolean {
    const source = glob.replace(/^\.\//, '')
    let regex = ''

    for (let i = 0; i < source.length; i++) {
        if (source.startsWith('**/', i)) {
            regex += '(?:.*/)?'
            i += 2
        } else if (source.startsWith('**', i)) {
            regex += '.*'
            i++
        } else if (source[i] === '*') {
            regex += '[^/]*'
        } else if (source[i] === '?') {
            regex += '[^/]'
        } else {
            regex += (source[i] ?? '').replace(/[.*+?^${}()|[\]\\/]/g, '\\$&')
        }
    }

    return new RegExp(`^${regex}(?:/.*)?$`).test(file)
}

/**
 * Appends `"!<pattern>"` as the last entry of files.includes — last, because
 * a later positive pattern would otherwise take the file back — on its own
 * line, indented like the entry before it, when the array spans lines.
 */
function appendExclusion(contents: string, pattern: string): string {
    const root = parse(contents)
    const includes = root === undefined ? undefined : findNodeAtLocation(root, ['files', 'includes'])
    const last = includes?.children?.at(-1)

    // Only reached for an array that processes the file, so it has a
    // positive pattern and therefore at least one entry.
    if (includes === undefined || last === undefined) {
        return contents
    }

    const entry = JSON.stringify(`!${pattern}`)
    const lastEnd = last.offset + last.length

    if (!contents.slice(includes.offset, includes.offset + includes.length).includes('\n')) {
        return insert(contents, `, ${entry}`, lastEnd)
    }

    const lineStart = contents.lastIndexOf('\n', last.offset) + 1
    const indent = /^[ \t]*/.exec(contents.slice(lineStart))?.[0] ?? ''
    const line = `\n${indent}${entry}`

    // A line comment after the last entry describes that entry, so the new
    // one goes on the next line rather than between the two.
    const newline = contents.indexOf('\n', lastEnd)
    const lineEnd = newline === -1 ? contents.length : newline
    const tail = /^[ \t]*(,)?[ \t]*(\/\/.*)?$/.exec(contents.slice(lastEnd, lineEnd))

    if (tail === null) {
        return insert(contents, `,${line}`, lastEnd)
    }

    if (tail[1] === ',') {
        return insert(contents, `${line},`, lineEnd)
    }

    return insert(insert(contents, line, lineEnd), ',', lastEnd)
}

function insert(contents: string, text: string, offset: number): string {
    return contents.slice(0, offset) + text + contents.slice(offset)
}

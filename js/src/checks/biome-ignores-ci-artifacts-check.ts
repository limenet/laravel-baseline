import { findNodeAtLocation, type Node, type ParseError, parseTree } from 'jsonc-parser'
import { policy } from '../policy.js'
import { type CheckResult, FixableCheck } from './check.js'

/**
 * Biome must not check the files a CI runner leaves in the project root. The
 * GitLab runner extracts a `metadata.json` holding the cache key next to the
 * checkout whenever it restores a cache; a `files.includes` that starts from
 * `**` picks it up, and `biome ci` fails on a file nobody committed — but only
 * on the runs that happened to restore a cache.
 *
 * `files.includes` is evaluated the way Biome does: in order, the last pattern
 * matching the file deciding, and no `includes` at all meaning every file. A
 * project whose includes are an allowlist that never reaches the root is fine
 * as it is.
 *
 * Like biomeUsesLocalSchema, the fix edits the text rather than re-encoding the
 * document: Biome formats biome.json itself, and the file may carry comments.
 */
export class BiomeIgnoresCiArtifactsCheck extends FixableCheck {
    static override readonly checkName = 'biomeIgnoresCiArtifacts'

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

        const exposed = policy()
            .strings('biome.ciArtifacts')
            .filter((artifact) => processes(includes, artifact))

        if (exposed.length === 0) {
            return 'pass'
        }

        const listed = exposed.join(', ')
        const entries = exposed.map((artifact) => JSON.stringify(`!${artifact}`)).join(', ')

        if (includes === undefined) {
            this.comment(
                `Biome checks CI runner artifacts (${listed}) because ${configFile} has no files.includes: add "files": { "includes": ["**", ${entries}] }`,
            )

            return 'fail'
        }

        this.comment(
            `Biome checks CI runner artifacts (${listed}) through files.includes in ${configFile}, failing \`biome ci\` whenever the runner restores a cache: add ${entries} at the end of files.includes`,
        )

        if (dry) {
            return 'fail'
        }

        for (const artifact of exposed) {
            contents = appendExclusion(contents, artifact)
        }

        this.project.write(configFile, contents)

        return this.fix(true)
    }
}

/** The document's root, or undefined when the source is not valid JSONC. */
function parse(contents: string): Node | undefined {
    const errors: ParseError[] = []
    const root = parseTree(contents, errors, { allowTrailingComma: true })

    return errors.length === 0 ? root : undefined
}

/**
 * Whether Biome processes a root-level file: no includes means everything,
 * otherwise the last matching pattern decides.
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

/** Biome's glob syntax: `*` stays within a path segment, `**` crosses them. */
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

    return new RegExp(`^${regex}$`).test(file)
}

/**
 * Appends `"!<artifact>"` as the last entry of files.includes — last, because
 * a later positive pattern would otherwise take the file back — on its own
 * line, indented like the entry before it, when the array spans lines.
 */
function appendExclusion(contents: string, artifact: string): string {
    const root = parse(contents)
    const includes = root === undefined ? undefined : findNodeAtLocation(root, ['files', 'includes'])
    const last = includes?.children?.at(-1)

    // Only reached for an array that processes the artifact, so it has a
    // positive pattern and therefore at least one entry.
    if (includes === undefined || last === undefined) {
        return contents
    }

    const entry = JSON.stringify(`!${artifact}`)
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

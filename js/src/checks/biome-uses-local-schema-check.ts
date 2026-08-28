import { policy } from '../policy.js'
import { type CheckResult, FixableCheck } from './check.js'

/** The `$schema` string literal, captured with its JSON escapes intact. */
const SCHEMA_PATTERN = /"\$schema"\s*:\s*"((?:[^"\\]|\\.)*)"/

/**
 * Biome's `$schema` must resolve out of node_modules rather than name a released
 * version. The local path always describes the Biome that is actually installed,
 * so upgrading Biome does not also mean editing biome.json — a versioned remote
 * URL leaves the editor validating against whichever version was current when
 * someone last remembered to bump the line.
 *
 * A project without Biome is none of this check's business: no config file means
 * pass, not a warning.
 *
 * Both the detection and the fix work on the raw text rather than a decoded
 * document, for two reasons: Biome reads its config as JSONC, so a file with
 * comments is valid and must not be reported as broken; and biome.json is
 * formatted by Biome itself, so re-encoding the whole file would leave
 * `biome ci` failing on the very file this fix just touched.
 */
export class BiomeUsesLocalSchemaCheck extends FixableCheck {
    static override readonly checkName = 'biomeUsesLocalSchema'

    fix(dry = false): CheckResult {
        const configFile = policy().string('biome.configFile')
        const contents = this.project.read(configFile)

        if (contents === null) {
            return 'pass'
        }

        if (!contents.includes('{')) {
            this.comment(`${configFile} is empty or unreadable`)

            return 'fail'
        }

        const expected = policy().string('biome.schema')
        const declared = declaredSchema(contents)

        if (declared === expected) {
            return 'pass'
        }

        this.comment(
            declared === null
                ? `Missing "$schema" in ${configFile}: set it to "${expected}" so the schema follows the installed Biome`
                : `Invalid "$schema" in ${configFile}: must be "${expected}" (found "${declared}"), so it does not need bumping on every Biome update`,
        )

        if (dry) {
            return 'fail'
        }

        this.project.write(
            configFile,
            declared === null ? insertSchema(contents, expected) : replaceSchema(contents, expected),
        )

        return this.fix(true)
    }
}

/** The decoded `$schema` value, or null if the file declares none. */
function declaredSchema(contents: string): string | null {
    const literal = SCHEMA_PATTERN.exec(contents)?.[1]

    if (literal === undefined) {
        return null
    }

    try {
        const decoded: unknown = JSON.parse(`"${literal}"`)

        return typeof decoded === 'string' ? decoded : null
    } catch {
        return null
    }
}

function replaceSchema(contents: string, expected: string): string {
    // A function, not a replacement string: the literal "$schema" would otherwise
    // have to be escaped against `$`-pattern interpolation.
    return contents.replace(SCHEMA_PATTERN, () => `"$schema": ${JSON.stringify(expected)}`)
}

/**
 * Prepends the key inside the top-level object, indented like whatever key
 * currently comes first — Biome's own convention is `$schema` first.
 */
function insertSchema(contents: string, expected: string): string {
    const open = contents.indexOf('{')
    const rest = contents.slice(open + 1)
    const indent = /\n([ \t]+)\S/.exec(rest)?.[1] ?? '    '
    const separator = rest.trim() === '}' ? '' : ','

    return `${contents.slice(0, open + 1)}\n${indent}"$schema": ${JSON.stringify(expected)}${separator}${rest}`
}
